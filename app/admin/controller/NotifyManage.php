<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\model\Notification;
use app\common\service\NotifyService;
use think\Request;
use think\Response;

/**
 * 管理后台 - 通知管理
 */
class NotifyManage
{
    /**
     * 发送系统通知
     * POST /admin/notify/send
     */
    public function send(Request $request): Response
    {
        $userId = (int)$request->post('user_id', 0); // 0 表示发送给所有用户 (需业务支持，当前 NotifyService.send 为单发)
        $title = trim($request->post('title', ''));
        $content = trim($request->post('content', ''));
        $type = (int)$request->post('type', Notification::TYPE_SYSTEM);

        if (!$title || !$content) {
            return json(['code' => 400, 'msg' => '标题和内容不能为空']);
        }

        if ($userId > 0) {
            NotifyService::send($userId, $type, $title, $content);
        } else {
            // 这里可以实现群发逻辑，暂且作为预留
            return json(['code' => 400, 'msg' => '目前仅支持单发通知']);
        }

        return json(['code' => 0, 'msg' => '发送成功']);
    }
}
