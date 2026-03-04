<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\PostCategory;
use app\common\model\Post;
use app\common\model\Report;
use app\common\model\User;
use think\facade\Log;

class TelegramService
{
    const BOT_TOKEN = '7709800644:AAEcd3FjsfdPK6euoMxGsSAbBUE1tTDKeg4';
    const CHAT_ID   = '1030830511';
    const API_TIMEOUT = 5;

    /**
     * 新启事发布通知
     */
    public static function notifyNewPost(Post $post): void
    {
        $category = PostCategory::getName($post->category);
        $user = User::find($post->user_id);
        $userCode = $user ? ($user->user_code ?: 'GH' . $user->id) : '未知';

        $text = "📋 新启事发布\n"
            . "类别：{$category}\n"
            . "名字：{$post->name}\n"
            . "城市：{$post->lost_city}\n"
            . "发布者：{$userCode}\n"
            . "时间：{$post->created_at}";

        self::sendMessage($text);
    }

    /**
     * 新举报通知
     */
    public static function notifyNewReport(Report $report): void
    {
        $targetTypes = [
            Report::TARGET_POST => '启事',
            Report::TARGET_CLUE => '线索',
            Report::TARGET_USER => '用户',
        ];
        $reasons = [
            Report::REASON_FAKE    => '虚假信息',
            Report::REASON_AD      => '广告推销',
            Report::REASON_ILLEGAL => '涉及违法',
            Report::REASON_HARASS  => '骚扰辱骂',
            Report::REASON_OTHER   => '其他',
        ];

        $targetType = $targetTypes[$report->target_type] ?? '未知';
        $reason = $reasons[$report->reason] ?? '未知';
        $user = User::find($report->user_id);
        $userCode = $user ? ($user->user_code ?: 'GH' . $user->id) : '未知';

        $text = "🚨 新举报\n"
            . "目标：{$targetType} #{$report->target_id}\n"
            . "原因：{$reason}\n";

        if (!empty($report->description)) {
            $desc = mb_substr($report->description, 0, 100);
            $text .= "描述：{$desc}\n";
        }

        $text .= "举报人：{$userCode}\n"
            . "时间：{$report->created_at}";

        self::sendMessage($text);
    }

    /**
     * 发送 Telegram 消息
     */
    protected static function sendMessage(string $text): void
    {
        try {
            $url = 'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage';

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'chat_id' => self::CHAT_ID,
                    'text'    => $text,
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::API_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::API_TIMEOUT,
            ]);

            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            Log::error('Telegram send failed: ' . $e->getMessage());
        }
    }
}
