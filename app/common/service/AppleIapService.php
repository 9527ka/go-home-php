<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\config\IapProducts;
use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use think\facade\Log;

/**
 * Apple In-App Purchase 收据验证服务
 */
class AppleIapService
{
    const PRODUCTION_URL = 'https://buy.itunes.apple.com/verifyReceipt';
    const SANDBOX_URL    = 'https://sandbox.itunes.apple.com/verifyReceipt';

    /**
     * 验证 Apple 收据并返回交易信息
     *
     * @param string $receiptData Base64 编码的收据数据
     * @param string $productId   客户端声称购买的产品 ID
     * @return array{original_transaction_id: string, product_id: string, coins: int}
     * @throws BusinessException
     */
    public static function verify(string $receiptData, string $productId): array
    {
        $sharedSecret = env('APPLE_SHARED_SECRET', '');
        if (empty($sharedSecret)) {
            Log::error('[AppleIAP] APPLE_SHARED_SECRET not configured');
            throw new BusinessException(ErrorCode::SYSTEM_ERROR, 'IAP configuration error');
        }

        $payload = [
            'receipt-data'             => $receiptData,
            'password'                 => $sharedSecret,
            'exclude-old-transactions' => true,
        ];

        // 先请求 production，若返回 21007 则重试 sandbox
        $response = self::sendRequest(self::PRODUCTION_URL, $payload);

        if ($response === null) {
            throw new BusinessException(ErrorCode::SYSTEM_ERROR, 'Apple receipt verification failed');
        }

        // 21007 = sandbox 收据发到了 production
        if (($response['status'] ?? -1) === 21007) {
            $response = self::sendRequest(self::SANDBOX_URL, $payload);
            if ($response === null) {
                throw new BusinessException(ErrorCode::SYSTEM_ERROR, 'Apple receipt verification failed (sandbox)');
            }
        }

        $status = $response['status'] ?? -1;
        if ($status !== 0) {
            Log::warning("[AppleIAP] Receipt verification failed, status: {$status}");
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, "Invalid receipt (status: {$status})");
        }

        // 验证 bundle_id（防止其他 app 的收据被提交）
        $expectedBundleId = env('APPLE_BUNDLE_ID', '');
        if (!empty($expectedBundleId)) {
            $receiptBundleId = $response['receipt']['bundle_id'] ?? '';
            if ($receiptBundleId !== $expectedBundleId) {
                Log::warning("[AppleIAP] Bundle ID mismatch: expected={$expectedBundleId}, got={$receiptBundleId}");
                throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, 'Receipt bundle ID mismatch');
            }
        }

        // 从 in_app 数组中找到匹配的交易
        $inApp = $response['receipt']['in_app'] ?? [];
        if (empty($inApp)) {
            // 也检查 latest_receipt_info（某些情况下 Apple 放在这里）
            $inApp = $response['latest_receipt_info'] ?? [];
        }

        if (empty($inApp)) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, 'No in-app transactions found in receipt');
        }

        // 找到与请求的 product_id 匹配的最新交易
        $matchedTransaction = null;
        foreach (array_reverse($inApp) as $transaction) {
            if (($transaction['product_id'] ?? '') === $productId) {
                $matchedTransaction = $transaction;
                break;
            }
        }

        if ($matchedTransaction === null) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, 'Product not found in receipt');
        }

        $verifiedProductId = $matchedTransaction['product_id'];
        $originalTransactionId = $matchedTransaction['original_transaction_id']
            ?? $matchedTransaction['transaction_id']
            ?? '';

        if (empty($originalTransactionId)) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, 'Missing transaction ID in receipt');
        }

        // 用服务端映射确定金币数（不信任客户端）
        $coins = IapProducts::getCoins($verifiedProductId);
        if ($coins === null) {
            Log::warning("[AppleIAP] Unknown product in receipt: {$verifiedProductId}");
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, 'Unknown product in receipt');
        }

        Log::info("[AppleIAP] Receipt verified", [
            'transaction_id' => $originalTransactionId,
            'product_id'     => $verifiedProductId,
            'coins'          => $coins,
        ]);

        return [
            'original_transaction_id' => $originalTransactionId,
            'product_id'              => $verifiedProductId,
            'coins'                   => $coins,
        ];
    }

    /**
     * 发送 HTTP 请求到 Apple 验证服务器
     * 对 status=21002（数据格式错误/Apple 临时故障）自动重试一次
     */
    private static function sendRequest(string $url, array $payload, bool $isRetry = false): ?array
    {
        // 诊断日志：帮助定位 receipt 是否为空 / 是否是 StoreKit 2 JWS / 是否被污染
        $receipt = (string)($payload['receipt-data'] ?? '');
        Log::info('[AppleIAP] sending to Apple', [
            'url'          => $url,
            'receipt_len'  => strlen($receipt),
            'receipt_head' => substr($receipt, 0, 40),
            'receipt_tail' => substr($receipt, -40),
            'has_secret'   => !empty($payload['password']),
            'is_retry'     => $isRetry,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false || $httpCode !== 200) {
            Log::error("[AppleIAP] HTTP request failed: {$error}, code: {$httpCode}");
            return null;
        }

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            Log::error('[AppleIAP] Invalid JSON response from Apple');
            return null;
        }

        // Apple 文档建议：status=21002 可能为临时故障，重试一次
        if (!$isRetry && ($decoded['status'] ?? -1) === 21002) {
            Log::warning('[AppleIAP] status 21002, retrying once');
            $retry = self::sendRequest($url, $payload, true);
            if ($retry !== null) {
                return $retry;
            }
        }

        return $decoded;
    }
}
