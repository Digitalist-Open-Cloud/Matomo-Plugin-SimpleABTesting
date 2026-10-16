<?php

namespace Piwik\Plugins\SimpleABTesting\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * API::getExperimentPublic must check view access and expose only the fields
 * the runtime tag needs. Source-reading test (no Matomo bootstrap needed).
 */
final class PublicApiAccessTest extends TestCase
{
    private function methodBody(string $method, string $file = 'API.php'): string
    {
        $src = file_get_contents(__DIR__ . '/../../' . $file);
        $this->assertSame(1, preg_match('/function ' . $method . '\([^)]*\)[^{]*\{(.*?)\n    \}\n/s', $src, $m), "method $method not found");
        return $m[1];
    }

    public function testPublicApiMethodChecksViewAccess(): void
    {
        $this->assertStringContainsString('checkUserHasViewAccess(', $this->methodBody('getExperimentPublic'));
    }

    public function testPublicApiMethodReturnsOnlyTagFields(): void
    {
        $body = $this->methodBody('getExperimentPublic') . $this->methodBody('publicFields');
        $this->assertStringNotContainsString('hypothesis', $body);
        $this->assertStringNotContainsString('description', $body);
        foreach (['found', 'name', 'from_date', 'to_date', 'css_insert', 'js_insert'] as $key) {
            $this->assertStringContainsString("'$key'", $body);
        }
    }

    public function testControllerEndpointReadsRowDirectlyWithoutApi(): void
    {
        $body = $this->methodBody('getExperimentPublic', 'Controller.php');
        $this->assertStringNotContainsString('new API', $body);
        $this->assertStringContainsString('getById(', $body);
    }
}
