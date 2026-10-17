<?php

namespace Piwik\Plugins\SimpleABTesting\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every write/read of an experiment row is scoped to one site: the API checks
 * admin access for that site, the Dao filters on id AND idsite and never moves
 * a row to another site, and the Controller's write actions check admin access
 * for the site before calling the API. Source-reading test (no Matomo
 * bootstrap needed), like PublicApiAccessTest.
 */
final class TenantScopeTest extends TestCase
{
    private function source(string $file): string
    {
        return file_get_contents(__DIR__ . '/../../' . $file);
    }

    private function methodBody(string $method, string $file): string
    {
        $this->assertSame(
            1,
            preg_match('/function ' . $method . '\([^)]*\)[^{]*\{(.*?)\n    \}\n/s', $this->source($file), $m),
            "method $method not found in $file"
        );
        return $m[1];
    }

    public static function apiMethods(): array
    {
        return [['insertExperiment'], ['updateExperiment'], ['getExperiment'], ['deleteExperiment']];
    }

    #[DataProvider('apiMethods')]
    public function testApiMethodChecksAdminAccessForTheSite(string $method): void
    {
        $body = $this->methodBody($method, 'API.php');
        $this->assertStringContainsString('Piwik::checkUserHasAdminAccess($idSite)', $body);
        $this->assertStringNotContainsString('checkUserHasSomeAdminAccess', $body);
    }

    public function testApiDeleteTakesTheSite(): void
    {
        $this->assertMatchesRegularExpression(
            '/public function deleteExperiment\(int \$id, int \$idSite\)/',
            $this->source('API.php')
        );
        $this->assertStringContainsString('deleteExperiment($id, $idSite)', $this->methodBody('deleteExperiment', 'API.php'));
    }

    public function testDaoDeleteFiltersOnSite(): void
    {
        $body = $this->methodBody('deleteExperiment', 'Dao/Experiments.php');
        $this->assertStringContainsString('WHERE id = ? AND idsite = ?', $body);
        $this->assertStringContainsString('[$id, $idSite]', $body);
    }

    public function testDaoUpdateFiltersOnSiteAndNeverMovesTheRow(): void
    {
        $body = $this->methodBody('updateExperiment', 'Dao/Experiments.php');
        $this->assertStringContainsString('WHERE id = ? AND idsite = ?', $body);
        $this->assertStringNotContainsString('idsite = ?, ', $body);
    }

    public static function controllerWriteActions(): array
    {
        return [['addExperiment'], ['updateExperiment'], ['delete']];
    }

    #[DataProvider('controllerWriteActions')]
    public function testControllerWriteActionChecksSiteAdminAccess(string $action): void
    {
        $body = $this->methodBody($action, 'Controller.php');
        $this->assertMatchesRegularExpression('/\$this->securityChecks\(\$idSite\)/', $body);
        $this->assertStringContainsString(
            'Piwik::checkUserHasAdminAccess($idSite)',
            $this->methodBody('securityChecks', 'Controller.php')
        );
    }

    public function testControllerDeletePassesTheSite(): void
    {
        $this->assertStringContainsString('deleteExperiment($id, $idSite)', $this->methodBody('delete', 'Controller.php'));
    }
}
