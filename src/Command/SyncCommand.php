<?php

namespace Git\Command;

use Git\Repository\RepositoryRegistry;
use Git\Warmer\RepositoryWarmer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What the warmer does at cache:warmup, on demand: clone the repositories
 * that have a url but no copy yet, fetch the others - all of them, or those
 * named. Worth a cron line when repositories come from a provider (they are
 * added long after the last deploy's warmup).
 */
#[AsCommand(name: 'git:sync', description: 'Clone or fetch the repositories that have a remote url')]
class SyncCommand extends Command
{
    public function __construct(
        private readonly RepositoryRegistry $registry,
        private readonly RepositoryWarmer $warmer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('names', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Only these repositories');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $names = $input->getArgument('names');
        $repositories = $this->registry->all();

        foreach ($names as $name) {
            if (!isset($repositories[$name])) {
                $io->error(sprintf('Unknown repository "%s".', $name));

                return Command::INVALID;
            }
        }

        $failed = 0;
        $synced = 0;
        foreach ($repositories as $name => $config) {
            if ($names && !\in_array($name, $names, true)) {
                continue;
            }
            if (empty($config['url'])) {
                $io->comment(sprintf('%s: no url, nothing to sync.', $name));
                continue;
            }
            $this->warmer->sync($name, $config) ? $synced++ : $failed++;
        }

        $failed ? $io->warning(sprintf('%d synced, %d failed.', $synced, $failed)) : $io->success(sprintf('%d synced.', $synced));

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
