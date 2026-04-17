<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\validate\PostValidate;
use app\common\enum\ErrorCode;
use app\common\enum\PostCategory;
use app\common\service\LocationService;
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
     * @body name          string 标题
     * @body appearance    string 体貌特征(≥10字)
     * @body description   string 补充描述
     * @body lost_at       string 走失时间 Y-m-d H:i:s
     * @body lost_province string 省
     * @body lost_city     string 市(必填)
     * @body lost_district string 区
     * @body lost_address  string 详细地址
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

        // 未指定分类时，默认只显示当前可见分类（宠物+物品），排除审核要求隐藏的亲人/儿童类
        if (!isset($params['category']) || $params['category'] === '') {
            $params['category'] = implode(',', [PostCategory::ELDER, PostCategory::PET, PostCategory::OTHER]);
        }

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
     * 附近启事
     * GET /api/post/nearby?lat=&lng=&radius=&page=
     * lat/lng 可选：未传则用用户上次定位（GPS 或 IP 兜底）
     */
    public function nearby(): Response
    {
        $lat = $this->request->get('lat');
        $lng = $this->request->get('lng');
        $radius = (float)$this->request->get('radius', 50);
        $page = max(1, (int)$this->request->get('page', 1));
        $pageSize = min(50, max(1, (int)$this->request->get('page_size', 20)));

        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            $loc = LocationService::getUserLocation($this->getUserId());
            if ($loc['lat'] === null || $loc['lng'] === null) {
                return $this->error(ErrorCode::PARAM_MISSING, '缺少经纬度且未授权定位');
            }
            $lat = $loc['lat'];
            $lng = $loc['lng'];
        }

        $data = PostService::getNearby((float)$lat, (float)$lng, $radius, $page, $pageSize);
        return $this->successPage($data);
    }

    /**
     * 上报用户 GPS 位置
     * POST /api/user/location   { lat, lng }
     */
    public function updateLocation(): Response
    {
        $lat = $this->request->post('lat');
        $lng = $this->request->post('lng');
        if ($lat === null || $lng === null) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }
        LocationService::updateFromGps($this->getUserId(), (float)$lat, (float)$lng);
        return $this->success(null, '位置已更新');
    }

    /**
     * 查询当前用户定位
     * GET /api/user/location
     */
    public function myLocation(): Response
    {
        return $this->success(LocationService::getUserLocation($this->getUserId()));
    }

    /**
     * 编辑启事（仅待审核/被驳回状态可编辑）
     * POST /api/post/update
     *
     * @header Authorization Bearer <token>
     * @body id            int    启事ID（必填）
     * @body name          string 标题
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
