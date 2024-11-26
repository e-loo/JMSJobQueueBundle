<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\FOSRestSetList;
use Rector\Symfony\Set\JMSSetList;
use Rector\Symfony\Set\SymfonySetList;


return RectorConfig::configure()
    ->withSymfonyContainerPhp(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/rector.php',
    ])
    ->withRules([
        \Rector\Symfony\Configs\Rector\Closure\ServiceSetStringNameToClassNameRector::class
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        // LevelSetList::UP_TO_PHP_83, // not yet
        // LevelSetList::UP_TO_PHP_84, // not yet
        SymfonySetList::SYMFONY_60,
        SymfonySetList::SYMFONY_61,
        SymfonySetList::SYMFONY_62,
        SymfonySetList::SYMFONY_63,
        SymfonySetList::SYMFONY_64,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::SYMFONY_52_VALIDATOR_ATTRIBUTES,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        DoctrineSetList::GEDMO_ANNOTATIONS_TO_ATTRIBUTES,
        DoctrineSetList::DOCTRINE_DBAL_30,
        DoctrineSetList::DOCTRINE_DBAL_40,
        DoctrineSetList::DOCTRINE_ORM_213,
        DoctrineSetList::DOCTRINE_ORM_214,
        DoctrineSetList::DOCTRINE_ORM_25,
        DoctrineSetList::DOCTRINE_ORM_29,
        FOSRestSetList::ANNOTATIONS_TO_ATTRIBUTES,
        JMSSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SetList::TYPE_DECLARATION,
        SetList::INSTANCEOF,
        SetList::NAMING,
        SetList::STRICT_BOOLEANS,
        SetList::PHP_74,
        SetList::PHP_80,
        SetList::PHP_81,
        SetList::PHP_82,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
        PHPUnitSetList::PHPUNIT_90,
        PHPUnitSetList::PHPUNIT_100,
        //PHPUnitSetList::PHPUNIT_110, not yet
        SetList::EARLY_RETURN,
        // TODO: Enable this set when all tests are fixed and phpstan is satisfied with type issues
        //SetList::DEAD_CODE,
    ])
    ->withSkip([
        ClassPropertyAssignToConstructorPromotionRector::class,
    ]);
