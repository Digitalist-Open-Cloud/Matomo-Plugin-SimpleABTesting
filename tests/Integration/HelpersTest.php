<?php

namespace Piwik\Plugins\SimpleABTesting\tests\Integration;

use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\SimpleABTesting\Helpers;
use Piwik\Tests\Framework\Fixture;

/**
 * @group SimpleABTesting
 * @group SimpleABTestingHelpers
 * @group Plugins
 */
class HelpersTest extends IntegrationTestCase
{
    private $testClass;
    private $idSite;

    public function setUp(): void
    {
        parent::setUp();

        // Create test site
        $this->idSite = Fixture::createWebsite('2024-01-01 00:00:00', $ecommerce = 0, $siteName = 'test site', 'http://example.com');

        // Create a test class that uses the Helpers trait
        $this->testClass = new class {
            use Helpers;

            public function publicGetCustomUrl($period = null, $date = null, $category = null, $subcategory = null)
            {
                return $this->getCustomUrl($period, $date, $category, $subcategory);
            }

            public function publicGetHost($url)
            {
                return $this->getHost($url);
            }

            public function publicGetDomain($url)
            {
                return $this->getDomain($url);
            }

            public function publicGetSiteDomainFromId($idSite)
            {
                return $this->getSiteDomainFromId($idSite);
            }

            public function publicGetDb()
            {
                return $this->getDb();
            }
        };
    }

    public function test_getCustomUrl_shouldGenerateCorrectUrl()
    {
        $_GET['idSite'] = $this->idSite;
        $_GET['period'] = 'day';
        $_GET['date'] = '2024-01-01';

        $url = $this->testClass->publicGetCustomUrl();

        $this->assertStringContainsString('module=CoreHome', $url);
        $this->assertStringContainsString('action=index', $url);
        $this->assertStringContainsString('idSite=' . $this->idSite, $url);
        $this->assertStringContainsString('period=day', $url);
        $this->assertStringContainsString('category=SimpleABTesting_SimpleABTesting', $url);
        $this->assertStringContainsString('subcategory=General_Overview', $url);
        $this->assertStringContainsString('date=2024-01-01', $url);
    }

    public function test_getCustomUrl_shouldUseProvidedParameters()
    {
        $url = $this->testClass->publicGetCustomUrl(
            'week',
            '2024-01-02',
            'CustomCategory',
            'CustomSubcategory'
        );

        $this->assertStringContainsString('period=week', $url);
        $this->assertStringContainsString('date=2024-01-02', $url);
        $this->assertStringContainsString('category=CustomCategory', $url);
        $this->assertStringContainsString('subcategory=CustomSubcategory', $url);
    }

    public function test_getHost_shouldRemoveProtocol()
    {
        $testUrls = [
            'https://example.com' => 'example.com',
            'http://example.com' => 'example.com',
            'example.com' => 'example.com',
            'https://www.example.com/path' => 'www.example.com/path',
        ];

        foreach ($testUrls as $input => $expected) {
            $this->assertEquals(
                $expected,
                $this->testClass->publicGetHost($input)
            );
        }
    }

    public function test_getDomain_shouldRemoveProtocolAndWww()
    {
        $testUrls = [
            'https://example.com' => 'example.com',
            'http://www.example.com' => 'example.com',
            'www.example.com' => 'example.com',
            'example.com' => 'example.com',
            'https://www.example.com/path' => 'example.com/path',
        ];

        foreach ($testUrls as $input => $expected) {
            $this->assertEquals(
                $expected,
                $this->testClass->publicGetDomain($input)
            );
        }
    }

    public function test_getSiteDomainFromId_shouldReturnCorrectDomain()
    {
        $domain = $this->testClass->publicGetSiteDomainFromId($this->idSite);
        $this->assertEquals('example.com', $domain);
    }

    public function test_getSiteDomainFromId_shouldHandleInvalidSiteId()
    {
        $this->expectException(\Exception::class);
        $this->testClass->publicGetSiteDomainFromId(99999);
    }

    public function test_getDb_shouldReturnDbInstance()
    {
        $db = $this->testClass->publicGetDb();
        $this->assertInstanceOf(\Zend_Db_Adapter_Abstract::class, $db);
    }

    public function provideContainerConfig()
    {
        return array();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        unset($_GET['idSite']);
        unset($_GET['period']);
        unset($_GET['date']);
    }
}