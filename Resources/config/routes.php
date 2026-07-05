<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// Route names are already prefixed 'git_' in the controller attributes.
// Apply the URL prefix at the application-level import:
//
//     # config/routes/git.yaml
//     git:
//         resource: '@GitBundle/Resources/config/routes.php'
//         prefix: '%git.route_prefix%'
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../src/Controller/', 'attribute');
};
