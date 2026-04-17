<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\model\AdminAuditLog;
use app\common\service\FoundStoryService;
use think\Request;
use think\Response;

class FoundStoryManage
{
    /**
     * 列表
     * GET /admin/found-story/list?status=&page=
     */
    public function list(Request $request): Response
    {
        $status = $request->get('status');
        $status = ($status === null || $status === '') ? null : (int)$status;
        $page = max(1, (int)$request->get('page', 1));
        return json(['code' => 0, 'msg' => 'ok', 'data' => FoundStoryService::adminList($status, $page)]);
    }

    /**
     * 通过
     * POST /admin/found-story/approve  {id, remark?}
     */
    public function approve(Request $request): Response
    {
        $id = (int)$request->post('id', 0);
        if ($id <= 0) return json(['code' => ErrorCode::PARAM_MISSING, 'msg' => '参数缺失']);
        FoundStoryService::approve($id, (int)$request->adminId, (string)$request->post('remark', ''));
        AdminAuditLog::log($request->adminId, 'found_story_approve', 'found_story', $id, null, $request->ip());
        return json(['code' => 0, 'msg' => '已通过']);
    }

    /**
     * 驳回
     * POST /admin/found-story/reject  {id, remark}
     */
    public function reject(Request $request): Response
    {
        $id = (int)$request->post('id', 0);
        $remark = (string)$request->post('remark', '');
        if ($id <= 0) return json(['code' => ErrorCode::PARAM_MISSING, 'msg' => '参数缺失']);
        FoundStoryService::reject($id, (int)$request->adminId, $remark);
        AdminAuditLog::log($request->adminId, 'found_story_reject', 'found_story', $id,
            json_encode(['remark' => $remark], JSON_UNESCAPED_UNICODE), $request->ip());
        return json(['code' => 0, 'msg' => '已驳回']);
    }
}
