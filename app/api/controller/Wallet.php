<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\config\IapProducts;
use app\common\enum\ErrorCode;
use app\common\model\PostBoost;
use app\common\model\RechargeOrder;
use app\common\model\WalletSetting;
use app\common\model\WalletTransaction;
use app\common\model\WithdrawalOrder;
use app\common\service\AppleIapService;
use app\common\service\UploadService;
use app\common\service\WalletService;
use think\facade\Db;
use think\Response;

class Wallet extends BaseApi
{
    /**
     * 钱包信息
     * GET /api/wallet/info
     */
    public function info(): Response
    {
        WalletService::checkEnabled();

        $wallet = WalletService::getOrCreateWallet($this->getUserId());

        return $this->success([
            'balance'                => (float)$wallet->balance,
            'frozen_balance'         => (float)$wallet->frozen_balance,
            'reward_frozen_balance'  => (float)$wallet->reward_frozen_balance,
            'total_recharged'        => (float)$wallet->total_recharged,
            'total_withdrawn'        => (float)$wallet->total_withdrawn,
            'total_donated'          => (float)$wallet->total_donated,
            'total_received'         => (float)$wallet->total_received,
            'total_reward_earned'    => (float)$wallet->total_reward_earned,
            'settings'        => [
                'usdt_address_trc20' => WalletSetting::getValue('usdt_address_trc20'),
                'usdt_address_erc20' => WalletSetting::getValue('usdt_address_erc20'),
                'min_recharge'       => (float)WalletSetting::getValue('min_recharge', '1000'),
                'min_withdrawal'     => (float)WalletSetting::getValue('min_withdrawal', '2000'),
                'withdrawal_fee_rate' => (float)WalletSetting::getValue('withdrawal_fee_rate', '0.05'),
                'boost_hourly_rate'  => (float)WalletSetting::getValue('boost_hourly_rate', '1000'),
                'min_donation'       => (float)WalletSetting::getValue('min_donation', '100'),
                'max_red_packet_amount' => (float)WalletSetting::getValue('max_red_packet_amount', '50000'),
                'coin_name'          => '爱心币',
                'coin_rate_per_usdt' => 100,
            ],
        ]);
    }

    /**
     * 流水明细
     * GET /api/wallet/transactions?type=&page=
     */
    public function transactions(): Response
    {
        $page = max(1, (int)$this->request->get('page', 1));
        $type = $this->request->get('type');

        $query = WalletTransaction::where('user_id', $this->getUserId())
            ->order('created_at', 'desc');

        if (!is_null($type) && $type !== '') {
            $query->where('type', (int)$type);
        }

        $list = $query->paginate([
            'list_rows' => 20,
            'page'      => $page,
        ]);

        return $this->successPage([
            'list'      => $list->items(),
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ]);
    }

    /**
     * 提交充值申请
     * POST /api/wallet/recharge  {amount, tx_hash, screenshot}
     */
    public function recharge(): Response
    {
        WalletService::checkEnabled();

        $amount = (float)$this->request->post('amount', 0);
        $txHash = trim((string)$this->request->post('tx_hash', ''));

        $minRecharge = (float)WalletSetting::getValue('min_recharge', '10');
        if ($amount < $minRecharge) {
            return $this->error(ErrorCode::WALLET_AMOUNT_TOO_SMALL, "最低充值 {$minRecharge} 爱心币");
        }

        if (empty($txHash)) {
            return $this->error(ErrorCode::PARAM_MISSING, '请填写交易Hash');
        }

        // 上传截图
        $screenshotUrl = '';
        $file = $this->request->file('screenshot');
        if ($file) {
            $result = UploadService::uploadImage($file);
            $screenshotUrl = $result['url'] ?? '';
        }

        $order = new RechargeOrder();
        $order->user_id        = $this->getUserId();
        $order->amount         = $amount;
        $order->tx_hash        = $txHash;
        $order->screenshot_url = $screenshotUrl;
        $order->status         = RechargeOrder::STATUS_PENDING;
        $order->save();

        return $this->success($order, '充值申请已提交，等待审核');
    }

    /**
     * 我的充值记录
     * GET /api/wallet/recharge/list
     */
    public function rechargeList(): Response
    {
        $page = max(1, (int)$this->request->get('page', 1));

        $list = RechargeOrder::where('user_id', $this->getUserId())
            ->order('created_at', 'desc')
            ->paginate(['list_rows' => 20, 'page' => $page]);

        return $this->successPage([
            'list'      => $list->items(),
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ]);
    }

    /**
     * 提交提现申请
     * POST /api/wallet/withdraw  {amount, wallet_address, chain_type}
     */
    public function withdraw(): Response
    {
        $amount  = (float)$this->request->post('amount', 0);
        $address = trim((string)$this->request->post('wallet_address', ''));
        $chain   = trim((string)$this->request->post('chain_type', 'TRC20'));

        if (empty($address)) {
            return $this->error(ErrorCode::PARAM_MISSING, '请填写钱包地址');
        }

        if (!in_array($chain, ['TRC20', 'ERC20'])) {
            return $this->error(ErrorCode::PARAM_FORMAT_ERROR, '不支持的链类型');
        }

        $order = WalletService::requestWithdrawal($this->getUserId(), $amount, $address, $chain);

        return $this->success($order, '提现申请已提交，等待审核');
    }

    /**
     * 我的提现记录
     * GET /api/wallet/withdraw/list
     */
    public function withdrawList(): Response
    {
        $page = max(1, (int)$this->request->get('page', 1));

        $list = WithdrawalOrder::where('user_id', $this->getUserId())
            ->order('created_at', 'desc')
            ->paginate(['list_rows' => 20, 'page' => $page]);

        return $this->successPage([
            'list'      => $list->items(),
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ]);
    }

    /**
     * 捐赠启事发布者
     * POST /api/wallet/donate  {post_id, amount, message?, is_anonymous?}
     */
    public function donate(): Response
    {
        $postId    = (int)$this->request->post('post_id', 0);
        $amount    = (float)$this->request->post('amount', 0);
        $message   = trim((string)$this->request->post('message', ''));
        $anonymous = (bool)$this->request->post('is_anonymous', false);

        if ($postId <= 0 || $amount <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $donation = WalletService::donate($this->getUserId(), $postId, $amount, $message, $anonymous);

        return $this->success($donation, '捐赠成功');
    }

    /**
     * 购买启事置顶
     * POST /api/wallet/boost  {post_id, hours}
     */
    public function boost(): Response
    {
        $postId = (int)$this->request->post('post_id', 0);
        $hours  = (int)$this->request->post('hours', 0);

        if ($postId <= 0 || $hours <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $boost = WalletService::boostPost($this->getUserId(), $postId, $hours);

        return $this->success([
            'boost_id'  => $boost->id,
            'start_at'  => $boost->start_at,
            'expire_at' => $boost->expire_at,
            'total_cost' => (float)$boost->total_cost,
        ], '置顶成功');
    }

    /**
     * Apple IAP 充值
     * POST /api/wallet/iap-recharge  {receipt_data, product_id}
     */
    public function iapRecharge(): Response
    {
        WalletService::checkEnabled();

        $receiptData = trim((string)$this->request->post('receipt_data', ''));
        $productId   = trim((string)$this->request->post('product_id', ''));

        if (empty($receiptData) || empty($productId)) {
            return $this->error(ErrorCode::PARAM_MISSING, '缺少收据或产品ID');
        }

        // 收据大小限制（防滥用）
        if (strlen($receiptData) > 50000) {
            return $this->error(ErrorCode::PARAM_FORMAT_ERROR, '收据数据过大');
        }

        if (!IapProducts::isValid($productId)) {
            return $this->error(ErrorCode::PARAM_FORMAT_ERROR, '无效的产品ID');
        }

        // 验证 Apple 收据
        $verified = AppleIapService::verify($receiptData, $productId);
        $transactionId = $verified['original_transaction_id'];
        $coins         = $verified['coins'];

        $userId = $this->getUserId();

        // 幂等检查 + 创建订单在同一事务内，避免竞态
        try {
            $result = Db::transaction(function () use ($userId, $coins, $productId, $transactionId, $receiptData) {
                // 事务内幂等检查（防并发）
                $existing = RechargeOrder::where('iap_transaction_id', $transactionId)->find();
                if ($existing && $existing->status === RechargeOrder::STATUS_APPROVED) {
                    return ['existing' => true, 'order' => $existing, 'coins' => (int)$existing->amount];
                }

                $order = new RechargeOrder();
                $order->user_id            = $userId;
                $order->amount             = $coins;
                $order->payment_type       = 1; // Apple IAP
                $order->iap_product_id     = $productId;
                $order->iap_transaction_id = $transactionId;
                $order->iap_receipt        = $receiptData;
                $order->status             = RechargeOrder::STATUS_APPROVED;
                $order->save();

                // 直接调用 credit 避免嵌套事务
                WalletService::iapCredit($userId, (float)$coins, $order->id);

                return ['existing' => false, 'order' => $order, 'coins' => $coins];
            });
        } catch (\Throwable $e) {
            // UNIQUE 约束冲突兜底：并发请求可能同时通过幂等检查
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'uk_iap_transaction')) {
                $existing = RechargeOrder::where('iap_transaction_id', $transactionId)->find();
                if ($existing) {
                    return $this->success([
                        'coins'    => (int)$existing->amount,
                        'order_id' => $existing->id,
                    ], '充值已到账');
                }
            }
            throw $e;
        }

        $msg = $result['existing'] ? '充值已到账' : '充值成功';
        return $this->success([
            'coins'    => $result['coins'],
            'order_id' => $result['order']->id,
        ], $msg);
    }

    /**
     * 查询启事是否有活跃置顶
     * GET /api/wallet/boost/active?post_id=
     */
    public function boostActive(): Response
    {
        $postId = (int)$this->request->get('post_id', 0);

        $boost = PostBoost::where('post_id', $postId)->active()->find();

        return $this->success([
            'is_boosted' => $boost ? true : false,
            'expire_at'  => $boost ? $boost->expire_at : null,
        ]);
    }
}
