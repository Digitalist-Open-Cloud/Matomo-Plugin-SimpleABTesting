<?php

namespace Piwik\Plugins\SimpleABTesting\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Updates0198Test extends TestCase
{
    private function src(string $file): string
    {
        return file_get_contents(__DIR__ . '/../../' . $file);
    }

    public function testMigrationAddsColumnsAndIndex(): void
    {
        $this->assertStringContainsString('class Updates_0_1_98', $this->src('Updates/0.1.98.php'));
        foreach (['Updates/0.1.98.php', 'Dao/LogExperiment.php'] as $file) {
            $src = $this->src($file);
            foreach (['idexperiment', 'assignment_id', 'idx_visit_time', '(idvisit, server_time)'] as $needle) {
                $this->assertStringContainsString($needle, $src, "$file missing $needle");
            }
        }
    }

    public function testPluginVersionIsAtLeast0198(): void
    {
        $json = json_decode($this->src('plugin.json'), true);
        $this->assertTrue(version_compare($json['version'], '0.1.98', '>='));
    }
}
