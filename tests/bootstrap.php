<?php

/**
 * Standalone bootstrap for this plugin's PURE-PHP unit tests.
 *
 * Matomo's own test harness supplies a full bootstrap, but the Matomo docker
 * image this plugin is developed against is a production build with dev
 * dependencies stripped — there is no vendor/bin/phpunit and no composer
 * autoloader to borrow. Without this, the PHP unit tests could only be
 * "verified" by reading them, which is not verification. Same approach as
 * Matomo-Plugin-RebelRob/tests/bootstrap.php.
 *
 * Scope and its limits: this autoloads the plugin's own classes and stubs a
 * small, named set of Matomo classes (see below) — just enough that the
 * plugin's own classes can be loaded and their pure logic exercised. Any test
 * that needs real Matomo behaviour (Db, Piwik::checkUserHasSomeAdminAccess,
 * the DI container) will still fail here and belongs under Matomo's own
 * harness instead. Treat a green run here as covering the deterministic pure
 * logic (validators, row grouping), not the integration surface.
 *
 * Run with: make abtest-test-php (from RebelDevEnvironment)
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Piwik\\Plugins\\SimpleABTesting\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/../' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

/**
 * Archiver.php declares `class Archiver extends \Piwik\Plugin\Archiver`.
 * This is an empty stub, not a behavioural fake: it exists only so the
 * subclass can be autoloaded, and nothing here (or under this bootstrap)
 * instantiates Archiver or calls its non-static, Matomo-dependent methods
 * (aggregateDayReport(), aggregateMultipleReports()) — those stay verified
 * live, per this suite's documented scope above.
 */
if (!class_exists(\Piwik\Plugin\Archiver::class, false)) {
    eval('namespace Piwik\Plugin; class Archiver {}');
}
