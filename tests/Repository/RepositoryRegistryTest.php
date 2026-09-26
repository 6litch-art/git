<?php

namespace Tests\Git\Repository;

use Git\Repository\RepositoryProviderInterface;
use Git\Repository\RepositoryRegistry;
use PHPUnit\Framework\TestCase;

class RepositoryRegistryTest extends TestCase
{
    public function testConfiguredRepositoriesWinOverProvidedOnes(): void
    {
        $provider = new class implements RepositoryProviderInterface {
            public function getRepositories(): array
            {
                return [
                    'app' => ['path' => '/provided/app.git', 'label' => 'Provided'],
                    'client' => ['path' => '/provided/client.git'],
                    '' => ['path' => '/nameless.git'],
                    'broken' => ['label' => 'no path'],
                ];
            }
        };

        $registry = new RepositoryRegistry(['app' => ['path' => '/configured/app.git', 'label' => 'Configured']], [$provider]);

        $names = array_keys($registry->all());
        sort($names);
        self::assertSame(['app', 'client'], $names);
        self::assertSame('/configured/app.git', $registry->get('app')['path']);
        self::assertSame('HEAD', $registry->get('client')['default_branch'], 'defaults fill what a provider leaves out');
        self::assertNull($registry->get('client')['url']);
        self::assertFalse($registry->has('broken'));
    }

    public function testProvidersAreAskedOnceUntilReset(): void
    {
        $provider = new class implements RepositoryProviderInterface {
            public int $calls = 0;

            public function getRepositories(): array
            {
                ++$this->calls;

                return ['r' => ['path' => '/r.git']];
            }
        };

        $registry = new RepositoryRegistry([], [$provider]);
        $registry->all();
        $registry->has('r');
        self::assertSame(1, $provider->calls);

        $registry->reset();
        $registry->all();
        self::assertSame(2, $provider->calls);
    }
}
