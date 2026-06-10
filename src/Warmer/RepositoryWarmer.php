<?php

namespace Git\Warmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Clones any repository that has a `url` configured but whose `path` does not
 * exist yet.  Runs during `cache:warmup` and streams formatted progress to
 * STDOUT so the operator sees what is happening.
 */
class RepositoryWarmer implements CacheWarmerInterface
{
    public function __construct(private readonly array $repositories) {}

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        foreach ($this->repositories as $name => $config) {
            if (empty($config['url'])) {
                continue;
            }

            $path = $config['path'];

            if (is_dir($path)) {
                $this->fetchUpdates($name, $config);
            } else {
                $this->cloneRepository($name, $config);
            }
        }

        return [];
    }

    private function cloneRepository(string $name, array $config): void
    {
        $url   = $config['url'];
        $path  = $config['path'];
        $label = $config['label'] ?? $name;

        $this->writeLine('');
        $this->writeLine("  \033[1;34m↓ Cloning\033[0m \033[1m{$label}\033[0m");
        $this->writeLine("    url  : {$url}");
        $this->writeLine("    into : {$path}");

        $cmd = ['git', 'clone', '--mirror', '--progress', $url, $path];
        $this->runWithProgress($cmd);
    }

    private function fetchUpdates(string $name, array $config): void
    {
        $path  = $config['path'];
        $label = $config['label'] ?? $name;

        $this->writeLine('');
        $this->writeLine("  \033[1;36m↻ Fetching\033[0m \033[1m{$label}\033[0m");

        // --mirror repos are bare; use GIT_DIR env var + fetch --all --prune
        $cmd = ['git', 'fetch', '--all', '--prune', '--progress'];
        $this->runWithProgress($cmd, ['GIT_DIR' => $path]);
    }

    private function runWithProgress(array $cmd, array $env = []): void
    {
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $envVars = $env ? array_merge(getenv(), $env) : null;
        $proc = proc_open($cmd, $spec, $pipes, null, $envVars);
        if (!is_resource($proc)) {
            $this->writeLine("  \033[1;31m✗ Failed to start process\033[0m");
            return;
        }

        fclose($pipes[0]);

        // git sends clone/fetch progress to stderr; stdout is usually silent
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $lastLine  = '';
        $startTime = microtime(true);

        while (true) {
            $status = proc_get_status($proc);

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            // git uses \r for in-place progress updates — show only the last segment
            $raw = $stderr . $stdout;
            if ($raw !== '') {
                $segments = preg_split('/[\r\n]/', $raw);
                foreach ($segments as $seg) {
                    $seg = trim($seg);
                    if ($seg === '' || $seg === $lastLine) continue;
                    $lastLine = $seg;
                    // Colour "Receiving objects: 100%" differently from mid-progress
                    if (str_contains($seg, '100%') || str_ends_with($seg, 'done.')) {
                        $this->writeLine("    \033[1;32m✓\033[0m {$seg}");
                    } else {
                        $this->writeLine("    \033[2m{$seg}\033[0m");
                    }
                }
            }

            if (!$status['running']) {
                break;
            }

            usleep(50_000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        $elapsed = round(microtime(true) - $startTime, 1);

        if ($exit === 0) {
            $this->writeLine("    \033[1;32m✓ done\033[0m ({$elapsed}s)");
        } else {
            $this->writeLine("    \033[1;31m✗ exited with code {$exit}\033[0m");
        }
    }

    private function writeLine(string $line): void
    {
        fwrite(STDOUT, $line . "\n");
    }
}
