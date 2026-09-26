<?php

// Standalone (composer install in this checkout) or inside a host application
// (vendor/git/git-bundle): whichever autoloader exists is used, and the test
// namespace is registered by hand because a host's autoloader never reads a
// dependency's autoload-dev.
foreach ([__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../autoload.php'] as $candidate) {
    if (is_file($candidate)) {
        $loader = require $candidate;
        $loader->addPsr4('Tests\\Git\\', __DIR__);
        // Prepended: the classes under test are this checkout's.
        $loader->addPsr4('Git\\', __DIR__.'/../src', true);

        return;
    }
}

throw new RuntimeException('No autoloader found: run composer install in this checkout or install the bundle in an application.');
