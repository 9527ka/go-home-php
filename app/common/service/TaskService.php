<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\ErrorCode;
use app\common\enum\WalletTransactionType;
use app\common\exception\BusinessException;
use app\common\model\TaskDefinition;
use app\common\model\TaskLog;
use app\common\model\WalletSetting;
use think\facade\Db;

class TaskService
{
    /**
     * 获取任务列表（含今日进度）
     */
    public static function getTaskList(int $userId): array
    {
        // 检查签到系统开关
        if (WalletSetting::getValue('sign_enabled', '1') !== '1') {
            return [];
        }

        $today = date('Y-m-d');

        // 加载所有启用的任务定义
        $tasks = TaskDefinition::where('is_enabled', 1)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();

        // 加载今日的任务日志
        $logs = TaskLog::where('user_id', $userId)
            ->where('log_date', $today)
            ->column('*', 'task_key');

        // 合并返回
        $result = [];
        foreach ($tasks as $task) {
            $log = $logs[$task['task_key']] ?? null;
            $result[] = [
                'task_key'     => $task['task_key'],
                'name'         => $task['name'],
                'description'  => $task['description'],
                'reward'       => (float)$task['reward'],
                'target_count' => (int)$task['target_count'],
                'progress'     => $log ? (int)$log['progress'] : 0,
                'is_completed' => $log ? (bool)$log['is_completed'] : false,
                'is_rewarded'  => $log ? (bool)$log['is_rewarded'] : false,
            ];
        }

        return $result;
    }

    /**
     * 手动完成任务（用于前端点击"领取"）
     *
     * @param int $userId 用户ID
     * @param string $taskKey 任务标识
     * @return array 完成结果
     */
    public static function completeTask(int $userId, string $taskKey): array
    {
        if (WalletSetting::getValue('sign_enabled', '1') !== '1') {
            throw new BusinessException(ErrorCode::TASK_DISABLED);
        }

        // 查找任务定义
        $task = TaskDefinition::where('task_key', $taskKey)
            ->where('is_enabled', 1)
            ->find();

        if (!$task) {
            throw new BusinessException(ErrorCode::TASK_NOT_FOUND);
        }

        $today = date('Y-m-d');

        // 获取或创建今日日志
        $log = TaskLog::where('user_id', $userId)
            ->where('task_key', $taskKey)
            ->where('log_date', $today)
            ->find();

        if ($log && $log->is_rewarded) {
            throw new BusinessException(ErrorCode::TASK_ALREADY_DONE);
        }

        $reward = (float)$task->reward;

        return Db::transaction(function () use ($userId, $taskKey, $today, $task, $log, $reward) {
            if (!$log) {
                $log = TaskLog::create([
                    'user_id'       => $userId,
                    'task_key'      => $taskKey,
                    'log_date'      => $today,
                    'progress'      => (int)$task->target_count,
                    'is_completed'  => 1,
                    'is_rewarded'   => 1,
                    'reward_amount' => $reward,
                    'completed_at'  => date('Y-m-d H:i:s'),
                ]);
            } else {
                // 确保进度已满
                $log->progress     = max((int)$log->progress, (int)$task->target_count);
                $log->is_completed = 1;
                $log->is_rewarded  = 1;
                $log->reward_amount = $reward;
                $log->completed_at = date('Y-m-d H:i:s');
                $log->save();
            }

            // 发放冻结奖励
            SignService::creditRewardFrozen(
                $userId,
                $reward,
                WalletTransactionType::TASK_REWARD,
                $log->id,
                "任务奖励:{$task->name}"
            );

            $wallet = WalletService::getOrCreateWallet($userId);

            return [
                'task_key'              => $taskKey,
                'reward'                => $reward,
                'reward_frozen_balance' => (float)$wallet->reward_frozen_balance,
            ];
        });
    }

    /**
     * 自动递增任务进度（由其他业务逻辑调用）
     *
     * 当进度达到目标时自动完成并发放奖励。
     *
     * @param int $userId 用户ID
     * @param string $taskKey 任务标识
     * @param int $increment 递增值
     */
    public static function incrementTaskProgress(int $userId, string $taskKey, int $increment = 1): void
    {
        // 静默检查：开关关闭则跳过
        if (WalletSetting::getValue('sign_enabled', '1') !== '1') {
            return;
        }

        $task = TaskDefinition::where('task_key', $taskKey)
            ->where('is_enabled', 1)
            ->find();

        if (!$task) {
            return;
        }

        $today = date('Y-m-d');

        // 获取或创建今日日志
        $log = TaskLog::where('user_id', $userId)
            ->where('task_key', $taskKey)
            ->where('log_date', $today)
            ->find();

        if ($log && $log->is_rewarded) {
            return; // 已完成已发奖，跳过
        }

        Db::transaction(function () use ($userId, $taskKey, $today, $task, $log, $increment) {
            if (!$log) {
                $log = TaskLog::create([
                    'user_id'  => $userId,
                    'task_key' => $taskKey,
                    'log_date' => $today,
                    'progress' => $increment,
                ]);
            } else {
                $log->progress = $log->progress + $increment;
                $log->save();
            }

            // 检查是否达到目标
            if ($log->progress >= (int)$task->target_count && !$log->is_completed) {
                $reward = (float)$task->reward;

                $log->is_completed  = 1;
                $log->is_rewarded   = 1;
                $log->reward_amount = $reward;
                $log->completed_at  = date('Y-m-d H:i:s');
                $log->save();

                // 发放冻结奖励
                SignService::creditRewardFrozen(
                    $userId,
                    $reward,
                    WalletTransactionType::TASK_REWARD,
                    $log->id,
                    "任务奖励:{$task->name}"
                );
            }
        });
    }
}
