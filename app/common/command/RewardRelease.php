<?php
declare(strict_types=1);

namespace app\common\command;

use app\common\service\RewardReleaseService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 每日释放奖励冻结余额
 *
 * 用法: php think reward:release
 * Crontab: 0 0 * * * cd /path/to/server && php think reward:release >> /var/log/reward_release.log 2>&1
 */
class RewardRelease extends Command
{
    protected function configure(): void
    {
        $this->setName('reward:release')
             ->setDescription('每日释放签到/任务奖励冻结余额的10%到可用余额');
    }

    protected function execute(Input $input, Output $output): int
    {
        $output->writeln('[' . date('Y-m-d H:i:s') . '] Starting daily reward release...');

        try {
            $count = RewardReleaseService::releaseDaily();
            $output->writeln("Done. Released rewards for {$count} wallet(s).");
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return 1;
        }

        return 0;
    }
}
