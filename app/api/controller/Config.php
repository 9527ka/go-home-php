<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\PostCategory;
use app\common\model\WalletSetting;
use think\Response;

/**
 * App 配置接口
 * GET /api/config/app
 */
class Config extends BaseApi
{
    /**
     * 获取 App 配置（分类可见性等）
     */
    public function app(): Response
    {
        // 当前可见的分类（审核要求隐藏亲人/儿童类型）
        $visibleCategories = [
            [
                'id'    => PostCategory::PET,
                'name'  => PostCategory::getName(PostCategory::PET),
                'icon'  => 'pets',
            ],
            [
                'id'    => PostCategory::OTHER,
                'name'  => PostCategory::getName(PostCategory::OTHER),
                'icon'  => 'inventory_2_outlined',
            ],
        ];

        return $this->success([
            'visible_categories' => $visibleCategories,
            'wallet_enabled'     => WalletSetting::get('wallet_enabled') === '1',
            'boost_hourly_rate'  => (float)WalletSetting::get('boost_hourly_rate', '10'),
        ]);
    }
}
