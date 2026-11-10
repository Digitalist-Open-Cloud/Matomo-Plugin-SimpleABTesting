<?php

namespace Piwik\Plugins\SimpleABTesting\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Matomo's updater instantiates an Updates_x_y_z class through the DI
 * container. An Updates file that reads $this->migration without a constructor
 * taking Piwik\Updater\Migration\Factory dies with "Call to a member function
 * sql() on null" on core:update (it was missed because a fresh install runs
 * install() instead of the migration).
 */
final class UpdatesMigrationFactoryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Minimal stand-ins for the Matomo classes, declared only when absent.
        if (!class_exists(\Piwik\Updates::class, false)) {
            eval('namespace Piwik; class Updates {}');
        }
        if (!class_exists(\Piwik\Updater::class, false)) {
            eval('namespace Piwik; class Updater {}');
        }
        if (!class_exists(\Piwik\Updater\Migration\Factory::class, false)) {
            eval('namespace Piwik\Updater\Migration; class Factory {}');
        }
    }

    /** @return array<string, array{string}> */
    public static function updatesFiles(): array
    {
        $cases = [];
        foreach (glob(__DIR__ . '/../../Updates/*.php') as $file) {
            $cases[basename($file)] = [$file];
        }
        return $cases;
    }

    /**
     * @dataProvider updatesFiles
     */
    public function testFilesUsingMigrationFactoryReceiveIt(string $file): void
    {
        $src = file_get_contents($file);
        if (strpos($src, '$this->migration') === false) {
            $this->addToAssertionCount(1);
            return;
        }
        $this->assertMatchesRegularExpression(
            '/function __construct\(\s*(\\\\?Piwik\\\\Updater\\\\Migration\\\\)?(MigrationFactory|Factory)\s+\$\w+/',
            $src,
            basename($file) . ' uses $this->migration but has no constructor taking the migration factory'
        );
    }

    public function testUpdates0198IsConstructedWithFactoryAndBuildsMigrations(): void
    {
        require_once __DIR__ . '/../../Updates/0.1.98.php';
        $class = \Piwik\Plugins\SimpleABTesting\Updates_0_1_98::class;

        $ctor = (new \ReflectionClass($class))->getConstructor();
        $this->assertNotNull($ctor, 'Updates_0_1_98 needs a constructor');
        $this->assertSame(
            \Piwik\Updater\Migration\Factory::class,
            $ctor->getParameters()[0]->getType()->getName()
        );

        $prop = (new \ReflectionClass($class))->getProperty('migration');
        $prop->setAccessible(true);
        $obj = new $class(new \Piwik\Updater\Migration\Factory());
        $this->assertInstanceOf(\Piwik\Updater\Migration\Factory::class, $prop->getValue($obj));
    }
}
