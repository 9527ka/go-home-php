<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\Friendship;
use app\common\model\Group as GroupModel;
use app\common\model\GroupMember;
use app\common\model\GroupMessage;
use app\common\model\User;
use app\common\service\GroupSystemMessageService;
use app\common\service\UserResource;
use think\facade\Db;
use think\Response;

class Group extends BaseApi
{
    /**
     * 创建群组
     * POST /api/group/create
     *
     * @body name        string   群名
     * @body avatar      string   头像(可选)
     * @body description string   简介(可选)
     * @body member_ids  int[]    初始成员ID列表
     */
    public function create(): Response
    {
        $userId = $this->getUserId();
        $name = trim((string)$this->request->post('name', ''));
        $avatar = trim((string)$this->request->post('avatar', ''));
        $description = trim((string)$this->request->post('description', ''));
        $memberIds = $this->request->post('member_ids', []);
        if (is_array($memberIds)) {
            $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        }

        if (empty($name)) {
            return $this->error(ErrorCode::PARAM_MISSING, '请输入群名');
        }

        // XSS 净化
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

        // 创建群组
        $group = GroupModel::create([
            'name'         => mb_substr($name, 0, 50),
            'avatar'       => $avatar,
            'description'  => mb_substr($description, 0, 500),
            'owner_id'     => $userId,
            'max_members'  => 100,
            'member_count' => 1,
            'status'       => 1,
        ]);

        // 群主加入
        GroupMember::create([
            'group_id'  => $group->id,
            'user_id'   => $userId,
            'role'      => 2, // 群主
            'joined_at' => date('Y-m-d H:i:s'),
        ]);

        // 邀请初始成员（仅限好友）
        $addedIds = [];
        if (is_array($memberIds) && !empty($memberIds)) {
            $count = 1;
            foreach ($memberIds as $memberId) {
                $memberId = (int)$memberId;
                if ($memberId <= 0 || $memberId === $userId) continue;

                // 验证是否为好友
                $isFriend = Friendship::where('user_id', $userId)
                    ->where('friend_id', $memberId)
                    ->find();
                if (!$isFriend) continue;

                // 防止重复
                $exists = GroupMember::where('group_id', $group->id)
                    ->where('user_id', $memberId)
                    ->find();
                if ($exists) continue;

                try {
                    GroupMember::create([
                        'group_id'  => $group->id,
                        'user_id'   => $memberId,
                        'role'      => 0,
                        'joined_at' => date('Y-m-d H:i:s'),
                    ]);
                    $count++;
                    $addedIds[] = $memberId;
                } catch (\Exception $e) {
                    // 唯一键冲突等异常，跳过
                    continue;
                }
            }
            $group->member_count = $count;
            $group->save();
        }

        // 系统通知：群主邀请了 X、Y、Z 加入群聊
        if (!empty($addedIds)) {
            $ownerName = $this->resolveNickname($userId);
            $names = $this->resolveNicknames($addedIds);
            GroupSystemMessageService::send(
                (int)$group->id,
                $ownerName . ' 邀请了 ' . implode('、', $names) . ' 加入群聊'
            );
        }

        return $this->success($group->toArray());
    }

    /**
     * 我的群组列表
     * GET /api/group/list
     */
    public function list(): Response
    {
        $userId = $this->getUserId();

        // 通过成员表关联查找活跃群组
        $memberRecords = GroupMember::with(['group'])
            ->where('user_id', $userId)
            ->select()
            ->toArray();

        $list = [];
        foreach ($memberRecords as $m) {
            $group = $m['group'] ?? null;
            if ($group && ($group['status'] ?? 0) === 1) {
                $list[] = $group;
            }
        }

        return $this->success(['list' => $list]);
    }

    /**
     * 群组详情（含成员列表）
     * GET /api/group/detail?group_id=xxx
     */
    public function detail(): Response
    {
        $userId = $this->getUserId();
        $groupId = (int)$this->request->get('group_id', 0);

        if ($groupId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $group = GroupModel::find($groupId);
        if (!$group || !$group->isActive()) {
            return $this->error(ErrorCode::GROUP_NOT_FOUND, '群组不存在');
        }

        // 验证是否为成员
        $isMember = GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->find();
        if (!$isMember) {
            return $this->error(ErrorCode::GROUP_NOT_MEMBER, '您不是该群成员');
        }

        // 获取成员列表
        $members = GroupMember::with(['user'])
            ->where('group_id', $groupId)
            ->order('role', 'desc')
            ->order('joined_at', 'asc')
            ->select()
            ->toArray();

        $memberList = array_map(function ($m) {
            return [
                'id'        => $m['id'],
                'user_id'   => $m['user_id'],
                'nickname'  => $m['user']['nickname'] ?? '',
                'avatar'    => $m['user']['avatar'] ?? '',
                'user_code' => $m['user']['user_code'] ?? '',
                'alias'     => $m['alias'] ?? '',
                'role'      => $m['role'],
                'joined_at' => $m['joined_at'],
            ];
        }, $members);

        UserResource::attachVipByUserIdKey($memberList, 'user_id', 'vip');

        return $this->success([
            'group'   => $group->toArray(),
            'members' => $memberList,
        ]);
    }

    /**
     * 更新群组信息（仅群主/管理员）
     * POST /api/group/update
     *
     * @body group_id    int
     * @body name        string
     * @body avatar      string
     * @body description string
     */
    public function update(): Response
    {
        $userId = $this->getUserId();
        $groupId = (int)$this->request->post('group_id', 0);

        if ($groupId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $group = GroupModel::find($groupId);
        if (!$group || !$group->isActive()) {
            return $this->error(ErrorCode::GROUP_NOT_FOUND);
        }

        // 验证权限（群主或管理员）
        $member = GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->find();
        if (!$member || !$member->isAdmin()) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION, '无权修改群信息');
        }

        $allow = ['name', 'avatar', 'description', 'announcement'];
        $data = $this->request->only($allow, 'post');

        // 记录原始值（用于系统消息，避免 htmlspecialchars 实体出现在提示文案中）
        $rawName = isset($data['name']) ? trim((string)$data['name']) : null;
        $rawAnn  = isset($data['announcement']) ? trim((string)$data['announcement']) : null;
        $oldName = (string)$group->name;
        $oldAnn  = (string)($group->announcement ?? '');

        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
                }
            }
            $group->save($data);
        }

        // 系统通知：群名 / 群公告变动（avatar、description 不发）
        // 注意：比较用 escaped 后的值（DB 存储的也是 escaped 的），但消息文案用原始值
        $operatorName = $this->resolveNickname($userId);
        if ($rawName !== null && $data['name'] !== $oldName && $data['name'] !== '') {
            GroupSystemMessageService::send(
                $groupId,
                $operatorName . ' 将群名修改为 "' . $rawName . '"'
            );
        }
        if ($rawAnn !== null && $data['announcement'] !== $oldAnn) {
            if ($rawAnn === '') {
                $content = $operatorName . ' 清空了群公告';
            } else {
                $preview = mb_substr($rawAnn, 0, 50);
                if (mb_strlen($rawAnn) > 50) $preview .= '…';
                $content = $operatorName . " 更新了群公告：\n" . $preview;
            }
            GroupSystemMessageService::send($groupId, $content);
        }

        return $this->success($group->toArray(), '更新成功');
    }

    /**
     * 邀请好友入群
     * POST /api/group/invite
     *
     * @body group_id int
     * @body user_ids int[]
     */
    public function invite(): Response
    {
        $userId = $this->getUserId();
        $groupId = (int)$this->request->post('group_id', 0);
        $userIds = $this->request->post('user_ids', []);

        if ($groupId <= 0 || !is_array($userIds) || empty($userIds)) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $group = GroupModel::find($groupId);
        if (!$group || !$group->isActive()) {
            return $this->error(ErrorCode::GROUP_NOT_FOUND);
        }

        // 验证邀请者是成员
        $inviter = GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->find();
        if (!$inviter) {
            return $this->error(ErrorCode::GROUP_NOT_MEMBER);
        }

        $added = 0;
        $addedIds = [];
        foreach ($userIds as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) continue;

            // 验证是否为好友
            $isFriend = Friendship::where('user_id', $userId)
                ->where('friend_id', $uid)
                ->find();
            if (!$isFriend) continue;

            // 检查人数上限
            if ($group->member_count >= $group->max_members) break;

            // 防止重复
            $exists = GroupMember::where('group_id', $groupId)
                ->where('user_id', $uid)
                ->find();
            if ($exists) continue;

            GroupMember::create([
                'group_id'  => $groupId,
                'user_id'   => $uid,
                'role'      => 0,
                'joined_at' => date('Y-m-d H:i:s'),
            ]);
            $added++;
            $addedIds[] = $uid;
        }

        // 更新人数
        if ($added > 0) {
            $group->member_count = GroupMember::where('group_id', $groupId)->count();
            $group->save();

            // 系统通知：邀请者 邀请了 X、Y、Z 加入群聊
            $inviterName = $this->resolveNickname($userId);
            $names = $this->resolveNicknames($addedIds);
            GroupSystemMessageService::send(
                $groupId,
                $inviterName . ' 邀请了 ' . implode('、', $names) . ' 加入群聊'
            );
        }

        return $this->success(null, "已邀请 {$added} 人入群");
    }

    /**
     * 退出群组
     * POST /api/group/leave
     *
     * @body group_id int
     */
    public function leave(): Response
    {
        $userId = $this->getUserId();
        $groupId = (int)$this->request->post('group_id', 0);

        if ($groupId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        // 公共聊天室（id=1）不允许退出
        if ($groupId === 1) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION, '公共聊天室不允许退出');
        }

        $group = GroupModel::find($groupId);
        if (!$group) {
            return $this->error(ErrorCode::GROUP_NOT_FOUND);
        }

        // 群主不能退出，需要解散或转让
        if ((int)$group->owner_id === $userId) {
            return $this->error(ErrorCode::GROUP_OWNER_CANNOT_LEAVE, '群主不能退出，请先解散群组');
        }

        GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->delete();

        // 更新人数
        $group->member_count = GroupMember::where('group_id', $groupId)->count();
        $group->save();

        // 系统通知：XXX 退出了群聊
        GroupSystemMessageService::send(
            $groupId,
            $this->resolveNickname($userId) . ' 退出了群聊'
        );

        return $this->success(null, '已退出群组');
    }

    /**
     * 踢出成员（仅群主/管理员）
     * POST /api/group/kick
     *
     * @body group_id int
     * @body user_id  int
     */
    public function kick(): Response
    {
        $userId = $this->getUserId();
        $groupId = (int)$this->request->post('group_id', 0);
        $targetId = (int)$this->request->post('user_id', 0);

        if ($groupId <= 0 || $targetId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $group = GroupModel::find($groupId);
        if (!$group || !$group->isActive()) {
            return $this->error(ErrorCode::GROUP_NOT_FOUND);
        }

        // 验证操作者权限
        $operator = GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->find();
        if (!$operator || !$operator->isAdmin()) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION);
        }

        // 不能踢群主
        if ($targetId === (int)$group->owner_id) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION, '不能踢出群主');
        }

        GroupMember::where('group_id', $groupId)
            ->where('user_id', $targetId)
            ->delete();

        $group->member_count = GroupMember::where('group_id', $groupId)->count();
        $group->save();

        // 系统通知：操作者 将 被踢者 移出了群聊
        GroupSystemMessageService::send(
            $groupId,
            $this->resolveNickname($userId) . ' 将 ' . $this->resolveNickname($targetId) . ' 移出了群聊'
        );

        return $this->success(null, '已踢出');
    }

    /**
     * 设置我在本群的昵称（别名）
     * POST /api/group/set-alias
     *
     * @body group_id int
     * @body alias    string
     */
    public function setAlias(): Response
    {
        $userId = $this->getUserId();
        $groupId = (int)$this->request->post('group_id', 0);
        $alias = trim((string)$this->request->post('alias', ''));

        if ($groupId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }
        if (mb_strlen($alias) > 50) {
            return $this->error(ErrorCode::PARAM_MISSING, '别名过长');
        }

        $member = GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->find();
        if (!$member) {
            return $this->error(ErrorCode::GROUP_NOT_MEMBER);
        }

        $member->alias = htmlspecialchars($alias, ENT_QUOTES, 'UTF-8');
        $member->save();

        return $this->success(null, '已更新');
    }

    /**
     * 设置群成员角色（仅群主）
     * POST /api/group/set-role
     *
     * @body group_id int
     * @body user_id  int
     * @body role     int  0=普通成员 1=管理员
     */
    public function setRole(): Response
    {
        $userId = $this->getUserId();
        $groupId = (int)$this->request->post('group_id', 0);
        $targetId = (int)$this->request->post('user_id', 0);
        $role = (int)$this->request->post('role', 0);

        if ($groupId <= 0 || $targetId <= 0 || !in_array($role, [0, 1])) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $group = GroupModel::find($groupId);
        if (!$group || !$group->isActive()) {
            return $this->error(ErrorCode::GROUP_NOT_FOUND);
        }

        // 仅群主可设置角色
        if ((int)$group->owner_id !== $userId) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION, '仅群主可设置管理员');
        }

        // 不能修改群主自己的角色
        if ($targetId === $userId) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION);
        }

        $member = GroupMember::where('group_id', $groupId)
            ->where('user_id', $targetId)
            ->find();
        if (!$member) {
            return $this->error(ErrorCode::PARAM_MISSING, '该用户不是群成员');
        }

        $oldRole = (int)$member->role;
        $member->role = $role;
        $member->save();

        // 系统通知：设为/取消管理员（role 0=普通 1=管理员；群主 role=2 此处不会经过）
        if ($oldRole !== $role) {
            $targetName = $this->resolveNickname($targetId);
            $content = $role === 1
                ? $targetName . ' 已被设为本群管理员'
                : $targetName . ' 已被取消管理员';
            GroupSystemMessageService::send($groupId, $content);
        }

        return $this->success(null, '角色已更新');
    }

    /**
     * 解散群组（仅群主）
     * POST /api/group/disband
     *
     * @body group_id int
     */
    public function disband(): Response
    {
        $userId = $this->getUserId();
        $groupId = (int)$this->request->post('group_id', 0);

        if ($groupId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        // 公共聊天室（id=1）不允许解散
        if ($groupId === 1) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION, '公共聊天室不允许解散');
        }

        $group = GroupModel::find($groupId);
        if (!$group) {
            return $this->error(ErrorCode::GROUP_NOT_FOUND);
        }

        if ((int)$group->owner_id !== $userId) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION, '仅群主可解散群组');
        }

        // 标记为已解散
        $group->status = 2;
        $group->save();

        // 清除成员
        GroupMember::where('group_id', $groupId)->delete();

        return $this->success(null, '群组已解散');
    }

    /**
     * 群主/管理员禁言群内某成员
     * POST /api/group/mute-member
     *
     * @body group_id int
     * @body user_id  int   被禁言者
     * @body minutes  int   时长（分钟）：0=解除禁言；-1=永久；>0=指定分钟数
     */
    public function muteMember(): Response
    {
        $userId   = $this->getUserId();
        $groupId  = (int)$this->request->post('group_id', 0);
        $targetId = (int)$this->request->post('user_id', 0);
        $minutes  = (int)$this->request->post('minutes', 0);

        if ($groupId <= 0 || $targetId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $group = GroupModel::find($groupId);
        if (!$group || !$group->isActive()) {
            return $this->error(ErrorCode::GROUP_NOT_FOUND);
        }

        // 操作者必须是管理员/群主
        $operator = GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->find();
        if (!$operator || !$operator->isAdmin()) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION);
        }

        // 不能禁言群主
        if ($targetId === (int)$group->owner_id) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION, '不能禁言群主');
        }

        // 自己不能禁言自己
        if ($targetId === $userId) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION);
        }

        // 管理员之间不能互相禁言：仅群主可禁言其他管理员
        if ((int)$group->owner_id !== $userId) {
            $target = GroupMember::where('group_id', $groupId)
                ->where('user_id', $targetId)
                ->find();
            if ($target && $target->isAdmin()) {
                return $this->error(ErrorCode::GROUP_NO_PERMISSION, '管理员之间不能互相禁言');
            }
        }

        $member = GroupMember::where('group_id', $groupId)
            ->where('user_id', $targetId)
            ->find();
        if (!$member) {
            return $this->error(ErrorCode::PARAM_MISSING, '该用户不是群成员');
        }

        if ($minutes === 0) {
            $member->muted_until = null;
            $msg = '已解除禁言';
        } elseif ($minutes < 0) {
            $member->muted_until = '2099-12-31 23:59:59';
            $msg = '已永久禁言';
        } else {
            $member->muted_until = date('Y-m-d H:i:s', time() + $minutes * 60);
            $msg = "已禁言 {$minutes} 分钟";
        }
        $member->save();

        return $this->success([
            'muted_until' => $member->muted_until,
        ], $msg);
    }

    /**
     * 群主/管理员开关"全员禁言"
     * POST /api/group/set-all-muted
     *
     * @body group_id  int
     * @body all_muted int 0=关闭 1=开启
     */
    public function setAllMuted(): Response
    {
        $userId   = $this->getUserId();
        $groupId  = (int)$this->request->post('group_id', 0);
        $allMuted = (int)$this->request->post('all_muted', 0) === 1 ? 1 : 0;

        if ($groupId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $group = GroupModel::find($groupId);
        if (!$group || !$group->isActive()) {
            return $this->error(ErrorCode::GROUP_NOT_FOUND);
        }

        // 操作者必须是管理员/群主
        $operator = GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->find();
        if (!$operator || !$operator->isAdmin()) {
            return $this->error(ErrorCode::GROUP_NO_PERMISSION);
        }

        $group->all_muted = $allMuted;
        $group->save();

        // 实时推送给所有群成员（含管理员自己），客户端据此即时更新输入栏可用性 & 提示横幅
        $memberIds = GroupMember::where('group_id', $groupId)->column('user_id');
        \app\common\service\WsPushService::sendToUsers($memberIds, [
            'type'        => 'group_event',
            'event'       => 'all_muted_changed',
            'group_id'    => $groupId,
            'all_muted'   => $allMuted,
            'operator_id' => $userId,
        ]);

        // 系统通知：开启/关闭全员禁言
        $operatorName = $this->resolveNickname($userId);
        GroupSystemMessageService::send(
            $groupId,
            $operatorName . ($allMuted === 1 ? ' 开启了全员禁言' : ' 关闭了全员禁言')
        );

        return $this->success([
            'all_muted' => $allMuted,
        ], $allMuted === 1 ? '已开启全员禁言' : '已关闭全员禁言');
    }

    /**
     * 生成群邀请 token（用于二维码 / 邀请链接）
     * POST /api/group/invite-token
     *
     * @body group_id int
     * @body ttl      int  有效期（秒），默认 7 天
     */
    public function inviteToken(): Response
    {
        $userId = $this->getUserId();
        $groupId = (int)$this->request->post('group_id', 0);
        $ttl     = (int)$this->request->post('ttl', 7 * 86400);
        if ($ttl < 60) $ttl = 60;
        if ($ttl > 30 * 86400) $ttl = 30 * 86400;

        if ($groupId <= 0) return $this->error(ErrorCode::PARAM_MISSING);

        $group = GroupModel::find($groupId);
        if (!$group || !$group->isActive()) {
            return $this->error(ErrorCode::GROUP_NOT_FOUND);
        }
        if ($group->isBanned()) {
            return $this->error(ErrorCode::CHAT_GROUP_BANNED);
        }

        // 邀请者必须是成员
        $isMember = GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->find();
        if (!$isMember) return $this->error(ErrorCode::GROUP_NOT_MEMBER);

        // 生成 token（32 字符 URL-safe）
        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        Db::name('group_invites')->insert([
            'group_id'   => $groupId,
            'token'      => $token,
            'created_by' => $userId,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->success([
            'token'      => $token,
            'group_id'   => $groupId,
            'expires_at' => $expiresAt,
            'invite_url' => 'gohome://group/invite/' . $token,
        ]);
    }

    /**
     * 通过 token 加入群（扫码 / 邀请链接）
     * POST /api/group/join-by-token
     *
     * @body token string
     */
    public function joinByToken(): Response
    {
        $userId = $this->getUserId();
        $token  = trim((string)$this->request->post('token', ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $invite = Db::name('group_invites')->where('token', $token)->find();
        if (!$invite) return $this->error(ErrorCode::PARAM_VALIDATE_FAIL, '邀请链接无效');
        if (strtotime($invite['expires_at']) < time()) {
            return $this->error(ErrorCode::PARAM_VALIDATE_FAIL, '邀请链接已过期');
        }

        $groupId = (int)$invite['group_id'];
        $group = GroupModel::find($groupId);
        if (!$group || !$group->isActive()) {
            return $this->error(ErrorCode::GROUP_NOT_FOUND);
        }
        if ($group->isBanned()) {
            return $this->error(ErrorCode::CHAT_GROUP_BANNED);
        }

        // 已是成员
        $exists = GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->find();
        if ($exists) {
            return $this->success(['group_id' => $groupId, 'already_member' => true], '已是群成员');
        }

        // 人数检查
        if ($group->member_count >= $group->max_members) {
            return $this->error(ErrorCode::GROUP_FULL);
        }

        GroupMember::create([
            'group_id'  => $groupId,
            'user_id'   => $userId,
            'role'      => 0,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);

        $group->member_count = GroupMember::where('group_id', $groupId)->count();
        $group->save();

        // 系统通知：XXX 通过扫一扫加入了群聊
        GroupSystemMessageService::send(
            $groupId,
            $this->resolveNickname($userId) . ' 通过扫一扫加入了群聊'
        );

        return $this->success(['group_id' => $groupId, 'already_member' => false], '已加入群组');
    }

    /**
     * 获取群消息历史 / 关键词搜索
     * GET /api/group/messages?group_id=xxx&before_id=xxx&limit=50&keyword=xxx
     *
     * - keyword 为空：分页倒序加载历史
     * - keyword 非空：在该群所有文本消息中模糊匹配，支持 before_id 翻页
     */
    public function messages(): Response
    {
        $userId = $this->getUserId();
        $groupId = (int)$this->request->get('group_id', 0);
        $beforeId = (int)$this->request->get('before_id', 0);
        $limit = min(100, max(1, (int)$this->request->get('limit', 50)));
        $keyword = trim((string)$this->request->get('keyword', ''));

        if ($groupId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        // 验证是否为群成员
        $isMember = GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->find();
        if (!$isMember) {
            return $this->error(ErrorCode::GROUP_NOT_MEMBER);
        }

        $query = GroupMessage::with(['user'])
            ->where('group_id', $groupId)
            ->order('id', 'desc');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }
        if ($keyword !== '') {
            // 仅匹配文本消息内容
            $query->where('msg_type', 'text')
                  ->where('content', 'like', '%' . $keyword . '%');
        }

        $messages = $query->limit($limit)->select()->toArray();
        $hasMore = count($messages) === $limit;
        $messages = array_reverse($messages);

        UserResource::attachVipInList($messages, 'user');

        return $this->success([
            'list'     => $messages,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * 查询单个用户昵称，用于系统消息文案拼接。
     * 找不到时返回占位符。
     */
    private function resolveNickname(int $userId): string
    {
        if ($userId <= 0) return '某用户';
        $nick = User::where('id', $userId)->value('nickname');
        return (is_string($nick) && $nick !== '') ? $nick : '某用户';
    }

    /**
     * 批量查询昵称，按传入 id 顺序返回。
     *
     * @param int[] $userIds
     * @return string[]
     */
    private function resolveNicknames(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) return [];

        $map = User::whereIn('id', $userIds)->column('nickname', 'id');
        $out = [];
        foreach ($userIds as $uid) {
            $nick = $map[$uid] ?? '';
            $out[] = ($nick !== '') ? $nick : '某用户';
        }
        return $out;
    }
}
