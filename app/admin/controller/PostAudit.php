<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\enum\PostStatus;
use app\common\exception\BusinessException;
use app\common\model\AdminAuditLog;
use app\common\model\Post;
use app\common\service\NotifyService;
use think\facade\Log;
use think\Request;
use think\Response;

class PostAudit
{
    /**
     * 待审核列表
     * GET /admin/audit/list
     */
    public function list(Request $request): Response
    {
        $page = max(1, (int)$request->get('page', 1));
        $status = $request->get('status'); // 可选: 按状态筛选

        $query = Post::with(['images', 'user'])
            ->order('created_at', 'desc');

        if (!is_null($status) && PostStatus::isValid((int)$status)) {
            $query->where('status', (int)$status);
        } else {
            // 默认显示待审核
            $query->where('status', PostStatus::PENDING);
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
     * 审核通过
     * POST /admin/audit/approve
     *
     * @body id int 启事ID
     */
    public function approve(Request $request): Response
    {
        $id = (int)$request->post('id', 0);
        $adminId = (int)($request->adminId ?? 0);

        $post = Post::find($id);
        if (!$post) {
            throw new BusinessException(ErrorCode::POST_NOT_FOUND);
        }

        if ($post->status !== PostStatus::PENDING) {
            throw new BusinessException(ErrorCode::PARAM_VALIDATE_FAIL, '该启事当前状态无法审核');
        }

        $post->status = PostStatus::ACTIVE;
        $post->audited_by = $adminId;
        $post->audited_at = date('Y-m-d H:i:s');
        $post->save();

        // 通知用户
        NotifyService::notifyAuditPass($post->user_id, $post->id, $post->name);

        // 审计日志
        AdminAuditLog::log($adminId, AdminAuditLog::ACTION_APPROVE, 'post', $id, null, $request->ip());

        Log::info("Post approved: id={$id}, admin={$adminId}");

        return json(['code' => 0, 'msg' => '审核通过']);
    }

    /**
     * 审核驳回
     * POST /admin/audit/reject
     *
     * @body id     int    启事ID
     * @body remark string 驳回原因
     */
    public function reject(Request $request): Response
    {
        $id = (int)$request->post('id', 0);
        $remark = trim($request->post('remark', ''));
        $adminId = (int)($request->adminId ?? 0);

        if (empty($remark)) {
            return json(['code' => ErrorCode::PARAM_MISSING, 'msg' => '请填写驳回原因']);
        }

        $post = Post::find($id);
        if (!$post) {
            throw new BusinessException(ErrorCode::POST_NOT_FOUND);
        }

        $post->status = PostStatus::REJECTED;
        $post->audit_remark = htmlspecialchars($remark, ENT_QUOTES, 'UTF-8');
        $post->audited_by = $adminId;
        $post->audited_at = date('Y-m-d H:i:s');
        $post->save();

        // 通知用户
        NotifyService::notifyAuditReject($post->user_id, $post->id, $post->name, $remark);

        // 审计日志
        AdminAuditLog::log($adminId, AdminAuditLog::ACTION_REJECT, 'post', $id, json_encode(['remark' => $remark], JSON_UNESCAPED_UNICODE), $request->ip());

        Log::info("Post rejected: id={$id}, admin={$adminId}, reason={$remark}");

        return json(['code' => 0, 'msg' => '已驳回']);
    }

    /**
     * 一键下架
     * POST /admin/audit/takedown
     *
     * @body id     int    启事ID
     * @body remark string 下架原因
     */
    public function takedown(Request $request): Response
    {
        $id = (int)$request->post('id', 0);
        $remark = trim($request->post('remark', ''));
        $adminId = (int)($request->adminId ?? 0);

        $post = Post::find($id);
        if (!$post) {
            throw new BusinessException(ErrorCode::POST_NOT_FOUND);
        }

        $post->status = PostStatus::CLOSED;
        $post->audit_remark = htmlspecialchars($remark, ENT_QUOTES, 'UTF-8');
        $post->audited_by = $adminId;
        $post->audited_at = date('Y-m-d H:i:s');
        $post->save();

        NotifyService::send(
            $post->user_id,
            \app\common\model\Notification::TYPE_SYSTEM,
            '您的启事已被下架',
            "您发布的「{$post->name}」因违反平台规定已被下架。原因：{$remark}",
            $post->id
        );

        // 审计日志
        AdminAuditLog::log($adminId, AdminAuditLog::ACTION_TAKEDOWN, 'post', $id, json_encode(['remark' => $remark], JSON_UNESCAPED_UNICODE), $request->ip());

        Log::info("Post taken down: id={$id}, admin={$adminId}");

        return json(['code' => 0, 'msg' => '已下架']);
    }
}
