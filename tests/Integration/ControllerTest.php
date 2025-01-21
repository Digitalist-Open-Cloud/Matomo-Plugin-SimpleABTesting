<?php
namespace Piwik\Plugins\SimpleABTesting\tests\Integration;

use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Tests\Framework\Fixture;
use Piwik\Date;
use Piwik\Plugins\SimpleABTesting\Controller;
use Piwik\Common;
use Piwik\Request;
use Piwik\Nonce;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Access;
use Exception;
use Piwik\Db;
use Piwik\Piwik;

/**
 * @group SimpleABTesting
 * @group SimpleABTesting_Controller
 * @group Plugins
 */
class ControllerTest extends IntegrationTestCase
{
    private $idSite;
    private $controller;

    public function setUp(): void
    {
        parent::setUp();

        $this->idSite = Fixture::createWebsite('2023-01-01');
        Fixture::createSuperUser();

        // Set up the fake access
        FakeAccess::clearAccess();
        FakeAccess::$superUser = true;

        // Set up POST request with valid nonce
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['nonce'] = Nonce::getNonce('SimpleABTesting.index');

        $this->controller = new Controller();
    }

    public function test_addExperiment()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('If you were using a browser, Matomo would redirect you to this URL: http://example.com&message=Experiment%20Created');

        $_POST['redirect_url'] = 'http://example.com';
        $_GET['name'] = 'TestExperiment';
        $_GET['hypothesis'] = 'Test Hypothesis';
        $_GET['description'] = 'Test Description';
        $_GET['from_date'] = '2023-01-01';
        $_GET['to_date'] = '2023-12-31';
        $_GET['css_insert'] = '.test { color: red; }';
        $_GET['js_insert'] = 'console.log("test");';
        $_GET['idSite'] = $this->idSite;

        $this->controller->addExperiment();
    }

    public function test_delete()
    {
        // First create an experiment
        $_POST['redirect_url'] = 'http://example.com';
        $_GET['name'] = 'TestExperiment';
        $_GET['hypothesis'] = 'Test Hypothesis';
        $_GET['description'] = 'Test Description';
        $_GET['from_date'] = '2023-01-01';
        $_GET['to_date'] = '2023-12-31';
        $_GET['css_insert'] = '.test { color: red; }';
        $_GET['js_insert'] = 'console.log("test");';
        $_GET['idSite'] = $this->idSite;

        try {
            $this->controller->addExperiment();
        } catch (Exception $e) {
            // Expected redirect exception
        }

        // Get the ID of the created experiment
        $table = Common::prefixTable('simple_ab_testing_experiments');
        $id = Db::fetchOne("SELECT id FROM " . $table . " WHERE name = ?", array('TestExperiment'));

        // Now test deletion
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('If you were using a browser, Matomo would redirect you to this URL: http://example.com');

        $_POST['redirect_url'] = 'http://example.com';
        $_GET['id'] = $id;

        $this->controller->delete();
    }



    public function test_securityChecks_invalidMethod()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(Piwik::translate('General_ExceptionNotAllowed'));

        $this->controller->addExperiment();
    }

    public function test_securityChecks_invalidNonce()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['nonce'] = 'invalid_nonce';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(Piwik::translate('General_ExceptionNotAllowed'));

        $this->controller->addExperiment();
    }

//     public function test_securityChecks_invalidNonce()
//     {
//         $_SERVER['REQUEST_METHOD'] = 'POST';
//         $_GET['nonce'] = 'invalid_nonce';

//         $this->expectOutputString("Not allowed. You can go to the <a href='/'>Dashboard / Home</a>.");
//         $this->controller->addExperiment();
//     }
    public function provideContainerConfig()
    {
        return array(
            'Piwik\Access' => new FakeAccess()
        );
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = array();
        $_GET = array();
    }

}