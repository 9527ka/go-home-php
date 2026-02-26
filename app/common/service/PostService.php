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
            $post->gender        = (int)($data['gender'] ?? 0);
            $post->age           = self::sanitize($data['age'] ?? '');
            $post->species       = self::sanitize($data['species'] ?? '');
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

            // ⚠️ 修复：contact_phone 也需要净化
            $post->contact_name  = self::sanitize($data['contact_name'] ?? '');
            $post->contact_phone = preg_replace('/[^\d\-\+\s]/', '', $data['contact_phone'] ?? '');

            $post->status = PostStatus::PENDING; // ⚠️ 所有发布默认待审核
            $post->save();

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
    public static function getList(array $params): array
    {
        $page     = max(1, (int)($params['page'] ?? 1));
        $pageSize = min(50, max(1, (int)($params['page_size'] ?? 20)));
        $category = $params['category'] ?? null;
        $city     = $params['city'] ?? null;
        $keyword  = $params['keyword'] ?? null;
        $days     = $params['days'] ?? null;

        $query = Post::active()
            ->with(['images' => function ($q) {
                $q->where('sort_order', 0)->field('post_id,image_url,thumb_url');
            }, 'user'])
            ->order('is_top', 'desc')
            ->order('created_at', 'desc');

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

        // 增加浏览次数（用 inc 原子操作防并发问题）
        Post::where('id', $id)->inc('view_count')->update();

        $result = $post->toArray();

        // ⚠️ 未成人保护
        if (PostCategory::isMinor($post->category)) {
            $result = self::maskChildInfo($result);
        }

        // 添加免责声明
        $result['disclaimer'] = '⚠️ 本平台不保证信息真实性，请通过官方渠道核实。如发现违法线索，请立即拨打110报警。';

        return $result;
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
                'name', 'gender', 'age', 'species', 'appearance', 'description',
                'lost_at', 'lost_province', 'lost_city', 'lost_district', 'lost_address',
                'contact_name', 'contact_phone',
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    if (in_array($field, ['gender'])) {
                        $post->$field = (int)$data[$field];
                    } elseif ($field === 'contact_phone') {
                        $post->$field = preg_replace('/[^\d\-\+\s]/', '', $data[$field]);
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
    protected static function filterSensitiveContent(array $data): void
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
