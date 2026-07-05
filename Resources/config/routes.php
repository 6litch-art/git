<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// Route names are already prefixed 'git_' in the controller attributes;
// only the URL prefix comes from the bundle configuration.
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../src/Controller/', 'attribute')
        ->prefix('%git.route_prefix%');
};
