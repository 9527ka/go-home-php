<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use app\common\model\Report as ReportModel;
use app\common\service\TelegramService;
use think\Response;

class Report extends BaseApi
{
    /**
     * 提交举报
     * POST /api/report/create
     *
     * @body target_type int    1=启事 2=线索 3=用户
     * @body target_id   int    被举报对象ID
     * @body reason      int    1=虚假 2=广告 3=违法 4=骚扰 5=其他
     * @body description string 补充说明(可选)
     */
    public function create(): Response
    {
        $userId     = $this->getUserId();
        $targetType = (int)$this->request->post('target_type', 0);
        $targetId   = (int)$this->request->post('target_id', 0);
        $reason     = (int)$this->request->post('reason', 0);
        $desc       = mb_substr(htmlspecialchars(trim($this->request->post('description', '')), ENT_QUOTES, 'UTF-8'), 0, 500);

        // 参数校验
        if (!in_array($targetType, [1, 2, 3])) {
            return $this->error(ErrorCode::PARAM_FORMAT_ERROR, '举报类型无效');
        }
        if ($targetId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING, '缺少举报目标');
        }
        if (!in_array($reason, [1, 2, 3, 4, 5])) {
            return $this->error(ErrorCode::PARAM_FORMAT_ERROR, '请选择举报原因');
        }

        // 防止重复举报
        $exists = ReportModel::where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->find();

        if ($exists) {
            throw new BusinessException(ErrorCode::REPORT_DUPLICATE);
        }

        $report = new ReportModel();
        $report->user_id     = $userId;
        $report->target_type = $targetType;
        $report->target_id   = $targetId;
        $report->reason      = $reason;
        $report->description = $desc;
        $report->status      = 0;
        $report->created_at  = date('Y-m-d H:i:s');
        $report->save();

        // Telegram 通知管理员
        TelegramService::notifyNewReport($report);

        return $this->success(null, '举报已提交，我们将尽快处理');
    }
}
