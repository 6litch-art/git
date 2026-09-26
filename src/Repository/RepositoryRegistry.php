<?php

namespace Git\Repository;

/**
 * Every repository the bundle knows: the configured ones, then those of the
 * providers. Resolved once per request (or per warmup), lazily - a provider
 * that reads a database is not queried while the container is compiled.
 */
class RepositoryRegistry
{
    private const DEFAULTS = ['url' => null, 'label' => null, 'description' => null, 'default_branch' => 'HEAD'];

    /** @var array<string, array>|null */
    private ?array $resolved = null;

    /**
     * @param array<string, array>                  $configured `git.repositories`
     * @param iterable<RepositoryProviderInterface> $providers
     */
    public function __construct(
        private readonly array $configured = [],
        private readonly iterable $providers = [],
    ) {}

    /** @return array<string, array{path: string, url: ?string, label: ?string, description: ?string, default_branch: string}> */
    public function all(): array
    {
        if (null !== $this->resolved) {
            return $this->resolved;
        }

        $repositories = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getRepositories() as $name => $config) {
                if (isset($config['path']) && '' !== (string) $name) {
                    $repositories[(string) $name] = $config + self::DEFAULTS;
                }
            }
        }
        foreach ($this->configured as $name => $config) {
            $repositories[(string) $name] = $config + self::DEFAULTS;
        }

        return $this->resolved = $repositories;
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    public function get(string $name): ?array
    {
        return $this->all()[$name] ?? null;
    }

    /** Forget what was resolved: the next call asks the providers again. */
    public function reset(): void
    {
        $this->resolved = null;
    }
}
