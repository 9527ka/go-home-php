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
                'id'    => PostCategory::ELDER,
                'name'  => PostCategory::getName(PostCategory::ELDER),
                'icon'  => 'pets',
            ],
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
            'wallet_enabled'     => WalletSetting::getValue('wallet_enabled') === '1',
            'boost_hourly_rate'  => (float)WalletSetting::getValue('boost_hourly_rate', '10'),
            'banner_enabled'     => WalletSetting::getValue('banner_enabled') === '1',
            'banner_text'        => WalletSetting::getValue('banner_text', ''),
            'banner_link'        => WalletSetting::getValue('banner_link', ''),
            'about' => [
                'version'      => WalletSetting::getValue('about_version', 'v1.0.0'),
                'telegram'     => WalletSetting::getValue('about_telegram', ''),
                'website_url'  => WalletSetting::getValue('about_website_url', ''),
                'website_name' => WalletSetting::getValue('about_website_name', ''),
                'mission'      => WalletSetting::getValue('about_mission', ''),
                'safety'       => WalletSetting::getValue('about_safety', ''),
                'free_service' => WalletSetting::getValue('about_free_service', ''),
                'disclaimer'   => WalletSetting::getValue('about_disclaimer', ''),
                'privacy'      => WalletSetting::getValue('about_privacy', ''),
            ],
        ]);
    }
}
