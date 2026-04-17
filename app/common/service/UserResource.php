<?php
declare(strict_types=1);

namespace app\common\service;

/**
 * 用户资源扩展
 *
 * 统一给 user 对象 / user_id 对象附加 vip 快照，
 * 避免在每个接口里重复调用 VipService，避免 N+1。
 *
 * 用法示例：
 *   // 1. 批量处理 list 中每项的 user 子对象：
 *   UserResource::attachVipInList($items, 'user');
 *
 *   // 2. 批量处理 list 中每项的 user_id（消息/红包场景）
 *   UserResource::attachVipByUserIdKey($items, 'user_id', 'sender_vip');
 *
 *   // 3. 单个用户对象
 *   UserResource::attachVipSingle($user);
 */
class UserResource
{
    /**
     * 给列表中每项的「嵌套 user 子对象」附加 vip 快照
     *
     *   [{ id:.., user: { id, nickname, avatar } }, ...]
     * 变成
     *   [{ id:.., user: { id, nickname, avatar, vip: {...}|null } }, ...]
     *
     * @param array &$items     待处理列表（引用传递，原地修改）
     * @param string $userKey   嵌套 user 字段名，默认 'user'
     * @param string $idKey     user 子对象中 id 字段名，默认 'id'
     */
    public static function attachVipInList(array &$items, string $userKey = 'user', string $idKey = 'id'): void
    {
        if (empty($items)) return;
        $userIds = [];
        foreach ($items as $item) {
            $u = $item[$userKey] ?? null;
            if (is_array($u) && !empty($u[$idKey])) {
                $userIds[] = (int)$u[$idKey];
            }
        }
        // 即使 userIds 为空也要给嵌套 user 写 vip=null（前端不用兼容 undefined）
        $snaps = empty($userIds)
            ? []
            : VipService::getVipSnapshots(array_values(array_unique($userIds)));
        foreach ($items as &$item) {
            if (isset($item[$userKey]) && is_array($item[$userKey])) {
                $uid = (int)($item[$userKey][$idKey] ?? 0);
                $item[$userKey]['vip'] = ($uid > 0 && isset($snaps[$uid])) ? $snaps[$uid] : null;
            }
        }
        unset($item);
    }

    /**
     * 给列表中每项按 user_id 字段查询 vip 并附加到指定 key
     * 适用消息、红包等场景（user 信息不是嵌套对象）
     */
    public static function attachVipByUserIdKey(array &$items, string $userIdKey = 'user_id', string $vipKey = 'vip'): void
    {
        if (empty($items)) return;
        $userIds = [];
        foreach ($items as $item) {
            if (!empty($item[$userIdKey])) {
                $userIds[] = (int)$item[$userIdKey];
            }
        }
        if (empty($userIds)) return;
        $snaps = VipService::getVipSnapshots(array_values(array_unique($userIds)));
        foreach ($items as &$item) {
            if (!empty($item[$userIdKey])) {
                $uid = (int)$item[$userIdKey];
                $item[$vipKey] = $snaps[$uid] ?? null;
            }
        }
        unset($item);
    }

    /**
     * 为单个 user 数组附加 vip 字段（原地修改）
     */
    public static function attachVipSingle(array &$user, string $idKey = 'id'): void
    {
        if (empty($user[$idKey])) return;
        $user['vip'] = VipService::getVipSnapshot((int)$user[$idKey]);
    }

    /**
     * 批量处理多个嵌套 key：一次查询，多字段附加
     * 如 donations 同时含 from_user 和 to_user 两个嵌套
     *
     * @param array $items
     * @param array $nestedKeys  ['from_user', 'to_user']
     * @param string $idKey
     */
    public static function attachVipInListMulti(array &$items, array $nestedKeys, string $idKey = 'id'): void
    {
        if (empty($items) || empty($nestedKeys)) return;
        $userIds = [];
        foreach ($items as $item) {
            foreach ($nestedKeys as $k) {
                if (isset($item[$k]) && is_array($item[$k]) && !empty($item[$k][$idKey])) {
                    $userIds[] = (int)$item[$k][$idKey];
                }
            }
        }
        if (empty($userIds)) return;
        $snaps = VipService::getVipSnapshots(array_values(array_unique($userIds)));
        foreach ($items as &$item) {
            foreach ($nestedKeys as $k) {
                if (isset($item[$k]) && is_array($item[$k]) && !empty($item[$k][$idKey])) {
                    $uid = (int)$item[$k][$idKey];
                    $item[$k]['vip'] = $snaps[$uid] ?? null;
                }
            }
        }
        unset($item);
    }
}
