<?php

namespace Piwik\Plugins\SimpleABTesting\tests\Integration;

use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\SimpleABTesting\SimpleABTesting;
use Piwik\Plugins\SimpleABTesting\Dao\LogExperiment;
use Piwik\Plugins\SimpleABTesting\Dao\Experiments;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\SimpleABTesting\Reports\GetExperimentData;
use DI\Container;

/**
 * @group SimpleABTesting
 * @group SimpleABTestingIntegration
 * @group Plugins
 */
class SimpleABTestingTest extends IntegrationTestCase
{
    /**
     * @var SimpleABTesting
     */
    private $plugin;

    /**
     * @var Container
     */
    private $container;

    public function setUp(): void
    {
        parent::setUp();
        $this->plugin = new SimpleABTesting();
    }

    public function test_constructor_shouldInitializeDependencies()
    {
        $this->assertInstanceOf(SimpleABTesting::class, $this->plugin);
        $this->assertInstanceOf(LogExperiment::class, StaticContainer::get(LogExperiment::class));
        $this->assertInstanceOf(Experiments::class, StaticContainer::get(Experiments::class));
    }

    public function test_registerEvents_shouldReturnCorrectEvents()
    {
        $events = $this->plugin->registerEvents();

        $this->assertIsArray($events);
        $this->assertArrayHasKey('AssetManager.getStylesheetFiles', $events);
        $this->assertArrayHasKey('Report.getReportMetadata', $events);
        $this->assertEquals('getStylesheetFiles', $events['AssetManager.getStylesheetFiles']);
        $this->assertEquals('getReportMetadata', $events['Report.getReportMetadata']);
    }

    public function test_getReportMetadata_shouldAddExperimentReport()
    {
        $reports = [];
        $this->plugin->getReportMetadata($reports);

        $this->assertCount(1, $reports);
        $this->assertInstanceOf(GetExperimentData::class, $reports[0]);
    }

    public function test_getStylesheetFiles_shouldAddStylesheet()
    {
        $stylesheets = [];
        $this->plugin->getStylesheetFiles($stylesheets);

        $this->assertCount(1, $stylesheets);
        $this->assertEquals(
            "plugins/SimpleABTesting/assets/fonts/style.css",
            $stylesheets[0]
        );
    }

    public function test_isTrackerPlugin_shouldReturnTrue()
    {
        $this->assertTrue($this->plugin->isTrackerPlugin());
    }

    public function test_install_shouldCallInstallOnDependencies()
    {
        $mockExperiments = $this->createMock(Experiments::class);
        $mockLogExperiment = $this->createMock(LogExperiment::class);

        $mockExperiments->expects($this->once())
            ->method('install');
        $mockLogExperiment->expects($this->once())
            ->method('install');

        // Override container bindings for the test
        StaticContainer::getContainer()->set(Experiments::class, $mockExperiments);
        StaticContainer::getContainer()->set(LogExperiment::class, $mockLogExperiment);

        // Create new instance to use mocked dependencies
        $plugin = new SimpleABTesting();
        $plugin->install();
    }

    public function test_uninstall_shouldCallUninstallOnDependencies()
    {
        $mockExperiments = $this->createMock(Experiments::class);
        $mockLogExperiment = $this->createMock(LogExperiment::class);

        $mockExperiments->expects($this->once())
            ->method('uninstall');
        $mockLogExperiment->expects($this->once())
            ->method('uninstall');

        // Override container bindings for the test
        StaticContainer::getContainer()->set(Experiments::class, $mockExperiments);
        StaticContainer::getContainer()->set(LogExperiment::class, $mockLogExperiment);

        // Create new instance to use mocked dependencies
        $plugin = new SimpleABTesting();
        $plugin->uninstall();
    }

    public function provideContainerConfig()
    {
        return [
            Experiments::class => \DI\create(Experiments::class),
            LogExperiment::class => \DI\create(LogExperiment::class),
        ];
    }
}