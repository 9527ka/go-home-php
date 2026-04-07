<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\Friendship;
use app\common\model\Group as GroupModel;
use app\common\model\GroupMember;
use app\common\model\GroupMessage;
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
                } catch (\Exception $e) {
                    // 唯一键冲突等异常，跳过
                    continue;
                }
            }
            $group->member_count = $count;
            $group->save();
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
                'role'      => $m['role'],
                'joined_at' => $m['joined_at'],
            ];
        }, $members);

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

        $allow = ['name', 'avatar', 'description'];
        $data = $this->request->only($allow, 'post');

        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
                }
            }
            $group->save($data);
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
        }

        // 更新人数
        if ($added > 0) {
            $group->member_count = GroupMember::where('group_id', $groupId)->count();
            $group->save();
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

        return $this->success(null, '已踢出');
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
     * 获取群消息历史
     * GET /api/group/messages?group_id=xxx&before_id=xxx&limit=50
     */
    public function messages(): Response
    {
        $userId = $this->getUserId();
        $groupId = (int)$this->request->get('group_id', 0);
        $beforeId = (int)$this->request->get('before_id', 0);
        $limit = min(100, max(1, (int)$this->request->get('limit', 50)));

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

        $messages = $query->limit($limit)->select()->toArray();
        $messages = array_reverse($messages);

        return $this->success([
            'list'     => $messages,
            'has_more' => count($messages) === $limit,
        ]);
    }
}
