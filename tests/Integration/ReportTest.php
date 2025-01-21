<?php
namespace Piwik\Plugins\SimpleABTesting\tests\Integration;

use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Tests\Framework\Fixture;
use Piwik\Plugins\SimpleABTesting\Reports\GetExperimentData;
use Piwik\Plugin\ViewDataTable;
use Piwik\ViewDataTable\Config as ViewDataTableConfig;

/**
 * @group SimpleABTesting
 * @group SimpleABTesting_Reports
 * @group Plugins
 */
class ReportTest extends IntegrationTestCase
{
    private $idSite;
    private $dateTime = '2023-01-01';

    public function setUp(): void
    {
        parent::setUp();
        $this->idSite = Fixture::createWebsite('2023-01-01');
    }

    public function test_reportInit()
    {
        $report = new GetExperimentData();

        $reflection = new \ReflectionClass($report);

        $nameProperty = $reflection->getProperty('name');
        $nameProperty->setAccessible(true);

        $dimensionProperty = $reflection->getProperty('dimension');
        $dimensionProperty->setAccessible(true);

        $hasSubtableProperty = $reflection->getProperty('hasSubtable');
        $hasSubtableProperty->setAccessible(true);

        $actionToLoadSubTablesProperty = $reflection->getProperty('actionToLoadSubTables');
        $actionToLoadSubTablesProperty->setAccessible(true);

        $this->assertEquals('SimpleABTesting_Experiments', $nameProperty->getValue($report));
        $this->assertNull($dimensionProperty->getValue($report));
        $this->assertTrue($hasSubtableProperty->getValue($report));
        $this->assertEquals('getVariantData', $actionToLoadSubTablesProperty->getValue($report));
    }

    public function test_reportMetrics()
    {
        $report = new GetExperimentData();
        $metrics = $report->getMetrics();

        $this->assertArrayHasKey('nb_visits', $metrics);
        $this->assertArrayHasKey('nb_uniq_visitors', $metrics);

        $this->assertEquals('SimpleABTesting_ColumnNbVisits', $metrics['nb_visits']);
        $this->assertEquals('SimpleABTesting_ColumnNbUniqVisitors', $metrics['nb_uniq_visitors']);
    }

    public function test_configureView()
    {
        $report = new GetExperimentData();

        $view = $this->getMockBuilder(ViewDataTable::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config = new ViewDataTableConfig();
        $config->columns_to_display = [];
        $config->show_search = true;
        $config->show_exclude_low_population = true;

        $view->config = $config;

        $report->configureView($view);

        $this->assertFalse($view->config->show_search);
        $this->assertFalse($view->config->show_exclude_low_population);
        $this->assertContains('label', $view->config->columns_to_display);
        $this->assertContains('nb_visits', $view->config->columns_to_display);
        $this->assertContains('nb_uniq_visitors', $view->config->columns_to_display);
        $this->assertEquals('getVariantData', $view->config->subtable_controller_action);
    }
}