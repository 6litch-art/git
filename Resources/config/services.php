<?php

use Git\Controller\RepositoryController;
use Git\Service\Git2Service;
use Git\Warmer\RepositoryWarmer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire(true)
            ->autoconfigure(true);

    $services->set('git.service.git2', Git2Service::class)
        ->args(['%git.repositories%'])
        ->public();

    $services->alias(Git2Service::class, 'git.service.git2');

    $services->set('git.warmer.repository', RepositoryWarmer::class)
        ->args(['%git.repositories%'])
        ->tag('kernel.cache_warmer');

    $services->set('git.controller.repository', RepositoryController::class)
        ->args([
            new Reference('git.service.git2'),
            '%git.access_role%',
        ])
        ->public()
        ->tag('controller.service_arguments');
};
