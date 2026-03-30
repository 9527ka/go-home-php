<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\model\AdminAuditLog;
use app\common\model\RechargeOrder;
use app\common\model\RedPacket;
use app\common\model\RedPacketClaim;
use app\common\model\WalletSetting;
use app\common\model\WalletTransaction;
use app\common\model\WithdrawalOrder;
use app\common\service\WalletService;
use think\facade\Log;
use think\Request;
use think\Response;

class WalletManage
{
    // ========== 充值管理 ==========

    /**
     * 充值订单列表
     * GET /admin/wallet/recharge/list?status=&page=
     */
    public function rechargeList(Request $request): Response
    {
        $page   = max(1, (int)$request->get('page', 1));
        $status = $request->get('status');

        $query = RechargeOrder::with(['user'])
            ->order('created_at', 'desc');

        if (!is_null($status) && $status !== '') {
            $query->where('status', (int)$status);
        }

        $list = $query->paginate(['list_rows' => 20, 'page' => $page]);

        return json(['code' => 0, 'msg' => 'ok', 'data' => $list->toArray()]);
    }

    /**
     * 审核通过充值
     * POST /admin/wallet/recharge/approve  {order_id}
     */
    public function rechargeApprove(Request $request): Response
    {
        $orderId = (int)$request->post('order_id', 0);
        $order = RechargeOrder::find($orderId);

        if (!$order || $order->status !== RechargeOrder::STATUS_PENDING) {
            return json(['code' => ErrorCode::PARAM_FORMAT_ERROR, 'msg' => '订单不存在或已处理']);
        }

        WalletService::recharge($order->user_id, (float)$order->amount, $order->id);

        $order->status       = RechargeOrder::STATUS_APPROVED;
        $order->admin_id     = $request->adminId;
        $order->processed_at = date('Y-m-d H:i:s');
        $order->save();

        AdminAuditLog::log($request->adminId, 'recharge_approve', 'recharge_order', $order->id, json_encode([
            'user_id' => $order->user_id,
            'amount'  => (float)$order->amount,
        ], JSON_UNESCAPED_UNICODE), $request->ip());

        Log::info("Recharge approved: order#{$orderId}, amount={$order->amount}");

        return json(['code' => 0, 'msg' => '充值已通过']);
    }

    /**
     * 拒绝充值
     * POST /admin/wallet/recharge/reject  {order_id, remark?}
     */
    public function rechargeReject(Request $request): Response
    {
        $orderId = (int)$request->post('order_id', 0);
        $remark  = trim((string)$request->post('remark', ''));
        $order   = RechargeOrder::find($orderId);

        if (!$order || $order->status !== RechargeOrder::STATUS_PENDING) {
            return json(['code' => ErrorCode::PARAM_FORMAT_ERROR, 'msg' => '订单不存在或已处理']);
        }

        $order->status       = RechargeOrder::STATUS_REJECTED;
        $order->admin_id     = $request->adminId;
        $order->admin_remark = $remark;
        $order->processed_at = date('Y-m-d H:i:s');
        $order->save();

        AdminAuditLog::log($request->adminId, 'recharge_reject', 'recharge_order', $order->id, json_encode([
            'user_id' => $order->user_id,
            'remark'  => $remark,
        ], JSON_UNESCAPED_UNICODE), $request->ip());

        return json(['code' => 0, 'msg' => '充值已拒绝']);
    }

    // ========== 提现管理 ==========

    /**
     * 提现订单列表
     * GET /admin/wallet/withdrawal/list?status=&page=
     */
    public function withdrawalList(Request $request): Response
    {
        $page   = max(1, (int)$request->get('page', 1));
        $status = $request->get('status');

        $query = WithdrawalOrder::with(['user'])
            ->order('created_at', 'desc');

        if (!is_null($status) && $status !== '') {
            $query->where('status', (int)$status);
        }

        $list = $query->paginate(['list_rows' => 20, 'page' => $page]);

        return json(['code' => 0, 'msg' => 'ok', 'data' => $list->toArray()]);
    }

    /**
     * 通过提现
     * POST /admin/wallet/withdrawal/approve  {order_id, tx_hash?}
     */
    public function withdrawalApprove(Request $request): Response
    {
        $orderId = (int)$request->post('order_id', 0);
        $txHash  = trim((string)$request->post('tx_hash', ''));

        WalletService::approveWithdrawal($orderId, $request->adminId, $txHash);

        AdminAuditLog::log($request->adminId, 'withdrawal_approve', 'withdrawal_order', $orderId, json_encode([
            'tx_hash' => $txHash,
        ], JSON_UNESCAPED_UNICODE), $request->ip());

        return json(['code' => 0, 'msg' => '提现已通过']);
    }

    /**
     * 拒绝提现
     * POST /admin/wallet/withdrawal/reject  {order_id, remark?}
     */
    public function withdrawalReject(Request $request): Response
    {
        $orderId = (int)$request->post('order_id', 0);
        $remark  = trim((string)$request->post('remark', ''));

        WalletService::rejectWithdrawal($orderId, $request->adminId, $remark);

        AdminAuditLog::log($request->adminId, 'withdrawal_reject', 'withdrawal_order', $orderId, json_encode([
            'remark' => $remark,
        ], JSON_UNESCAPED_UNICODE), $request->ip());

        return json(['code' => 0, 'msg' => '提现已拒绝，余额已退回']);
    }

    // ========== 流水 ==========

    /**
     * 全局流水
     * GET /admin/wallet/transactions?user_id=&type=&page=
     */
    public function transactions(Request $request): Response
    {
        $page   = max(1, (int)$request->get('page', 1));
        $userId = $request->get('user_id');
        $type   = $request->get('type');

        $query = WalletTransaction::with(['user'])
            ->order('created_at', 'desc');

        if (!is_null($userId) && $userId !== '') {
            $query->where('user_id', (int)$userId);
        }
        if (!is_null($type) && $type !== '') {
            $query->where('type', (int)$type);
        }

        $list = $query->paginate(['list_rows' => 20, 'page' => $page]);

        return json(['code' => 0, 'msg' => 'ok', 'data' => $list->toArray()]);
    }

    // ========== 红包记录 ==========

    /**
     * 红包发送记录
     * GET /admin/wallet/red-packet/list?page=
     */
    public function redPacketList(Request $request): Response
    {
        $page = max(1, (int)$request->get('page', 1));

        $list = RedPacket::with(['user'])
            ->order('created_at', 'desc')
            ->paginate(['list_rows' => 20, 'page' => $page]);

        return json(['code' => 0, 'msg' => 'ok', 'data' => $list->toArray()]);
    }

    /**
     * 红包领取记录
     * GET /admin/wallet/red-packet/claims?red_packet_id=&page=
     */
    public function redPacketClaims(Request $request): Response
    {
        $page        = max(1, (int)$request->get('page', 1));
        $redPacketId = $request->get('red_packet_id');

        $query = RedPacketClaim::with(['user'])
            ->order('created_at', 'desc');

        if (!is_null($redPacketId) && $redPacketId !== '') {
            $query->where('red_packet_id', (int)$redPacketId);
        }

        $list = $query->paginate(['list_rows' => 20, 'page' => $page]);

        return json(['code' => 0, 'msg' => 'ok', 'data' => $list->toArray()]);
    }

    // ========== 配置管理 ==========

    /**
     * 获取所有钱包配置
     * GET /admin/wallet/settings
     */
    public function settings(): Response
    {
        return json(['code' => 0, 'msg' => 'ok', 'data' => WalletSetting::getAll()]);
    }

    /**
     * 更新配置
     * POST /admin/wallet/settings/update  {key, value}
     */
    public function settingsUpdate(Request $request): Response
    {
        $key   = trim((string)$request->post('key', ''));
        $value = trim((string)$request->post('value', ''));

        if (empty($key)) {
            return json(['code' => ErrorCode::PARAM_MISSING, 'msg' => '缺少参数']);
        }

        WalletSetting::setValue($key, $value);

        AdminAuditLog::log($request->adminId, 'wallet_settings_update', 'wallet_settings', 0, json_encode([
            'key'   => $key,
            'value' => $value,
        ], JSON_UNESCAPED_UNICODE), $request->ip());

        return json(['code' => 0, 'msg' => '配置已更新']);
    }
}
