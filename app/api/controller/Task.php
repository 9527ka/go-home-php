<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\service\TaskService;
use think\Response;

class Task extends BaseApi
{
    /**
     * 获取任务列表
     * GET /api/tasks
     */
    public function list(): Response
    {
        $tasks = TaskService::getTaskList($this->getUserId());

        return $this->success($tasks);
    }

    /**
     * 完成任务
     * POST /api/task/complete  {task_key}
     */
    public function complete(): Response
    {
        $taskKey = trim((string)$this->request->post('task_key', ''));

        if (empty($taskKey)) {
            return $this->error(ErrorCode::PARAM_MISSING, '缺少任务标识');
        }

        $result = TaskService::completeTask($this->getUserId(), $taskKey);

        return $this->success($result, '任务完成');
    }
}
