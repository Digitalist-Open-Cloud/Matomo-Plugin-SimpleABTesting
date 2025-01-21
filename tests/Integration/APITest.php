<?php
namespace Piwik\Plugins\SimpleABTesting\tests\Integration;

use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Tests\Framework\Fixture;
use Piwik\Date;
use Piwik\Plugins\SimpleABTesting\API;
use Piwik\Db;
use Piwik\Common;

/**
 * @group SimpleABTesting
 * @group SimpleABTesting_Integration
 * @group Plugins
 */
class APITest extends IntegrationTestCase
{
    private $idSite;
    private $api;

    public function setUp(): void
    {
        parent::setUp();

        $this->idSite = Fixture::createWebsite('2023-01-01');
        $this->api = API::getInstance();
    }

    public function test_insertExperiment()
    {
        $name = 'Test Experiment';
        $hypothesis = 'Test Hypothesis';
        $description = 'Test Description';
        $fromDate = '2023-01-01';
        $toDate = '2023-12-31';
        $cssInsert = '.test { color: red; }';
        $customJs = 'console.log("test");';

        $this->api->insertExperiment(
            $this->idSite,
            $name,
            $hypothesis,
            $description,
            $fromDate,
            $toDate,
            $cssInsert,
            $customJs
        );

        $table = Common::prefixTable('simple_ab_testing_experiments');
        $experiment = Db::fetchRow("SELECT * FROM " . $table . " WHERE idsite = ? AND name = ?", array($this->idSite, $name));

        $this->assertNotEmpty($experiment);
        $this->assertEquals($hypothesis, $experiment['hypothesis']);
        $this->assertEquals($description, $experiment['description']);
        $this->assertEquals($fromDate, $experiment['from_date']);
        $this->assertEquals($toDate, $experiment['to_date']);
        $this->assertEquals($cssInsert, $experiment['css_insert']);
        $this->assertEquals($customJs, $experiment['js_insert']);
    }

    public function test_insertExperiment_withSpecialCharacters()
    {
        $name = "Test's Experiment with & special < characters >";
        $hypothesis = "Test's hypothesis & more";

        $this->api->insertExperiment(
            $this->idSite,
            $name,
            $hypothesis,
            'description',
            '2023-01-01',
            '2023-12-31',
            '',
            ''
        );

        $table = Common::prefixTable('simple_ab_testing_experiments');
        $experiment = Db::fetchRow("SELECT * FROM " . $table . " WHERE idsite = ? AND name = ?", array($this->idSite, $name));

        $this->assertNotEmpty($experiment);
        $this->assertEquals($hypothesis, $experiment['hypothesis']);
    }

    public function test_insertExperiment_withLongText()
    {
        $longText = str_repeat('a', 1000);

        $this->api->insertExperiment(
            $this->idSite,
            'Test',
            $longText,
            $longText,
            '2023-01-01',
            '2023-12-31',
            '',
            ''
        );

        $table = Common::prefixTable('simple_ab_testing_experiments');
        $experiment = Db::fetchRow("SELECT * FROM " . $table . " WHERE idsite = ? AND name = ?", array($this->idSite, 'Test'));

        $this->assertNotEmpty($experiment);
    }

    public function test_insertExperiment_withEmptyRequiredFields()
    {
        $this->expectException(\Exception::class);

        $this->api->insertExperiment(
            $this->idSite,
            '', // Empty name
            'hypothesis',
            'description',
            '2023-01-01',
            '2023-12-31',
            '',
            ''
        );
    }

    public function test_insertExperiment_withInvalidDateFormat()
    {
        $this->expectException(\Exception::class);

        $this->api->insertExperiment(
            $this->idSite,
            'Test',
            'hypothesis',
            'description',
            'invalid-date', // Invalid date format
            '2023-12-31',
            '',
            ''
        );
    }

    public function test_insertExperiment_withInvalidCssSyntax()
    {
        $this->expectException(\Exception::class);

        $this->api->insertExperiment(
            $this->idSite,
            'Test',
            'hypothesis',
            'description',
            '2023-01-01',
            '2023-12-31',
            'invalid { css', // Invalid CSS
            ''
        );
    }

    public function test_insertExperiment_withInvalidJsSyntax()
    {
        $this->expectException(\Exception::class);

        $this->api->insertExperiment(
            $this->idSite,
            'Test',
            'hypothesis',
            'description',
            '2023-01-01',
            '2023-12-31',
            '',
            'function() { missing closing brace'
        );
    }

    public function test_deleteExperiment()
    {
        // First insert an experiment
        $name = 'Test Experiment';
        $this->api->insertExperiment(
            $this->idSite,
            $name,
            'hypothesis',
            'description',
            '2023-01-01',
            '2023-12-31',
            '',
            ''
        );

        $table = Common::prefixTable('simple_ab_testing_experiments');
        $experiment = Db::fetchRow("SELECT id FROM " . $table . " WHERE idsite = ? AND name = ?", array($this->idSite, $name));

        $this->api->deleteExperiment($experiment['id']);

        $count = Db::fetchOne("SELECT COUNT(*) FROM " . $table . " WHERE id = ?", array($experiment['id']));
        $this->assertEquals(0, $count);
    }

    public function test_deleteExperiment_nonExistent()
    {
        $this->expectException(\Exception::class);
        $this->api->deleteExperiment(99999);
    }

    public function test_deleteExperiment_withInvalidIdType()
    {
        $this->expectException(\Exception::class);
        $this->api->deleteExperiment('invalid-id');
    }

    public function test_deleteExperiment_withAssociatedData()
    {
        // First insert an experiment
        $name = 'Test Experiment';
        $this->api->insertExperiment(
            $this->idSite,
            $name,
            'hypothesis',
            'description',
            '2023-01-01',
            '2023-12-31',
            '',
            ''
        );

        $table = Common::prefixTable('simple_ab_testing_experiments');
        $experiment = Db::fetchRow("SELECT id FROM " . $table . " WHERE idsite = ? AND name = ?", array($this->idSite, $name));

        // Insert associated log data
        $logTable = Common::prefixTable('simple_ab_testing_log');
        Db::query("INSERT INTO " . $logTable . "
            (idsite, idvisitor, server_time, experiment_name, variant) VALUES
            (?, ?, ?, ?, ?)",
            array($this->idSite, bin2hex(random_bytes(8)), '2023-01-01 00:00:00', $name, '1')
        );

        // Delete experiment
        $this->api->deleteExperiment($experiment['id']);

        // Verify experiment is deleted
        $count = Db::fetchOne("SELECT COUNT(*) FROM " . $table . " WHERE id = ?", array($experiment['id']));
        $this->assertEquals(0, $count);

        // Verify associated data is also deleted
        $logCount = Db::fetchOne("SELECT COUNT(*) FROM " . $logTable . " WHERE experiment_name = ?", array($name));
        $this->assertEquals(0, $logCount);
    }

    public function test_insertExperiment_withPastStartDate()
    {
        $this->expectException(\Exception::class);

        $pastDate = Date::factory('yesterday')->toString();
        $this->api->insertExperiment(
            $this->idSite,
            'Test',
            'hypothesis',
            'description',
            $pastDate,
            '2024-01-01',
            '',
            ''
        );
    }

    public function test_insertExperiment_withHtmlInFields()
    {
    $name = '<script>alert("test")</script>Test';
    $hypothesis = '<p>Test hypothesis</p>';

    $this->api->insertExperiment(
        $this->idSite,
        $name,
        $hypothesis,
        'description',
        '2023-01-01',
        '2023-12-31',
        '',
        ''
    );

    $table = Common::prefixTable('simple_ab_testing_experiments');
    $experiment = Db::fetchRow("SELECT * FROM " . $table . " WHERE idsite = ?", array($this->idSite));

    // Verify HTML was properly escaped/stripped
    $this->assertEquals('Test', $experiment['name']);
    $this->assertEquals('Test hypothesis', $experiment['hypothesis']);
  }

  public function test_insertExperiment_withMultipleSites()
  {
      // Create a second test site
      $idSite2 = Fixture::createWebsite('2023-01-01');

      // First experiment in site 1
      $this->api->insertExperiment(
          $this->idSite,
          'Test Site 1',  // Different name
          'hypothesis 1',
          'description',
          '2023-01-01',
          '2023-12-31',
          '',
          ''
      );

      // Second experiment in site 2
      $this->api->insertExperiment(
          (int)$idSite2,
          'Test Site 2',  // Different name
          'hypothesis 2',
          'description',
          '2023-01-01',
          '2023-12-31',
          '',
          ''
      );

      $table = Common::prefixTable('simple_ab_testing_experiments');
      $count = Db::fetchOne("SELECT COUNT(*) FROM " . $table); // Count all experiments

      $this->assertEquals(2, $count);
  }
  public function test_deleteExperiment_withRunningExperiment()
  {
      // Insert experiment
      $this->api->insertExperiment(
          $this->idSite,
          'Test',
          'hypothesis',
          'description',
          Date::factory('today')->toString(),
          Date::factory('tomorrow')->toString(),
          '',
          ''
      );

      $table = Common::prefixTable('simple_ab_testing_experiments');
      $experiment = Db::fetchRow("SELECT id FROM " . $table . " WHERE idsite = ? AND name = ?", array($this->idSite, 'Test'));

      // Should not be able to delete a running experiment
      $this->expectException(\Exception::class);
      $this->api->deleteExperiment($experiment['id']);
  }

  public function test_deleteExperiment_cascadeDelete()
{
    // Insert experiment with associated data
    $name = 'Test';
    $this->api->insertExperiment(
        $this->idSite,
        $name,
        'hypothesis',
        'description',
        '2023-01-01',
        '2023-12-31',
        '',
        ''
    );

    $table = Common::prefixTable('simple_ab_testing_experiments');
    $experiment = Db::fetchRow("SELECT id FROM " . $table . " WHERE idsite = ? AND name = ?", array($this->idSite, $name));

    // Insert test data
    $logTable = Common::prefixTable('simple_ab_testing_log');
    for ($i = 0; $i < 3; $i++) {
        Db::query("INSERT INTO " . $logTable . "
            (idsite, idvisitor, server_time, experiment_name, variant) VALUES
            (?, ?, ?, ?, ?)",
            array($this->idSite, bin2hex(random_bytes(8)), '2023-01-01 00:00:00', $name, '1')
        );
    }

    // Delete experiment
    $this->api->deleteExperiment($experiment['id']);

    // Verify all associated data was deleted
    $logCount = Db::fetchOne("SELECT COUNT(*) FROM " . $logTable . " WHERE experiment_name = ?", array($name));
    $this->assertEquals(0, $logCount);
   }
}