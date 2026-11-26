<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
                ->withPaths([
                    __DIR__ . '/src',
                ])
                ->withSkip([
                    NewlineBetweenClassLikeStmtsRector::class,
                ])
                ->withCache(__DIR__ . '/.rector.cache')
                ->withPreparedSets(
                        deadCode: true,
                        codeQuality: true,
                        codingStyle: true,
                        typeDeclarations: true,
                        typeDeclarationDocblocks: true,
                        privatization: true,
                        // naming: true,
                        namedArgs: true,
                        phpunitCodeQuality: true,
                        phpunitNarrowAsserts: true,
                        phpunitMockToStub: true,
                        doctrineCodeQuality: true,
                        symfonyCodeQuality: true,
                        symfonyConfigs: true,
                        rectorPreset: true,)
                ->withAttributesSets(phpunit: true)
                ->withComposerBased(phpunit: true)
                ->withImportNames(importShortClasses: false, removeUnusedImports: true)
                ->withPhpSets()
;
