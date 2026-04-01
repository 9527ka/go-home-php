<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\PostBoost;
use app\common\model\RechargeOrder;
use app\common\model\WalletSetting;
use app\common\model\WalletTransaction;
use app\common\model\WithdrawalOrder;
use app\common\service\UploadService;
use app\common\service\WalletService;
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
                'min_recharge'       => (float)WalletSetting::getValue('min_recharge', '10'),
                'min_withdrawal'     => (float)WalletSetting::getValue('min_withdrawal', '20'),
                'withdrawal_fee_rate' => (float)WalletSetting::getValue('withdrawal_fee_rate', '0'),
                'boost_hourly_rate'  => (float)WalletSetting::getValue('boost_hourly_rate', '10'),
                'min_donation'       => (float)WalletSetting::getValue('min_donation', '1'),
                'max_red_packet_amount' => (float)WalletSetting::getValue('max_red_packet_amount', '500'),
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

        return $this->successPage($list->toArray());
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
            return $this->error(ErrorCode::WALLET_AMOUNT_TOO_SMALL, "最低充值 {$minRecharge} USDT");
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

        return $this->successPage($list->toArray());
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

        return $this->successPage($list->toArray());
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
