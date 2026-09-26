<?php

use Git\Command\SyncCommand;
use Git\Controller\RepositoryController;
use Git\Repository\RepositoryRegistry;
use Git\Service\Git2Service;
use Git\Warmer\RepositoryWarmer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire(true)
            ->autoconfigure(true);

    // The configured repositories plus those of every
    // git.repository_provider (Git\Repository\RepositoryProviderInterface).
    $services->set('git.repository.registry', RepositoryRegistry::class)
        ->args(['%git.repositories%', tagged_iterator('git.repository_provider')])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->alias(RepositoryRegistry::class, 'git.repository.registry');

    $services->set('git.service.git2', Git2Service::class)
        ->args([new Reference('git.repository.registry')])
        ->public();

    $services->alias(Git2Service::class, 'git.service.git2');

    $services->set('git.warmer.repository', RepositoryWarmer::class)
        ->args([new Reference('git.repository.registry')])
        ->tag('kernel.cache_warmer');

    $services->alias(RepositoryWarmer::class, 'git.warmer.repository');

    $services->set('git.command.sync', SyncCommand::class)
        ->args([new Reference('git.repository.registry'), new Reference('git.warmer.repository')])
        ->tag('console.command');

    // Registered under the FQCN: attribute-imported routes reference the
    // controller by class name. Keep the short id as a BC alias.
    $services->set(RepositoryController::class)
        ->args([
            new Reference('git.service.git2'),
            '%git.access_role%',
            '%git.repository_attribute%',
        ])
        ->public()
        ->tag('controller.service_arguments');

    $services->alias('git.controller.repository', RepositoryController::class)
        ->public();
};
