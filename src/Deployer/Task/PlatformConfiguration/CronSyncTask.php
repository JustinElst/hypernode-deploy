<?php

namespace Hypernode\Deploy\Deployer\Task\PlatformConfiguration;

use Hypernode\Deploy\Deployer\Task\IncrementedTaskTrait;
use Deployer\Task\Task;
use Hypernode\Deploy\Deployer\Task\ConfigurableTaskInterface;
use Hypernode\Deploy\Deployer\Task\RegisterAfterInterface;
use Hypernode\DeployConfiguration\Configuration;
use Hypernode\DeployConfiguration\PlatformConfiguration\CronConfiguration;
use Hypernode\DeployConfiguration\TaskConfigurationInterface;
use Twig\Environment;

use function Deployer\get;
use function Deployer\set;
use function Deployer\run;
use function Deployer\fail;
use function Deployer\task;
use function Deployer\writeln;

class CronSyncTask implements ConfigurableTaskInterface, RegisterAfterInterface
{
    use IncrementedTaskTrait;

    /**
     * @var Environment
     */
    private $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    protected function getIncrementalNamePrefix(): string
    {
        return 'deploy:configuration:cron:sync:';
    }

    public function configureTask(TaskConfigurationInterface $config): void
    {
    }

    public function supports(TaskConfigurationInterface $config): bool
    {
        return $config instanceof CronConfiguration;
    }

    public function registerAfter(): void
    {
    }

    public function getCurrentCrontab(): string
    {
        return run('crontab -l');
    }

    public function setCrontab(string $newCrontab): void
    {
        run('echo "' . $newCrontab . '" | crontab -');
    }

    public function replaceExistingCronBlocks(string $newCronBlock): string
    {
        $beginOld = "### BEGIN " . get("domain") . " ###";
        $endOld = "### END " . get("domain") . " ###";
        $currentCrontab = $this->getCurrentCrontab();

        # Check if begin and end of old block are present
        if (strpos($currentCrontab, $beginOld) === false || strpos($currentCrontab, $endOld) === false) {
            writeln("Appending new cron block for {{domain}} in crontab");
            $newCrontab = $currentCrontab . "\n" . $newCronBlock;
            return $newCrontab;
        } else {
            writeln("Replacing cron block for {{domain}}");
            return preg_replace('/^' . $beginOld . '$.*^' . $endOld . '$/ms', $newCronBlock, $currentCrontab);
        }
    }

    public function build(TaskConfigurationInterface $config): ?Task
    {
        return null;
    }

    public function configure(Configuration $config): void
    {
        task('deploy:cron:sync', function () {
            $newCrontab = $this->replaceExistingCronBlocks(get("new_crontab"));
            $this->setCrontab($newCrontab);
        });
    }
}
