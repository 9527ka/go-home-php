<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\Feedback as FeedbackModel;
use think\Response;

class Feedback extends BaseApi
{
    /**
     * 提交反馈
     * POST /api/feedback/create
     *
     * @body content string 反馈内容(必填, 最长1000字)
     * @body contact string 联系方式(可选, 最长100字)
     */
    public function create(): Response
    {
        $userId  = $this->getUserId();
        $content = mb_substr(
            htmlspecialchars(trim($this->request->post('content', '')), ENT_QUOTES, 'UTF-8'),
            0, 1000
        );
        $contact = mb_substr(
            htmlspecialchars(trim($this->request->post('contact', '')), ENT_QUOTES, 'UTF-8'),
            0, 100
        );

        if (empty($content)) {
            return $this->error(ErrorCode::PARAM_MISSING, '请输入反馈内容');
        }

        if (mb_strlen($content) < 5) {
            return $this->error(ErrorCode::FEEDBACK_TOO_SHORT);
        }

        $feedback = new FeedbackModel();
        $feedback->user_id    = $userId;
        $feedback->content    = $content;
        $feedback->contact    = $contact;
        $feedback->status     = FeedbackModel::STATUS_PENDING;
        $feedback->save();

        return $this->success(null, '感谢您的反馈，我们会尽快处理');
    }
}
