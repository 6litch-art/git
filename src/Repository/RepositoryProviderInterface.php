<?php

namespace Git\Repository;

/**
 * A source of repositories besides the `git.repositories` configuration -
 * typically an application's database (the projects of its clients, the
 * software it publishes). Tag the service `git.repository_provider`
 * (autoconfigured for any class implementing this interface).
 *
 * Each repository has the same shape as a configured one:
 *
 *     ['path' => '/srv/repos/app.git', 'url' => 'https://…', 'label' => 'App',
 *      'description' => null, 'default_branch' => 'HEAD']
 *
 * A configured repository wins over a provided one of the same name.
 */
interface RepositoryProviderInterface
{
    /** @return array<string, array{path: string, url?: ?string, label?: ?string, description?: ?string, default_branch?: string}> */
    public function getRepositories(): array;
}
