<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\model\SignLog;
use app\common\model\TaskDefinition;
use app\common\model\TaskLog;
use app\common\model\WalletSetting;
use think\facade\Db;
use think\Request;
use think\Response;

class SignManage
{
    /**
     * 签到统计
     * GET /admin/sign/stats
     */
    public function stats(Request $request): Response
    {
        $today = date('Y-m-d');

        // 今日签到人数
        $todaySignCount = SignLog::where('sign_date', $today)->count();

        // 今日发放奖励总额
        $todayRewardTotal = SignLog::where('sign_date', $today)->sum('final_reward');

        // 今日任务完成数
        $todayTaskCount = TaskLog::where('log_date', $today)
            ->where('is_rewarded', 1)
            ->count();

        // 今日任务奖励总额
        $todayTaskReward = TaskLog::where('log_date', $today)
            ->where('is_rewarded', 1)
            ->sum('reward_amount');

        // 累计签到人次
        $totalSignCount = SignLog::count();

        // 签到开关状态
        $signEnabled = WalletSetting::getValue('sign_enabled', '1') === '1';

        return json([
            'code' => 0,
            'msg'  => 'ok',
            'data' => [
                'today_sign_count'   => $todaySignCount,
                'today_reward_total' => round((float)$todayRewardTotal, 2),
                'today_task_count'   => $todayTaskCount,
                'today_task_reward'  => round((float)$todayTaskReward, 2),
                'total_sign_count'   => $totalSignCount,
                'sign_enabled'       => $signEnabled,
            ],
        ]);
    }

    /**
     * 签到日志列表
     * GET /admin/sign/logs?page=&date=
     */
    public function signLogs(Request $request): Response
    {
        $page = max(1, (int)$request->get('page', 1));
        $date = $request->get('date');

        $query = SignLog::with(['user'])
            ->order('created_at', 'desc');

        if (!empty($date)) {
            $query->where('sign_date', $date);
        }

        $list = $query->paginate(['list_rows' => 20, 'page' => $page]);

        return json(['code' => 0, 'msg' => 'ok', 'data' => $list->toArray()]);
    }

    /**
     * 任务完成日志
     * GET /admin/sign/task-logs?page=&date=
     */
    public function taskLogs(Request $request): Response
    {
        $page = max(1, (int)$request->get('page', 1));
        $date = $request->get('date');

        $query = TaskLog::with(['user'])
            ->where('is_rewarded', 1)
            ->order('completed_at', 'desc');

        if (!empty($date)) {
            $query->where('log_date', $date);
        }

        $list = $query->paginate(['list_rows' => 20, 'page' => $page]);

        return json(['code' => 0, 'msg' => 'ok', 'data' => $list->toArray()]);
    }

    /**
     * 任务定义列表
     * GET /admin/sign/tasks
     */
    public function taskDefinitions(Request $request): Response
    {
        $tasks = TaskDefinition::order('sort_order', 'asc')->select();

        return json(['code' => 0, 'msg' => 'ok', 'data' => $tasks->toArray()]);
    }

    /**
     * 更新任务配置
     * POST /admin/sign/task/update  {id, reward?, is_enabled?, name?, description?}
     */
    public function updateTask(Request $request): Response
    {
        $id = (int)$request->post('id', 0);
        $task = TaskDefinition::find($id);

        if (!$task) {
            return json(['code' => ErrorCode::PARAM_FORMAT_ERROR, 'msg' => '任务不存在']);
        }

        $reward = $request->post('reward');
        if (!is_null($reward)) {
            $task->reward = round((float)$reward, 2);
        }

        $isEnabled = $request->post('is_enabled');
        if (!is_null($isEnabled)) {
            $task->is_enabled = (int)$isEnabled;
        }

        $name = $request->post('name');
        if (!is_null($name) && $name !== '') {
            $task->name = (string)$name;
        }

        $description = $request->post('description');
        if (!is_null($description)) {
            $task->description = (string)$description;
        }

        $task->save();

        return json(['code' => 0, 'msg' => '更新成功']);
    }

    /**
     * 签到系统开关
     * POST /admin/sign/toggle  {enabled: 0|1}
     */
    public function toggle(Request $request): Response
    {
        $enabled = (int)$request->post('enabled', 1);

        Db::table('wallet_settings')
            ->where('setting_key', 'sign_enabled')
            ->update(['setting_value' => $enabled ? '1' : '0']);

        return json(['code' => 0, 'msg' => $enabled ? '签到已开启' : '签到已关闭']);
    }
}
