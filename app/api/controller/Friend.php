<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\FriendRequest;
use app\common\model\Friendship;
use app\common\model\User;
use think\Response;

class Friend extends BaseApi
{
    /**
     * 搜索用户（仅用户编号或昵称 — App Store Guideline 5.1.1 合规，移除手机号搜索）
     * GET /api/friend/search?keyword=xxx
     */
    public function search(): Response
    {
        $keyword = trim((string)$this->request->get('keyword', ''));
        if (empty($keyword)) {
            return $this->success(['list' => []]);
        }

        $userId = $this->getUserId();

        // 仅按 user_code 精确匹配 / 昵称模糊匹配（不再支持手机号搜索）
        $users = User::where('id', '<>', $userId)
            ->where('status', 1)
            ->where(function ($q) use ($keyword) {
                $q->whereOr('user_code', $keyword)
                  ->whereOr('nickname', 'like', "%{$keyword}%");
            })
            ->field('id,nickname,avatar,user_code')
            ->limit(20)
            ->select()
            ->toArray();

        return $this->success(['list' => $users]);
    }

    /**
     * 发送好友请求
     * POST /api/friend/request
     *
     * @body to_id   int    目标用户ID
     * @body message string 验证消息(可选)
     */
    public function request(): Response
    {
        $userId = $this->getUserId();
        $toId = (int)$this->request->post('to_id', 0);
        $message = trim((string)$this->request->post('message', ''));

        if ($toId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING, '缺少目标用户');
        }

        if ($toId === $userId) {
            return $this->error(ErrorCode::FRIEND_SELF, '不能添加自己为好友');
        }

        // 目标用户是否存在
        $targetUser = User::where('id', $toId)->where('status', 1)->find();
        if (!$targetUser) {
            return $this->error(ErrorCode::AUTH_ACCOUNT_NOT_FOUND, '用户不存在');
        }

        // 是否已是好友
        $exists = Friendship::where('user_id', $userId)
            ->where('friend_id', $toId)
            ->find();
        if ($exists) {
            return $this->error(ErrorCode::FRIEND_ALREADY, '已经是好友了');
        }

        // 是否有未处理的请求
        $pending = FriendRequest::where('from_id', $userId)
            ->where('to_id', $toId)
            ->where('status', 0)
            ->find();
        if ($pending) {
            return $this->error(ErrorCode::FRIEND_REQUEST_PENDING, '已发送过请求，请等待对方处理');
        }

        // 对方是否给自己发了请求（自动互加）
        $reverse = FriendRequest::where('from_id', $toId)
            ->where('to_id', $userId)
            ->where('status', 0)
            ->find();
        if ($reverse) {
            // 自动接受
            $reverse->status = 1;
            $reverse->save();
            $this->createFriendship($userId, $toId);
            return $this->success(null, '已自动添加为好友');
        }

        // XSS 净化
        if (!empty($message)) {
            $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }

        FriendRequest::create([
            'from_id' => $userId,
            'to_id'   => $toId,
            'message' => mb_substr($message, 0, 200),
            'status'  => 0,
        ]);

        // WebSocket 推送通知给对方
        $this->pushFriendRequest($toId, $userId);

        return $this->success(null, '请求已发送');
    }

    /**
     * 获取收到的好友请求列表
     * GET /api/friend/requests
     */
    public function requests(): Response
    {
        $userId = $this->getUserId();

        $list = FriendRequest::with(['fromUser'])
            ->where('to_id', $userId)
            ->where('status', 0)
            ->order('created_at', 'desc')
            ->limit(50)
            ->select()
            ->toArray();

        // 扁平化发送者信息，前端期望的格式
        $result = array_map(function ($item) {
            return [
                'id'            => $item['id'],
                'from_id'       => $item['from_id'],
                'to_id'         => $item['to_id'],
                'message'       => $item['message'],
                'status'        => $item['status'],
                'created_at'    => $item['created_at'],
                'from_nickname' => $item['from_user']['nickname'] ?? '',
                'from_avatar'   => $item['from_user']['avatar'] ?? '',
            ];
        }, $list);

        return $this->success(['list' => $result]);
    }

    /**
     * 获取待处理请求数量
     * GET /api/friend/request-count
     */
    public function requestCount(): Response
    {
        $userId = $this->getUserId();

        $count = FriendRequest::where('to_id', $userId)
            ->where('status', 0)
            ->count();

        return $this->success(['count' => $count]);
    }

    /**
     * 接受好友请求
     * POST /api/friend/accept
     *
     * @body request_id int 请求ID
     */
    public function accept(): Response
    {
        $userId = $this->getUserId();
        $requestId = (int)$this->request->post('request_id', 0);

        if ($requestId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $req = FriendRequest::where('id', $requestId)
            ->where('to_id', $userId)
            ->where('status', 0)
            ->find();

        if (!$req) {
            return $this->error(ErrorCode::FRIEND_REQUEST_NOT_FOUND, '请求不存在或已处理');
        }

        // 更新请求状态
        $req->status = 1;
        $req->save();

        // 创建双向好友关系
        $this->createFriendship($userId, (int)$req->from_id);

        // WebSocket 推送通知给对方（好友请求被接受）
        $this->pushFriendAccepted((int)$req->from_id, $userId);

        return $this->success(null, '已添加好友');
    }

    /**
     * 拒绝好友请求
     * POST /api/friend/reject
     *
     * @body request_id int 请求ID
     */
    public function reject(): Response
    {
        $userId = $this->getUserId();
        $requestId = (int)$this->request->post('request_id', 0);

        if ($requestId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $req = FriendRequest::where('id', $requestId)
            ->where('to_id', $userId)
            ->where('status', 0)
            ->find();

        if (!$req) {
            return $this->error(ErrorCode::FRIEND_REQUEST_NOT_FOUND, '请求不存在或已处理');
        }

        $req->status = 2;
        $req->save();

        return $this->success(null, '已拒绝');
    }

    /**
     * 获取好友列表
     * GET /api/friend/list
     */
    public function list(): Response
    {
        $userId = $this->getUserId();

        $friendships = Friendship::with(['friend'])
            ->where('user_id', $userId)
            ->order('created_at', 'desc')
            ->select()
            ->toArray();

        // 扁平化，前端期望: {id, friend_id/user_id, nickname, avatar, account, remark, created_at}
        $list = array_map(function ($item) {
            return [
                'id'         => $item['id'],
                'friend_id'  => $item['friend_id'],
                'user_id'    => $item['friend_id'],
                'nickname'   => $item['friend']['nickname'] ?? '',
                'avatar'     => $item['friend']['avatar'] ?? '',
                'account'    => $item['friend']['account'] ?? '',
                'user_code'  => $item['friend']['user_code'] ?? '',
                'remark'     => $item['remark'],
                'created_at' => $item['created_at'],
            ];
        }, $friendships);

        return $this->success(['list' => $list]);
    }

    /**
     * 删除好友
     * POST /api/friend/remove
     *
     * @body friend_id int 好友用户ID
     */
    public function remove(): Response
    {
        $userId = $this->getUserId();
        $friendId = (int)$this->request->post('friend_id', 0);

        if ($friendId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        // 删除双向关系
        Friendship::where('user_id', $userId)->where('friend_id', $friendId)->delete();
        Friendship::where('user_id', $friendId)->where('friend_id', $userId)->delete();

        return $this->success(null, '已删除好友');
    }

    /**
     * 创建双向好友关系
     */
    private function createFriendship(int $userA, int $userB): void
    {
        $now = date('Y-m-d H:i:s');

        // 防止重复
        if (!Friendship::where('user_id', $userA)->where('friend_id', $userB)->find()) {
            Friendship::create([
                'user_id'    => $userA,
                'friend_id'  => $userB,
                'created_at' => $now,
            ]);
        }
        if (!Friendship::where('user_id', $userB)->where('friend_id', $userA)->find()) {
            Friendship::create([
                'user_id'    => $userB,
                'friend_id'  => $userA,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * 推送好友请求通知（WebSocket）
     * @param int $toUserId 接收方用户 ID
     * @param int $fromUserId 发送方用户 ID
     */
    private function pushFriendRequest(int $toUserId, int $fromUserId): void
    {
        try {
            // 获取发送方用户信息
            $fromUser = User::where('id', $fromUserId)
                ->field('id,nickname,avatar')
                ->find();

            if (!$fromUser) {
                return;
            }

            // 通过 Workerman 内部通信推送（需要 WebSocket 服务运行）
            $data = json_encode([
                'type' => 'friend_request',
                'from_user_id' => $fromUserId,
                'from_nickname' => $fromUser['nickname'] ?? '',
                'from_avatar' => $fromUser['avatar'] ?? '',
                'timestamp' => time(),
            ], JSON_UNESCAPED_UNICODE);

            // 使用 AsyncTcpConnection 向 WebSocket 服务发送指令
            // 由于当前架构是独立的 WebSocket 服务，这里使用内部端口通信
            $this->sendToWebSocket($toUserId, $data);
        } catch (\Exception $e) {
            // 推送失败不影响主流程
            trace('[Friend] pushFriendRequest error: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * 推送好友请求被接受通知（WebSocket）
     * @param int $toUserId 接收方用户 ID（请求发送方）
     * @param int $acceptedUserId 接受方用户 ID
     */
    private function pushFriendAccepted(int $toUserId, int $acceptedUserId): void
    {
        try {
            $data = json_encode([
                'type' => 'friend_accepted',
                'accepted_user_id' => $acceptedUserId,
                'timestamp' => time(),
            ], JSON_UNESCAPED_UNICODE);

            $this->sendToWebSocket($toUserId, $data);
        } catch (\Exception $e) {
            trace('[Friend] pushFriendAccepted error: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * 向 WebSocket 服务发送消息
     * 使用 fsockopen 直接连接到 WebSocket 内部端口
     */
    private function sendToWebSocket(int $userId, string $data): void
    {
        // 方案1: 使用内部 TCP 端口通信（需要 WebSocket 服务开启内部端口）
        // 方案2: 使用 Redis Pub/Sub
        // 方案3: 直接调用 AsyncTcpConnection
        
        // 这里使用简单方案: 尝试连接内部端口 (127.0.0.1:7272)
        // 如果 WebSocket 服务未开启内部端口，此方法静默失败
        try {
            $internalHost = '127.0.0.1';
            $internalPort = 7272; // WebSocket 内部通信端口
            
            $socket = @fsockopen($internalHost, $internalPort, $errno, $errstr, 1);
            if ($socket) {
                $message = json_encode([
                    'cmd' => 'send_to_user',
                    'user_id' => $userId,
                    'data' => $data,
                ]);
                fwrite($socket, $message . "\n");
                fclose($socket);
            }
        } catch (\Exception $e) {
            // 静默失败，不影响主流程
        }
    }
}
