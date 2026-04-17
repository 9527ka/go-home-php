<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\ErrorCode;
use app\common\enum\PostCategory;
use app\common\enum\PostStatus;
use app\common\exception\BusinessException;
use app\common\model\Notification;
use app\common\model\Post;
use app\common\model\PostImage;
use app\common\model\Like;
use app\common\model\Favorite;
use app\common\model\Donation;
use app\common\model\PostBoost;
use app\common\model\RewardClaim;
use app\common\model\WalletSetting;
use think\facade\Db;
use think\facade\Log;

class PostService
{
    /**
     * 每日发布上限
     */
    const DAILY_PUBLISH_LIMIT = 5;

    /**
     * 创建启事
     *
     * @param int   $userId  发布者ID
     * @param array $data    启事数据
     * @param array $images  图片路径列表
     * @return Post
     */
    public static function create(int $userId, array $data, array $images = []): Post
    {
        // ========== 安全校验 ==========

        // 1. 发布频率限制（含被删除的也计数，防止删了重发绕过）
        $todayCount = Post::where('user_id', $userId)
            ->whereDay('created_at')
            ->count();

        if ($todayCount >= self::DAILY_PUBLISH_LIMIT) {
            throw new BusinessException(ErrorCode::POST_PUBLISH_LIMIT);
        }

        // 2. ⚠️ 未成人保护 — 儿童类别禁止精确地址
        $category = (int)($data['category'] ?? 0);
        if (PostCategory::isMinor($category)) {
            self::validateChildSafety($data);
        }

        // 3. 敏感词基础过滤
        self::filterSensitiveContent($data);

        // 4. ⚠️ 图片路径校验 — 防止伪造路径遍历
        $validImages = self::validateImagePaths($images);

        // ========== 事务写入 ==========
        Db::startTrans();
        try {
            // 创建启事
            $post = new Post();
            $post->user_id       = $userId;
            $post->category      = $category;
            $post->lang          = $data['lang'] ?? 'zh-CN';
            $post->name          = self::sanitize($data['name'] ?? '');
            $post->appearance    = self::sanitize($data['appearance'] ?? '');
            $post->description   = self::sanitize($data['description'] ?? '');
            $post->lost_at       = $data['lost_at'] ?? date('Y-m-d H:i:s');
            $post->lost_province = self::sanitize($data['lost_province'] ?? '');
            $post->lost_city     = self::sanitize($data['lost_city'] ?? '');
            $post->lost_district = self::sanitize($data['lost_district'] ?? '');
            $post->lost_address  = self::sanitize($data['lost_address'] ?? '');

            // ⚠️ 修复：经纬度做数值范围校验
            $lng = $data['lost_longitude'] ?? null;
            $lat = $data['lost_latitude'] ?? null;
            $post->lost_longitude = ($lng !== null && abs((float)$lng) <= 180) ? (float)$lng : null;
            $post->lost_latitude  = ($lat !== null && abs((float)$lat) <= 90) ? (float)$lat : null;

            // 可见性：1=公开 2=仅自己可见
            $post->visibility = in_array((int)($data['visibility'] ?? 1), [1, 2]) ? (int)$data['visibility'] : 1;

            $post->status = PostStatus::PENDING; // ⚠️ 所有发布默认待审核
            $post->save();

            // 悬赏冻结（审核通过前即冻结，防止发布后余额不足）
            $rewardAmount = (float)($data['reward_amount'] ?? 0);
            if ($rewardAmount > 0) {
                WalletService::freezeReward($userId, $post->id, $rewardAmount);
                Db::table('posts')->where('id', $post->id)->update([
                    'reward_amount' => $rewardAmount,
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }

            // 保存图片
            if (!empty($validImages)) {
                $cdnUrl = env('APP_CDN_URL', '');
                $imageData = [];
                foreach ($validImages as $index => $url) {
                    $imageData[] = [
                        'post_id'    => $post->id,
                        'image_url'  => $cdnUrl . $url,
                        'thumb_url'  => self::getThumbUrl($url),
                        'sort_order' => $index,
                        'created_at' => date('Y-m-d H:i:s'),
                    ];
                }
                (new PostImage())->saveAll($imageData);
            }

            Db::commit();

            Log::info("Post created: id={$post->id}, user={$userId}, category={$category}");

            // Telegram 通知管理员
            TelegramService::notifyNewPost($post);

            // 加载关联数据返回
            return Post::with(['images', 'user'])->find($post->id);

        } catch (BusinessException $e) {
            Db::rollback();
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("Post create failed: " . $e->getMessage());
            throw new BusinessException(ErrorCode::DB_ERROR);
        }
    }

    /**
     * 获取启事列表（前台）
     *
     * @param array $params 筛选参数
     * @return array
     */
    public static function getList(array $params, ?int $userId = null): array
    {
        $page     = max(1, (int)($params['page'] ?? 1));
        $pageSize = min(50, max(1, (int)($params['page_size'] ?? 20)));
        $category = $params['category'] ?? null;
        $city     = $params['city'] ?? null;
        $keyword  = $params['keyword'] ?? null;
        $days     = $params['days'] ?? null;

        $now = date('Y-m-d H:i:s');

        // 注意：使用了 alias + leftJoin，不能用 scopeActive，需手动加 posts. 前缀避免字段歧义
        $query = Post::where('posts.status', PostStatus::ACTIVE)
            ->alias('posts')
            ->leftJoin('post_boosts pb', "pb.post_id = posts.id AND pb.status = 1 AND pb.expire_at > '{$now}'")
            ->field('posts.*, CASE WHEN pb.id IS NOT NULL THEN 1 ELSE 0 END as is_boosted')
            ->group('posts.id')
            // 可见性过滤：只显示公开的，或自己发布的私密帖
            ->where(function ($q) use ($userId) {
                $q->where('posts.visibility', 1);
                if ($userId) {
                    $q->whereOr('posts.user_id', $userId);
                }
            })
            ->with(['images' => function ($q) {
                $q->where('sort_order', 0)->field('post_id,image_url,thumb_url');
            }, 'user'])
            ->order('is_boosted', 'desc')
            ->order('posts.is_top', 'desc')
            ->order('posts.created_at', 'desc');

        // 分类筛选（支持逗号分隔的多分类：如 "1,4" 表示宠物+其它物品）
        if (!is_null($category) && $category !== '') {
            $categories = PostCategory::parseMultiple((string)$category);
            if (count($categories) === 1) {
                $query->where('category', $categories[0]);
            } elseif (count($categories) > 1) {
                $query->whereIn('category', $categories);
            }
        }

        // 城市筛选（净化输入）
        if (!empty($city)) {
            $query->where('lost_city', self::sanitize($city));
        }

        // 关键词搜索（限制长度防止超长输入攻击）
        if (!empty($keyword)) {
            $keyword = mb_substr(self::sanitize($keyword), 0, 50);
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('name', "%{$keyword}%")
                    ->whereOr('appearance', 'like', "%{$keyword}%")
                    ->whereOr('lost_address', 'like', "%{$keyword}%");
            });
        }

        // 时间范围
        if (!empty($days) && in_array((int)$days, [1, 3, 7, 30])) {
            $query->whereTime('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")));
        }

        // ⚠️ 未成人保护：列表中隐藏儿童的精确地址
        $list = $query->paginate($pageSize, false, ['page' => $page]);
        $items = $list->items();

        $result = [];
        foreach ($items as $item) {
            $row = $item->toArray();
            if (PostCategory::isMinor($row['category'])) {
                $row = self::maskChildInfo($row);
            }
            $result[] = $row;
        }

        // 附加发布者 VIP 快照（批量，无 N+1）
        UserResource::attachVipInList($result, 'user');

        return [
            'list'      => $result,
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ];
    }

    /**
     * 获取启事详情
     */
    public static function getDetail(int $id, ?int $userId = null): array
    {
        $post = Post::with(['images', 'user', 'clues' => function ($q) {
            $q->where('status', 1)->order('created_at', 'desc')->limit(20);
        }, 'clues.user'])
            ->find($id);

        if (!$post || (!$post->isActive() && $post->user_id !== $userId)) {
            throw new BusinessException(ErrorCode::POST_NOT_FOUND);
        }

        // 私密帖仅发布者本人可查看
        if ($post->visibility == 2 && $post->user_id !== $userId) {
            throw new BusinessException(ErrorCode::POST_NOT_FOUND);
        }

        // 增加浏览次数（用 inc 原子操作防并发问题）
        Post::where('id', $id)->inc('view_count')->update();

        $result = $post->toArray();

        // ⚠️ 未成人保护
        if (PostCategory::isMinor($post->category)) {
            $result = self::maskChildInfo($result);
        }

        // 添加免责声明
        $result['disclaimer'] = '⚠️ 本平台不保证信息真实性，请通过官方渠道核实。如发现违法线索，请立即拨打110报警。';

        // 互动状态（登录用户才查）
        $result['is_liked']     = false;
        $result['is_favorited'] = false;
        if ($userId) {
            $result['is_liked'] = Like::where('user_id', $userId)
                ->where('target_type', Like::TARGET_POST)
                ->where('target_id', $id)
                ->count() > 0;
            $result['is_favorited'] = Favorite::where('user_id', $userId)
                ->where('post_id', $id)
                ->count() > 0;
        }

        // 悬赏发放记录
        $result['reward_claims'] = [];
        $rewardAmount = (float)($result['reward_amount'] ?? 0);
        if ($rewardAmount > 0) {
            try {
                $result['reward_claims'] = RewardClaim::with(['toUser'])
                    ->where('post_id', $id)
                    ->order('created_at', 'desc')
                    ->limit(50)
                    ->select()
                    ->toArray();
            } catch (\Throwable $e) {
                Log::error('Load reward_claims failed: ' . $e->getMessage());
            }
        }

        // 捐赠记录（最近20条）
        $result['donations'] = [];
        $result['boosts']    = [];
        try {
            $donations = Donation::with(['fromUser'])
                ->where('post_id', $id)
                ->order('created_at', 'desc')
                ->limit(20)
                ->select()
                ->toArray();
            // 匿名捐赠隐藏用户信息
            foreach ($donations as &$d) {
                if ($d['is_anonymous']) {
                    $d['from_user'] = null;
                }
            }
            unset($d);
            $result['donations'] = $donations;

            // 推广置顶记录（最近20条）
            $result['boosts'] = PostBoost::with(['user'])
                ->where('post_id', $id)
                ->order('created_at', 'desc')
                ->limit(20)
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('Load donations/boosts failed: ' . $e->getMessage());
        }

        // 附加发布者 / 线索作者 / 悬赏接收方 / 捐赠人 / 置顶人 的 VIP
        if (isset($result['user']) && is_array($result['user'])) {
            UserResource::attachVipSingle($result['user']);
        }
        if (!empty($result['clues'])) {
            UserResource::attachVipInList($result['clues'], 'user');
        }
        if (!empty($result['reward_claims'])) {
            UserResource::attachVipInList($result['reward_claims'], 'to_user');
        }
        if (!empty($result['donations'])) {
            UserResource::attachVipInList($result['donations'], 'from_user');
        }
        if (!empty($result['boosts'])) {
            UserResource::attachVipInList($result['boosts'], 'user');
        }

        return $result;
    }

    /**
     * 附近启事 (F1/F2/F3)
     *
     * 算法：矩形框预过滤（走索引）+ ST_Distance_Sphere 精确距离 + 距离/时效混合权重
     *
     * @param float $lat 用户纬度
     * @param float $lng 用户经度
     * @param float $radiusKm 查询半径(km)
     * @param int $page
     * @return array
     */
    public static function getNearby(float $lat, float $lng, float $radiusKm, int $page = 1, int $pageSize = 20): array
    {
        if (WalletSetting::getValue('nearby_enabled', '1') !== '1') {
            return ['list' => [], 'page' => $page, 'page_size' => $pageSize, 'total' => 0, 'last_page' => 0];
        }

        // 全局最大半径兜底
        $maxRadius = (float)WalletSetting::getValue('nearby_max_radius_km', '500');
        $radiusKm = min(max(0.1, $radiusKm), $maxRadius);

        // 权重配置
        $distW = (float)WalletSetting::getValue('nearby_distance_weight', '0.6');
        $recW  = (float)WalletSetting::getValue('nearby_recency_weight', '0.4');
        $decayDays = max(1, (int)WalletSetting::getValue('nearby_recency_decay_days', '30'));

        // 纬度 1 度 ≈ 111km；经度 1 度 ≈ cos(lat)*111km
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / max(0.01, cos(deg2rad($lat)) * 111.0);

        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;

        $pageSize = min(50, max(1, $pageSize));
        $offset   = ($page - 1) * $pageSize;

        // 取已发布 + 公开 + 经纬度在框内的帖子
        // 用户在矩形内 → 精确算距离 → HAVING <= radiusKm
        // 排序公式：distScore * distW + recScore * recW
        //   distScore = 1 - dist/radius（0..1，越近越高）
        //   recScore  = 1 / (1 + days_since_lost / decayDays)（越新越高）
        $sql = "
            SELECT SQL_CALC_FOUND_ROWS p.*,
                ST_Distance_Sphere(POINT(p.lost_longitude, p.lost_latitude), POINT(:lng, :lat))/1000 AS distance_km
            FROM posts p
            WHERE p.status = 1
              AND p.visibility = 1
              AND p.lost_longitude IS NOT NULL
              AND p.lost_latitude IS NOT NULL
              AND p.lost_longitude BETWEEN :minLng AND :maxLng
              AND p.lost_latitude BETWEEN :minLat AND :maxLat
            HAVING distance_km <= :radius
            ORDER BY (
                (1 - distance_km / :radius) * :distW
                + (1 / (1 + GREATEST(DATEDIFF(NOW(), p.lost_at), 0) / :decayDays)) * :recW
            ) DESC
            LIMIT {$offset}, {$pageSize}
        ";

        $bindings = [
            ':lat'       => $lat,
            ':lng'       => $lng,
            ':minLat'    => $minLat,
            ':maxLat'    => $maxLat,
            ':minLng'    => $minLng,
            ':maxLng'    => $maxLng,
            ':radius'    => $radiusKm,
            ':distW'     => $distW,
            ':recW'      => $recW,
            ':decayDays' => $decayDays,
        ];

        $rows = Db::query($sql, $bindings);
        $total = (int)Db::query('SELECT FOUND_ROWS() as cnt')[0]['cnt'];

        // 补充 user + images（批量查避免 N+1）
        $postIds = array_column($rows, 'id');
        $userIds = array_column($rows, 'user_id');
        $usersIndexed = [];
        $imagesIndexed = [];
        if (!empty($userIds)) {
            $us = Db::table('users')->whereIn('id', array_values(array_unique($userIds)))
                ->field('id,nickname,avatar,user_code,user_type')->select()->toArray();
            foreach ($us as $u) $usersIndexed[(int)$u['id']] = $u;
        }
        if (!empty($postIds)) {
            $imgs = Db::table('post_images')->whereIn('post_id', $postIds)
                ->where('sort_order', 0)
                ->field('post_id,image_url,thumb_url')
                ->select()->toArray();
            foreach ($imgs as $im) $imagesIndexed[(int)$im['post_id']][] = $im;
        }

        $list = [];
        foreach ($rows as $row) {
            $row['user']   = $usersIndexed[(int)$row['user_id']] ?? null;
            $row['images'] = $imagesIndexed[(int)$row['id']] ?? [];
            if (PostCategory::isMinor((int)$row['category'])) {
                $row = self::maskChildInfo($row);
            }
            $list[] = $row;
        }

        UserResource::attachVipInList($list, 'user');

        return [
            'list'      => $list,
            'page'      => $page,
            'page_size' => $pageSize,
            'total'     => $total,
            'last_page' => (int)ceil($total / $pageSize),
        ];
    }

    /**
     * 获取用户自己的启事列表
     *
     * @param int   $userId  用户ID
     * @param array $params  分页参数
     * @return array
     */
    public static function getMine(int $userId, array $params = []): array
    {
        $page     = max(1, (int)($params['page'] ?? 1));
        $pageSize = min(50, max(1, (int)($params['page_size'] ?? 20)));

        $list = Post::where('user_id', $userId)
            ->with(['images' => function ($q) {
                $q->where('sort_order', 0)->field('post_id,image_url,thumb_url');
            }])
            ->order('created_at', 'desc')
            ->paginate($pageSize, false, ['page' => $page]);

        return [
            'list'      => $list->items(),
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ];
    }

    /**
     * 更新启事状态
     */
    public static function updateStatus(int $postId, int $userId, int $newStatus): Post
    {
        $post = Post::find($postId);

        if (!$post) {
            throw new BusinessException(ErrorCode::POST_NOT_FOUND);
        }

        if ($post->user_id !== $userId) {
            throw new BusinessException(ErrorCode::POST_NO_PERMISSION);
        }

        // 只允许: 已发布→已找到, 已发布→已关闭
        $allowed = [
            PostStatus::ACTIVE => [PostStatus::FOUND, PostStatus::CLOSED],
        ];

        if (!isset($allowed[$post->status]) || !in_array($newStatus, $allowed[$post->status])) {
            throw new BusinessException(ErrorCode::POST_ALREADY_CLOSED, '当前状态不允许此操作');
        }

        $post->status = $newStatus;
        $post->save();

        // 悬赏退还：帖子关闭或已找到时，退还未发放的悬赏金额
        if (in_array($newStatus, [PostStatus::FOUND, PostStatus::CLOSED])) {
            try {
                $rewardAmount = (float)Db::table('posts')->where('id', $postId)->value('reward_amount');
                if ($rewardAmount > 0) {
                    WalletService::refundReward($postId);
                }
            } catch (\Throwable $e) {
                // reward_amount 字段不存在时静默跳过（迁移未执行）
            }
        }

        Log::info("Post status updated: id={$postId}, user={$userId}, newStatus={$newStatus}");

        return $post;
    }

    /**
     * 编辑启事（仅允许编辑待审核或被驳回的启事）
     *
     * @param int   $postId  启事ID
     * @param int   $userId  用户ID
     * @param array $data    更新数据
     * @param array $images  新图片路径列表（如果提供则替换全部图片）
     * @return Post
     */
    public static function update(int $postId, int $userId, array $data, ?array $images = null): Post
    {
        $post = Post::find($postId);

        if (!$post) {
            throw new BusinessException(ErrorCode::POST_NOT_FOUND);
        }

        if ($post->user_id !== $userId) {
            throw new BusinessException(ErrorCode::POST_NO_PERMISSION);
        }

        // 只允许编辑待审核、被驳回或举报屏蔽的启事
        $editableStatuses = [PostStatus::PENDING, PostStatus::REJECTED, PostStatus::REPORT_BLOCKED];
        if (!in_array($post->status, $editableStatuses)) {
            throw new BusinessException(ErrorCode::POST_ALREADY_CLOSED, '当前状态不允许编辑，仅待审核、被驳回或举报屏蔽的启事可编辑');
        }

        // 儿童安全校验
        $category = (int)($data['category'] ?? $post->category);
        if (PostCategory::isMinor($category)) {
            self::validateChildSafety($data);
        }

        // 敏感词过滤
        self::filterSensitiveContent($data);

        Db::startTrans();
        try {
            // 更新允许修改的字段
            $allowedFields = [
                'name', 'appearance', 'description',
                'lost_at', 'lost_province', 'lost_city', 'lost_district', 'lost_address',
                'visibility',
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'visibility') {
                        $post->$field = (int)$data[$field];
                    } else {
                        $post->$field = self::sanitize($data[$field]);
                    }
                }
            }

            // 经纬度
            if (isset($data['lost_longitude'])) {
                $lng = $data['lost_longitude'];
                $post->lost_longitude = ($lng !== null && abs((float)$lng) <= 180) ? (float)$lng : null;
            }
            if (isset($data['lost_latitude'])) {
                $lat = $data['lost_latitude'];
                $post->lost_latitude = ($lat !== null && abs((float)$lat) <= 90) ? (float)$lat : null;
            }

            // 编辑后重新进入待审核状态
            $post->status = PostStatus::PENDING;
            $post->audit_remark = '';
            $post->save();

            // 如果提供了新的图片列表，替换全部图片
            if ($images !== null) {
                $validImages = self::validateImagePaths($images);
                // 删除旧图片记录
                PostImage::where('post_id', $postId)->delete();
                // 保存新图片
                if (!empty($validImages)) {
                    $cdnUrl = env('APP_CDN_URL', '');
                    $imageData = [];
                    foreach ($validImages as $index => $url) {
                        $imageData[] = [
                            'post_id'    => $post->id,
                            'image_url'  => $cdnUrl . $url,
                            'thumb_url'  => self::getThumbUrl($url),
                            'sort_order' => $index,
                            'created_at' => date('Y-m-d H:i:s'),
                        ];
                    }
                    (new PostImage())->saveAll($imageData);
                }
            }

            Db::commit();

            Log::info("Post updated: id={$postId}, user={$userId}, re-submitted for review");

            return Post::with(['images', 'user'])->find($post->id);

        } catch (BusinessException $e) {
            Db::rollback();
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("Post update failed: " . $e->getMessage());
            throw new BusinessException(ErrorCode::DB_ERROR);
        }
    }

    // =====================================================
    // ⚠️ 安全方法
    // =====================================================

    /**
     * 未成人安全校验
     * - 不允许精确住址
     * - 名字长度限制（支持化名）
     */
    protected static function validateChildSafety(array $data): void
    {
        // 检查是否包含精确门牌号/楼栋号等
        $address = $data['lost_address'] ?? '';
        if (preg_match('/\d+号楼|\d+栋|\d+单元|\d+室|\d+号$|\d+弄|\d+幢/', $address)) {
            throw new BusinessException(
                ErrorCode::CHILD_ADDRESS_DENIED,
                '为保护儿童安全，请勿填写精确门牌号。请填写到街道/小区级别。'
            );
        }

        // 儿童联系方式建议检查（非强制，仅日志警告）
        $phone = $data['contact_phone'] ?? '';
        if (!empty($phone) && preg_match('/^1[3-9]\d{9}$/', $phone)) {
            Log::info("Child post uses mobile phone as contact: {$phone}");
        }
    }

    /**
     * 脱敏联系电话（列表和详情返回时）— App Store Guideline 5.1.1
     * 所有分类的帖子都脱敏联系电话
     */
    protected static function maskContactPhone(array $item): array
    {
        if (!empty($item['contact_phone']) && strlen($item['contact_phone']) >= 7) {
            $phone = $item['contact_phone'];
            $item['contact_phone'] = substr($phone, 0, 3) . '****' . substr($phone, -4);
        } elseif (!empty($item['contact_phone'])) {
            $item['contact_phone'] = '****';
        }
        return $item;
    }

    /**
     * 脱敏儿童信息（列表和详情返回时）
     */
    protected static function maskChildInfo(array $item): array
    {
        // 地址只显示到区级
        // if (!empty($item['lost_address'])) {
        //     $item['lost_address'] = '（地址已隐藏，请联系发布者）';
        // }

        // 经纬度不对外暴露
        $item['lost_longitude'] = null;
        $item['lost_latitude'] = null;

        // 名字不再隐藏，直接显示完整名称

        // ⚠️ 新增：联系电话中间四位隐藏
        if (!empty($item['contact_phone']) && strlen($item['contact_phone']) >= 7) {
            $phone = $item['contact_phone'];
            $len = strlen($phone);
            $item['contact_phone_masked'] = substr($phone, 0, 3) . '****' . substr($phone, -4);
            // 保留原始电话给发布者自己看（详情接口根据 user_id 判断）
        }

        return $item;
    }

    /**
     * 输入净化 — 防 XSS
     */
    protected static function sanitize(string $value): string
    {
        $value = strip_tags($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value); // 移除控制字符
        return trim($value);
    }

    /**
     * ⚠️ 图片路径校验 — 防止路径遍历攻击
     */
    protected static function validateImagePaths(array $images): array
    {
        $valid = [];
        foreach ($images as $url) {
            if (!is_string($url)) continue;
            // 只允许 /uploads/ 开头的路径
            if (preg_match('#^/uploads/\d{8}/[a-f0-9]+\.\w+$#', $url)) {
                $valid[] = $url;
            } else {
                Log::warning("Invalid image path rejected: {$url}");
            }
        }
        return $valid;
    }

    /**
     * 基础敏感词过滤
     * MVP阶段用简单的关键词匹配，后续可接入第三方
     */
    public static function filterSensitiveContent(array $data): void
    {
        // 基础关键词（可从数据库/配置文件读取扩展）
        $sensitiveWords = [
            '赌博', '代孕', '枪支', '毒品', '色情',
            '诈骗', '传销', '法轮', '邪教',
        ];

        $checkFields = ['name', 'appearance', 'description'];
        foreach ($checkFields as $field) {
            $value = $data[$field] ?? '';
            foreach ($sensitiveWords as $word) {
                if (str_contains($value, $word)) {
                    Log::warning("Sensitive content detected in field [{$field}]: contains [{$word}]");
                    throw new BusinessException(
                        ErrorCode::PARAM_VALIDATE_FAIL,
                        '内容包含违规信息，请修改后重新提交'
                    );
                }
            }
        }
    }

    /**
     * 生成缩略图路径
     */
    protected static function getThumbUrl(string $url): string
    {
        $info = pathinfo($url);
        return ($info['dirname'] ?? '') . '/' . ($info['filename'] ?? '') . '_thumb.' . ($info['extension'] ?? 'jpg');
    }
}
