<?php

namespace Git;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class GitBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
