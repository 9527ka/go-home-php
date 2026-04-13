<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\model\Group as GroupModel;
use app\common\model\GroupMember;
use think\Request;
use think\Response;
use think\facade\Db;

/**
 * 管理后台 — 聊天监控与管控
 *
 * 只读查询：
 *   GET /admin/chat/private        私聊消息分页查询（按发送者/接收者/关键词/时间）
 *   GET /admin/chat/group          群聊消息分页查询（按群/发送者/关键词/时间）
 *   GET /admin/chat/groups         群组列表（含封禁/禁言状态，供管控）
 *
 * 管控：
 *   POST /admin/chat/group/ban     封禁/解禁群（整群）
 *   POST /admin/chat/group/mute    全员禁言开关
 *   POST /admin/chat/member/mute   在某群内禁言某成员（Task 5 复用）
 *   （用户全局禁言复用 /admin/user/status：status=2 禁言 / 3 封禁）
 */
class ChatManage
{
    /**
     * 私聊消息列表
     * GET /admin/chat/private
     * 参数：page, limit, from_id, to_id, keyword, start_at, end_at
     */
    public function privateList(Request $request): Response
    {
        $page    = max(1, (int)$request->get('page', 1));
        $limit   = min(100, max(1, (int)$request->get('limit', 20)));
        $fromId  = (int)$request->get('from_id', 0);
        $toId    = (int)$request->get('to_id', 0);
        $keyword = trim((string)$request->get('keyword', ''));
        $startAt = trim((string)$request->get('start_at', ''));
        $endAt   = trim((string)$request->get('end_at', ''));

        $query = Db::name('private_messages')->order('id', 'desc');
        if ($fromId > 0)             $query->where('from_id', $fromId);
        if ($toId > 0)               $query->where('to_id', $toId);
        if ($keyword !== '')         $query->where('content', 'like', '%' . $keyword . '%');
        if ($startAt !== '')         $query->where('created_at', '>=', $startAt);
        if ($endAt !== '')           $query->where('created_at', '<=', $endAt);

        $total = (clone $query)->count();
        $rows  = $query->page($page, $limit)->select()->toArray();

        // 批量补充用户昵称
        $userIds = [];
        foreach ($rows as $r) {
            $userIds[] = (int)$r['from_id'];
            $userIds[] = (int)$r['to_id'];
        }
        $userMap = $this->fetchUserMap(array_unique(array_filter($userIds)));

        foreach ($rows as &$r) {
            $r['from_user'] = $userMap[(int)$r['from_id']] ?? null;
            $r['to_user']   = $userMap[(int)$r['to_id']] ?? null;
        }
        unset($r);

        return json([
            'code' => 0,
            'data' => [
                'list'  => $rows,
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * 群聊消息列表
     * GET /admin/chat/group
     * 参数：page, limit, group_id, user_id, keyword, start_at, end_at
     */
    public function groupList(Request $request): Response
    {
        $page    = max(1, (int)$request->get('page', 1));
        $limit   = min(100, max(1, (int)$request->get('limit', 20)));
        $groupId = (int)$request->get('group_id', 0);
        $userId  = (int)$request->get('user_id', 0);
        $keyword = trim((string)$request->get('keyword', ''));
        $startAt = trim((string)$request->get('start_at', ''));
        $endAt   = trim((string)$request->get('end_at', ''));

        $query = Db::name('group_messages')->order('id', 'desc');
        if ($groupId > 0)    $query->where('group_id', $groupId);
        if ($userId > 0)     $query->where('user_id', $userId);
        if ($keyword !== '') $query->where('content', 'like', '%' . $keyword . '%');
        if ($startAt !== '') $query->where('created_at', '>=', $startAt);
        if ($endAt !== '')   $query->where('created_at', '<=', $endAt);

        $total = (clone $query)->count();
        $rows  = $query->page($page, $limit)->select()->toArray();

        $userIds  = array_unique(array_filter(array_map(fn($r) => (int)$r['user_id'], $rows)));
        $groupIds = array_unique(array_filter(array_map(fn($r) => (int)$r['group_id'], $rows)));

        $userMap  = $this->fetchUserMap($userIds);
        $groupMap = empty($groupIds) ? [] : Db::name('groups')
            ->whereIn('id', $groupIds)
            ->column('name', 'id');

        foreach ($rows as &$r) {
            $r['user']       = $userMap[(int)$r['user_id']] ?? null;
            $r['group_name'] = $groupMap[(int)$r['group_id']] ?? '';
        }
        unset($r);

        return json([
            'code' => 0,
            'data' => [
                'list'  => $rows,
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * 群组列表（管控用）
     * GET /admin/chat/groups
     * 参数：page, limit, keyword, banned
     */
    public function groups(Request $request): Response
    {
        $page    = max(1, (int)$request->get('page', 1));
        $limit   = min(100, max(1, (int)$request->get('limit', 20)));
        $keyword = trim((string)$request->get('keyword', ''));
        $banned  = $request->get('banned', '');

        $query = GroupModel::order('id', 'desc');
        if ($keyword !== '')    $query->where('name', 'like', '%' . $keyword . '%');
        if ($banned !== '')     $query->where('banned', (int)$banned);

        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)->select()->toArray();

        return json([
            'code' => 0,
            'data' => [
                'list'  => $list,
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * 封禁/解禁群
     * POST /admin/chat/group/ban
     * body: group_id, banned (0/1)
     */
    public function toggleGroupBan(Request $request): Response
    {
        $groupId = (int)$request->post('group_id', 0);
        $banned  = (int)$request->post('banned', 0) === 1 ? 1 : 0;

        if ($groupId <= 0) {
            return json(['code' => 400, 'msg' => '参数缺失']);
        }

        $group = GroupModel::find($groupId);
        if (!$group) return json(['code' => 404, 'msg' => '群组不存在']);

        $group->banned = $banned;
        $group->save();

        return json(['code' => 0, 'msg' => $banned ? '已封禁' : '已解禁']);
    }

    /**
     * 全员禁言开关
     * POST /admin/chat/group/mute
     * body: group_id, all_muted (0/1)
     */
    public function toggleGroupAllMute(Request $request): Response
    {
        $groupId  = (int)$request->post('group_id', 0);
        $allMuted = (int)$request->post('all_muted', 0) === 1 ? 1 : 0;

        if ($groupId <= 0) {
            return json(['code' => 400, 'msg' => '参数缺失']);
        }

        $group = GroupModel::find($groupId);
        if (!$group) return json(['code' => 404, 'msg' => '群组不存在']);

        $group->all_muted = $allMuted;
        $group->save();

        return json(['code' => 0, 'msg' => $allMuted ? '已开启全员禁言' : '已关闭全员禁言']);
    }

    /**
     * 群内禁言某成员（管理后台）
     * POST /admin/chat/member/mute
     * body: group_id, user_id, minutes (0=解除禁言；-1=永久)
     */
    public function muteMember(Request $request): Response
    {
        $groupId = (int)$request->post('group_id', 0);
        $userId  = (int)$request->post('user_id', 0);
        $minutes = (int)$request->post('minutes', 0);

        if ($groupId <= 0 || $userId <= 0) {
            return json(['code' => 400, 'msg' => '参数缺失']);
        }

        $member = GroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->find();
        if (!$member) return json(['code' => 404, 'msg' => '该用户不是群成员']);

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

        return json(['code' => 0, 'msg' => $msg]);
    }

    // ---- 私有辅助 ----

    private function fetchUserMap(array $userIds): array
    {
        if (empty($userIds)) return [];
        $users = Db::name('users')
            ->whereIn('id', $userIds)
            ->field('id, nickname, avatar, user_code')
            ->select()
            ->toArray();
        $map = [];
        foreach ($users as $u) {
            $map[(int)$u['id']] = $u;
        }
        return $map;
    }
}
