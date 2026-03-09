<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\validate\PostValidate;
use app\common\service\PostService;
use think\Response;

class Post extends BaseApi
{
    /**
     * 发布启事
     * POST /api/post/create
     *
     * @header Authorization Bearer <token>
     * @body category      int    1=宠物 2=亲人 3=儿童 4=其它物品
     * @body name          string 名字/称呼
     * @body gender        int    0=未知 1=男 2=女
     * @body age           string 年龄描述
     * @body species       string 宠物品种(仅宠物类)
     * @body appearance    string 体貌特征(≥10字)
     * @body description   string 补充描述
     * @body lost_at       string 走失时间 Y-m-d H:i:s
     * @body lost_province string 省
     * @body lost_city     string 市(必填)
     * @body lost_district string 区
     * @body lost_address  string 详细地址
     * @body contact_name  string 联系人
     * @body contact_phone string 联系电话(必填)
     * @body images        array  图片URL列表(≤9张)
     */
    public function create(): Response
    {
        $params = $this->request->post();

        // 参数校验
        validate(PostValidate::class)->scene('create')->check($params);

        $images = $params['images'] ?? [];
        unset($params['images']);

        $post = PostService::create($this->getUserId(), $params, $images);

        return $this->success([
            'id'     => $post->id,
            'status' => $post->status,
            'msg'    => '发布成功，请等待审核。审核通过后将公开展示。',
        ], '发布成功');
    }

    /**
     * 启事列表
     * GET /api/post/list
     *
     * @query page      int    页码(默认1)
     * @query page_size int    每页条数(默认20,最大50)
     * @query category  string 分类筛选，支持逗号分隔多值：1/2/3/4 或 "1,4"
     * @query city      string 城市筛选
     * @query keyword   string 关键词搜索
     * @query days      int    时间范围 1/3/7/30
     */
    public function list(): Response
    {
        $params = $this->request->get();

        $userId = $this->getUserId();
        $data = PostService::getList($params, $userId ?: null);

        return $this->successPage($data);
    }

    /**
     * 启事详情
     * GET /api/post/detail
     *
     * @query id int 启事ID
     */
    public function detail(): Response
    {
        $id = (int)$this->request->get('id', 0);

        if ($id <= 0) {
            return $this->error(2001, '缺少启事ID');
        }

        $data = PostService::getDetail($id, $this->getUserId());

        return $this->success($data);
    }

    /**
     * 我的启事列表
     * GET /api/post/mine
     */
    public function mine(): Response
    {
        $params = $this->request->get();
        $data = PostService::getMine($this->getUserId(), $params);

        return $this->successPage($data);
    }

    /**
     * 编辑启事（仅待审核/被驳回状态可编辑）
     * POST /api/post/update
     *
     * @header Authorization Bearer <token>
     * @body id            int    启事ID（必填）
     * @body name          string 名字/称呼
     * @body appearance    string 体貌特征
     * @body ...           其他可编辑字段同 create
     * @body images        array  新图片URL列表（可选，提供则替换全部图片）
     */
    public function update(): Response
    {
        $params = $this->request->post();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->error(2001, '缺少启事ID');
        }

        validate(PostValidate::class)->scene('update')->check($params);

        $images = $params['images'] ?? null;
        unset($params['images'], $params['id']);

        $post = PostService::update($id, $this->getUserId(), $params, $images);

        return $this->success([
            'id'     => $post->id,
            'status' => $post->status,
            'msg'    => '修改成功，已重新提交审核。',
        ], '修改成功');
    }

    /**
     * 更新启事状态 (已找到/关闭)
     * POST /api/post/updateStatus
     *
     * @body id     int 启事ID
     * @body status int 新状态 2=已找到 3=已关闭
     */
    public function updateStatus(): Response
    {
        $id = (int)$this->request->post('id', 0);
        $status = (int)$this->request->post('status', 0);

        $post = PostService::updateStatus($id, $this->getUserId(), $status);

        return $this->success([
            'id'     => $post->id,
            'status' => $post->status,
        ], '状态更新成功');
    }
}
