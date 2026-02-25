<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\model\ChatMessage;
use think\Response;

class Chat extends BaseApi
{
    /**
     * 获取聊天历史记录
     * GET /api/chat/history
     *
     * @query before_id int 加载此ID之前的消息（分页用）
     * @query limit     int 条数(默认50, 最大100)
     */
    public function history(): Response
    {
        $beforeId = (int)$this->request->get('before_id', 0);
        $limit = min(100, max(1, (int)$this->request->get('limit', 50)));

        $query = ChatMessage::with(['user'])
            ->order('id', 'desc');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->limit($limit)->select()->toArray();

        // 反转使消息按时间正序
        $messages = array_reverse($messages);

        return $this->success([
            'list'     => $messages,
            'has_more' => count($messages) === $limit,
        ]);
    }
}
