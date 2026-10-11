<?php

declare(strict_types=1);

use App\Tests\Behat\ApiContext;
use App\Tests\Behat\CatalogContext;
use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Formatter\PrettyFormatter;
use Behat\Config\Profile;
use Behat\Config\Suite;

return new Config()
    ->withProfile(
        new Profile('default')
            ->withSuite(
                new Suite('api')
                    ->withPaths('%paths.base%/features')
                    ->withContexts(ApiContext::class, CatalogContext::class),
            )
            ->withExtension(new Extension(FriendsOfBehat\SymfonyExtension\ServiceContainer\SymfonyExtension::class, [
                'bootstrap' => 'tests/bootstrap.php',
                'kernel' => [
                    'class' => App\Kernel::class,
                    'environment' => 'test',
                    'debug' => true,
                ],
            ]))
            ->withFormatter(new PrettyFormatter()),
    );
