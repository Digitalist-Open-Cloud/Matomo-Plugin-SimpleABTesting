<?php declare(strict_types=1);

namespace Piwik\Plugins\SimpleABTesting\tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Tests\Framework\Fixture;
use Piwik\Plugins\SimpleABTesting\Reports\GetExperimentData;
use Piwik\Plugin\ViewDataTable;
use Piwik\ViewDataTable\Config as ViewDataTableConfig;
use Piwik\ViewDataTable\RequestConfig;

/**
 * @group SimpleABTesting
 * @group SimpleABTesting_Reports
 * @group Plugins
 */
#[CoversClass(GetExperimentData::class)]
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

    public function test_reportFilters()
    {
        $report = new GetExperimentData();

        $view = $this->getMockBuilder(ViewDataTable::class)
        ->disableOriginalConstructor()
        ->getMock();

        $config = new ViewDataTableConfig();
        $config->columns_to_display = [];

        $view->config = $config;

        $report->configureView($view);

    // Test that filters array exists
        $this->assertIsArray($view->config->filters);
    }

    public function test_reportTranslations()
    {
        $report = new GetExperimentData();

        $view = $this->getMockBuilder(ViewDataTable::class)
        ->disableOriginalConstructor()
        ->getMock();

        $config = new ViewDataTableConfig();
        $translations = [];
        $config->translations = $translations;

        $view->config = $config;

        $report->configureView($view);

        // Verify translations are set
        $this->assertArrayHasKey('label', $view->config->translations);
        $this->assertEquals('SimpleABTesting_Experiment', $view->config->translations['label']);
    }


    public function test_reportDefaultSorting()
    {
        $report = new GetExperimentData();

        $view = $this->getMockBuilder(ViewDataTable::class)
        ->disableOriginalConstructor()
        ->getMock();

        $config = new ViewDataTableConfig();
        $view->config = $config;
        $view->requestConfig = new RequestConfig();

        $report->configureView($view);

        //var_dump($view->requestConfig);

        // Test default sorting
        $this->assertEquals(false, $view->requestConfig->filter_sort_column);
        $this->assertEquals('desc', $view->requestConfig->filter_sort_order);
    }

    public function test_reportDocumentation()
    {
        $report = new GetExperimentData();

        $reflection = new \ReflectionClass($report);
        $docProperty = $reflection->getProperty('documentation');
        $docProperty->setAccessible(true);

        $this->assertEquals('SimpleABTesting_ExperimentsReportDocumentation', $docProperty->getValue($report));
    }

    public function test_reportVisualizationSettings()
    {
        $report = new GetExperimentData();

        $view = $this->getMockBuilder(ViewDataTable::class)
        ->disableOriginalConstructor()
        ->getMock();

        $config = new ViewDataTableConfig();
        $view->config = $config;

        $report->configureView($view);

        // Test visualization settings
        $this->assertTrue($view->config->show_table);
        $this->assertTrue($view->config->show_bar_chart);
        $this->assertFalse($view->config->show_pie_chart);
        $this->assertTrue($view->config->show_insights);
    }
    public function test_reportColumnConfiguration()
    {
        $report = new GetExperimentData();

        $view = $this->getMockBuilder(ViewDataTable::class)
        ->disableOriginalConstructor()
        ->getMock();

        $config = new ViewDataTableConfig();
        $config->columns_to_display = [];
        $view->config = $config;

        $report->configureView($view);

        // Test that all required columns are present and in correct order
        $expectedColumns = ['label', 'nb_visits', 'nb_uniq_visitors'];
        $this->assertEquals($expectedColumns, $view->config->columns_to_display);
    }
    public function test_reportOrder()
    {
        $report = new GetExperimentData();

        $reflection = new \ReflectionClass($report);
        $orderProperty = $reflection->getProperty('order');
        $orderProperty->setAccessible(true);

        $this->assertEquals(1, $orderProperty->getValue($report));
    }

    public function test_reportCategory()
    {
        $report = new GetExperimentData();

        $reflection = new \ReflectionClass($report);
        $categoryProperty = $reflection->getProperty('categoryId');
        $categoryProperty->setAccessible(true);

        $this->assertEquals('SimpleABTesting_SimpleABTesting', $categoryProperty->getValue($report));
    }
}
