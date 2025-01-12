<?php

namespace lucatume\WPBrowser;

use lucatume\WPBrowser\Command\GenerateWPUnit;
use lucatume\WPBrowser\Module\WPBrowser;
use lucatume\WPBrowser\Module\WPBrowserMethods;
use lucatume\WPBrowser\Module\WPCLI;
use lucatume\WPBrowser\Module\WPDb;
use lucatume\WPBrowser\Module\WPFilesystem;
use lucatume\WPBrowser\Module\WPLoader;
use lucatume\WPBrowser\Module\WPQueries;
use lucatume\WPBrowser\Module\WPWebDriver;
use lucatume\WPBrowser\Template\Wpbrowser as WpbrowserInitTemplate;
use lucatume\WPBrowser\TestCase\WPAjaxTestCase;
use lucatume\WPBrowser\TestCase\WPCanonicalTestCase;
use lucatume\WPBrowser\TestCase\WPRestApiTestCase;
use lucatume\WPBrowser\TestCase\WPRestControllerTestCase;
use lucatume\WPBrowser\TestCase\WPRestPostTypeControllerTestCase;
use lucatume\WPBrowser\TestCase\WPTestCase;
use lucatume\WPBrowser\TestCase\WPXMLRPCTestCase;
use function class_alias;

/**
 * Defines a set of class aliases to allow referencing the framework classes with their previous versions' names.
 */

/**
 * Calls to `class_alias` will immediately autoload the class in a eager fashion.
 * Using an autoloader will load them lazily.
 */
$deprecatedAutoloader = static function (string $class) use (&$deprecatedAutoloader): void {
    $deprecated = [
        'Codeception\\Module\\WPBrowser' => WPBrowser::class,
        'Codeception\\Module\\WPBrowserMethods' => WPBrowserMethods::class,
        'Codeception\\Module\\WPCLI' => WPCLI::class,
        'Codeception\\Module\\WPDb' => WPDb::class,
        'Codeception\\Module\\WPFilesystem' => WPFilesystem::class,
        'Codeception\\Module\\WPLoader' => WPLoader::class,
        'Codeception\\Module\\WPQueries' => WPQueries::class,
        'Codeception\\Module\\WPWebDriver' => WPWebDriver::class,
        'Codeception\\Command\\GenerateWPUnit' => GenerateWPUnit::class,
        'Codeception\\Template\\Wpbrowser' => WpbrowserInitTemplate::class,
        'Codeception\\TestCase\\WPTestCase' => WPTestCase::class,
        'Codeception\\TestCase\\WPAjaxTestCase' => WPAjaxTestCase::class,
        'Codeception\\TestCase\\WPCanonicalTestCase' => WPCanonicalTestCase::class,
        'Codeception\\TestCase\\WPRestApiTestCase' => WPRestApiTestCase::class,
        'Codeception\\TestCase\\WPRestControllerTestCase' => WPRestControllerTestCase::class,
        'Codeception\\TestCase\\WPRestPostTypeControllerTestCase' => WPRestPostTypeControllerTestCase::class,
        'Codeception\\TestCase\\WPXMLRPCTestCase' => WPXMLRPCTestCase::class,
    ];
    $countDeprecated = count($deprecated);
    static $hits = 0;

    if (isset($deprecated[$class])) {
        class_alias($deprecated[$class], $class);
        $hits++;
    }

    if ($hits === $countDeprecated) {
        // Job done, do not keep loading.
        spl_autoload_unregister($deprecatedAutoloader);
    }
};

spl_autoload_register($deprecatedAutoloader);
