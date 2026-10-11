<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\Concat\DirnameDirConcatStringToDirectStringPathRector;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests'])
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    // Versions are detected from composer.json, so the rule set follows the
    // dependencies instead of drifting apart from them.
    ->withComposerBased(doctrine: true, phpunit: true, symfony: true)
    ->withSkip([
        // `dirname(__DIR__)` reads better than string path arithmetic.
        DirnameDirConcatStringToDirectStringPathRector::class,
        // PHPUnit data providers and named constructors must stay static.
        LocallyCalledStaticMethodToNonStaticRector::class,
    ])
    // Global classes stay fully qualified so Rector does not fight the
    // `@PHP84Migration`-based coding standard.
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withCache(__DIR__.'/var/cache/rector');
