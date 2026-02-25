<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\model\Language;
use app\common\model\Region;
use think\Request;
use think\Response;

/**
 * 管理后台 - 系统设置
 */
class SystemSettings
{
    /**
     * 语言列表
     * GET /admin/settings/languages
     */
    public function languages(): Response
    {
        $list = Language::order('sort_order', 'asc')->select();
        return json(['code' => 0, 'data' => $list]);
    }

    /**
     * 更新语言状态/排序
     * POST /admin/settings/language/update
     */
    public function updateLanguage(Request $request): Response
    {
        $id = (int)$request->post('id');
        $status = $request->post('status');
        $sortOrder = $request->post('sort_order');

        $lang = Language::find($id);
        if (!$lang) {
            return json(['code' => 404, 'msg' => '记录不存在']);
        }

        if ($status !== null) $lang->status = (int)$status;
        if ($sortOrder !== null) $lang->sort_order = (int)$sortOrder;
        $lang->save();

        return json(['code' => 0, 'msg' => '更新成功']);
    }

    /**
     * 地区列表
     * GET /admin/settings/regions
     */
    public function regions(Request $request): Response
    {
        $parentId = (int)$request->get('parent_id', 0);
        $list = Region::where('parent_id', $parentId)->order('sort_order', 'asc')->select();
        return json(['code' => 0, 'data' => $list]);
    }
}
