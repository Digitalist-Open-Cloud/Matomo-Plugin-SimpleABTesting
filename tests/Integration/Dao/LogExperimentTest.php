<?php

namespace Piwik\Plugins\SimpleABTesting\tests\Integration\Dao;

use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\SimpleABTesting\Dao\LogExperiment;
use Piwik\Common;
use Piwik\Date;
use Piwik\Db;
use Exception;
use Piwik\Tests\Framework\Fixture;

/**
 * @group SimpleABTesting
 * @group SimpleABTestingDao
 * @group SimpleABTestingDaoLogExperiment
 * @group Plugins
 */
class LogExperimentTest extends IntegrationTestCase
{
    /**
     * @var LogExperiment
     */
    private $dao;

    /**
     * @var int
     */
    private $idSite;

    public function setUp(): void
    {
        parent::setUp();

        $this->dao = new LogExperiment();
        $this->idSite = Fixture::createWebsite('2024-01-01 00:00:00');

        // Ensure clean state
        $this->dao->install();
    }

    public function tearDown(): void
    {
        // Clean up test data
        Db::exec('TRUNCATE ' . Common::prefixTable('simple_ab_testing_log'));

        parent::tearDown();
    }

    public function test_install_shouldCreateTable()
    {
        // Verify table exists
        $tables = Db::fetchAll("SHOW TABLES LIKE '" . Common::prefixTable('simple_ab_testing_log') . "'");
        $this->assertCount(1, $tables);

        // Verify table structure
        $columns = Db::fetchAll("SHOW COLUMNS FROM " . Common::prefixTable('simple_ab_testing_log'));
        $columnNames = array_column($columns, 'Field');

        $expectedColumns = [
            'idlog',
            'idsite',
            'idvisit',
            'idvisitor',
            'experiment_name',
            'variant',
            'server_time',
            'created_time',
            'idaction_url',
            'idaction_name',
            'idgoal',
            'category'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columnNames);
        }
    }


}