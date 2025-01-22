<?php

namespace Piwik\Plugins\SimpleABTesting\tests\Integration\Dao;

use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\SimpleABTesting\Dao\Experiments;
use Piwik\Common;
use Piwik\Date;
use Piwik\Db;
use Exception;
use Piwik\Tests\Framework\Fixture;

/**
 * @group SimpleABTesting
 * @group SimpleABTestingDao
 * @group Plugins
 */
class ExperimentsTest extends IntegrationTestCase
{
    /**
     * @var Experiments
     */
    private $dao;

    /**
     * @var int
     */
    private $idSite;

    public function setUp(): void
    {
        parent::setUp();

        $this->dao = new Experiments();
        $this->idSite = Fixture::createWebsite('2024-01-01 00:00:00');

        // Ensure clean state
        $this->dao->install();
    }

    public function tearDown(): void
    {
        // Clean up test data
        Db::exec('TRUNCATE ' . Common::prefixTable('simple_ab_testing_experiments'));

        parent::tearDown();
    }

    public function test_install_shouldCreateTable()
    {
        // Verify table exists
        $tables = Db::fetchAll("SHOW TABLES LIKE '" . Common::prefixTable('simple_ab_testing_experiments') . "'");
        $this->assertCount(1, $tables);

        // Verify table structure
        $columns = Db::fetchAll("SHOW COLUMNS FROM " . Common::prefixTable('simple_ab_testing_experiments'));
        $columnNames = array_column($columns, 'Field');

        $expectedColumns = [
            'id',
            'idsite',
            'name',
            'hypothesis',
            'description',
            'from_date',
            'to_date',
            'css_insert',
            'js_insert'  // Note: changed from custom_js to js_insert
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columnNames);
        }
    }

    public function test_insertExperiment_shouldCreateExperiment()
    {
        $experimentData = [
            'idsite' => $this->idSite,
            'name' => 'Test Experiment',
            'hypothesis' => 'Test Hypothesis',
            'description' => 'Test Description',
            'from_date' => '2024-01-01',
            'to_date' => '2024-12-31',
            'css_insert' => '.test { color: red; }',
            'js_insert' => 'console.log("test");'
        ];

        $this->dao->insertExperiment(
            $experimentData['idsite'],
            $experimentData['name'],
            $experimentData['hypothesis'],
            $experimentData['description'],
            $experimentData['from_date'],
            $experimentData['to_date'],
            $experimentData['css_insert'],
            $experimentData['js_insert']
        );

        // Verify experiment was created
        $experiment = Db::fetchRow(
            "SELECT * FROM " . Common::prefixTable('simple_ab_testing_experiments') .
            " WHERE idsite = ? AND name = ?",
            [$experimentData['idsite'], $experimentData['name']]
        );

        $this->assertNotEmpty($experiment);
        $this->assertEquals($experimentData['name'], $experiment['name']);
        $this->assertEquals($experimentData['hypothesis'], $experiment['hypothesis']);
        $this->assertEquals($experimentData['description'], $experiment['description']);
        $this->assertEquals($experimentData['css_insert'], $experiment['css_insert']);
        $this->assertEquals($experimentData['js_insert'], $experiment['js_insert']);
    }

    public function test_experiments_shouldBeStoredCorrectly()
    {
        // Insert test experiments directly using database
        Db::query(
            "INSERT INTO " . Common::prefixTable('simple_ab_testing_experiments') .
            " (idsite, name, hypothesis, description, from_date, to_date, css_insert, js_insert) VALUES" .
            " (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->idSite,
                'Test 1',
                'Test Hypothesis 1',
                'Test Description 1',
                '2024-01-01',
                '2024-12-31',
                '',
                ''
            ]
        );

        Db::query(
            "INSERT INTO " . Common::prefixTable('simple_ab_testing_experiments') .
            " (idsite, name, hypothesis, description, from_date, to_date, css_insert, js_insert) VALUES" .
            " (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->idSite,
                'Test 2',
                'Test Hypothesis 2',
                'Test Description 2',
                '2024-01-01',
                '2024-12-31',
                '',
                ''
            ]
        );

        // Verify experiments were stored correctly
        $experiments = Db::fetchAll(
            "SELECT * FROM " . Common::prefixTable('simple_ab_testing_experiments') .
            " WHERE idsite = ? ORDER BY name",
            [$this->idSite]
        );

        $this->assertCount(2, $experiments);
        $this->assertEquals('Test 1', $experiments[0]['name']);
        $this->assertEquals('Test 2', $experiments[1]['name']);
    }

    public function test_insertExperiment_shouldFailWithDuplicateName()
    {
        $this->expectException(Exception::class);

        // Insert first experiment
        $this->dao->insertExperiment(
            $this->idSite,
            'Test Experiment',
            'Test Hypothesis',
            'Test Description',
            '2024-01-01',
            '2024-12-31',
            '',
            ''
        );

        // Try to insert duplicate
        $this->dao->insertExperiment(
            $this->idSite,
            'Test Experiment', // Same name
            'Different Hypothesis',
            'Different Description',
            '2024-01-01',
            '2024-12-31',
            '',
            ''
        );
    }
}