<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use app\common\model\Report;
use think\Request;
use think\Response;

class ReportManage
{
    /**
     * 举报列表（按优先级排序：待处理优先）
     * GET /admin/report/list
     */
    public function list(Request $request): Response
    {
        $page = max(1, (int)$request->get('page', 1));
        $status = $request->get('status');

        $query = Report::with(['reporter'])
            ->order('status', 'asc')  // 待处理排前面
            ->order('created_at', 'desc');

        if (!is_null($status)) {
            $query->where('status', (int)$status);
        }

        $list = $query->paginate(20, false, ['page' => $page]);

        return json([
            'code' => 0,
            'data' => [
                'list'      => $list->items(),
                'page'      => $list->currentPage(),
                'page_size' => $list->listRows(),
                'total'     => $list->total(),
            ],
        ]);
    }

    /**
     * 处理举报
     * POST /admin/report/handle
     *
     * @body id     int    举报ID
     * @body status int    1=有效 2=无效 3=忽略
     * @body remark string 处理备注
     */
    public function handle(Request $request): Response
    {
        $id = (int)$request->post('id', 0);
        $status = (int)$request->post('status', 0);
        $remark = trim($request->post('remark', ''));
        $adminId = (int)($request->adminId ?? 0);

        if (!in_array($status, [1, 2, 3])) {
            return json(['code' => ErrorCode::PARAM_FORMAT_ERROR, 'msg' => '无效的处理结果']);
        }

        $report = Report::find($id);
        if (!$report) {
            return json(['code' => ErrorCode::POST_NOT_FOUND, 'msg' => '举报记录不存在']);
        }

        $report->status = $status;
        $report->handled_by = $adminId;
        $report->handle_remark = htmlspecialchars($remark, ENT_QUOTES, 'UTF-8');
        $report->handled_at = date('Y-m-d H:i:s');
        $report->save();

        return json(['code' => 0, 'msg' => '处理成功']);
    }
}
