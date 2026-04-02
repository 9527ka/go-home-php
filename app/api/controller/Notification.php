<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\model\Notification as NotificationModel;
use app\common\service\NotifyService;
use think\Response;

class Notification extends BaseApi
{
    /**
     * 通知列表
     * GET /api/notification/list
     *
     * @query page int 页码
     */
    public function list(): Response
    {
        $userId = $this->getUserId();
        $page = max(1, (int)$this->request->get('page', 1));

        $list = NotificationModel::where('user_id', $userId)
            ->order('is_read', 'asc')
            ->order('created_at', 'desc')
            ->paginate(20, false, ['page' => $page]);

        return $this->successPage([
            'list'         => $list->items(),
            'page'         => $list->currentPage(),
            'page_size'    => $list->listRows(),
            'total'        => $list->total(),
            'last_page'    => $list->lastPage(),
            'unread_count' => NotifyService::getUnreadCount($userId),
        ]);
    }

    /**
     * 标记已读
     * POST /api/notification/read
     *
     * @body id int 通知ID(不传则全部标记已读)
     */
    public function read(): Response
    {
        $userId = $this->getUserId();
        $id = $this->request->post('id');

        NotifyService::markAsRead($userId, $id ? (int)$id : null);

        return $this->success(null, '已标记为已读');
    }

    /**
     * 删除全部已读通知
     * POST /api/notification/deleteAll
     */
    public function deleteAll(): Response
    {
        $userId = $this->getUserId();

        $deleted = NotificationModel::where('user_id', $userId)
            ->where('is_read', 1)
            ->delete();

        return $this->success(['deleted_count' => (int)$deleted], '已清空');
    }

    /**
     * 未读数量
     * GET /api/notification/unread
     */
    public function unread(): Response
    {
        $count = NotifyService::getUnreadCount($this->getUserId());

        return $this->success(['count' => $count]);
    }
}
