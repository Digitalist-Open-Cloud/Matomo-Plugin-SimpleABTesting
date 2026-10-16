<?php

namespace Piwik\Plugins\SimpleABTesting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\SimpleABTesting\Tracker\ExposureParams;

final class ExposureParamsTest extends TestCase
{
    public function testFullTagRequest(): void
    {
        $r = ExposureParams::fromRequestParams([
            'sabt' => '2', 'sabi' => '7', 'sabn' => 'Knapp grön', 'saba' => str_repeat('a', 32),
        ]);
        $this->assertSame(2, $r['variant']);
        $this->assertSame(7, $r['idexperiment']);
        $this->assertSame('Knapp grön', $r['experiment_name']);
        $this->assertSame(str_repeat('a', 32), $r['assignment_id']);
    }

    public function testLegacyTrackerRequestFallsBackToExperimentName(): void
    {
        $r = ExposureParams::fromRequestParams(['sabt' => '1', 'experiment' => 'test']);
        $this->assertSame(1, $r['variant']);
        $this->assertNull($r['idexperiment']);
        $this->assertSame('test', $r['experiment_name']);
        $this->assertNull($r['assignment_id']);
    }

    public function testTamperedValuesBecomeNull(): void
    {
        $r = ExposureParams::fromRequestParams(['sabt' => '3', 'sabi' => '7; DROP', 'saba' => 'ZZ']);
        $this->assertNull($r['variant']);
        $this->assertNull($r['idexperiment']);
        $this->assertNull($r['assignment_id']);
        $this->assertSame('Unknown', $r['experiment_name']);
    }

    public function testNoSabtMeansNoRow(): void
    {
        $this->assertNull(ExposureParams::fromRequestParams([]));
    }

    public function testResolveKeepsSabiThatExistsForSite(): void
    {
        $this->assertSame(7, ExposureParams::resolveExperimentId(7, 'x', fn($id) => true, fn($n) => 99));
    }

    public function testResolveFallsBackToNameWhenSabiBelongsToOtherSite(): void
    {
        $this->assertSame(5, ExposureParams::resolveExperimentId(7, 'x', fn($id) => false, fn($n) => 5));
    }

    public function testResolveReturnsNullWhenBothUnknown(): void
    {
        $this->assertNull(ExposureParams::resolveExperimentId(7, 'x', fn($id) => false, fn($n) => null));
        $this->assertNull(ExposureParams::resolveExperimentId(null, 'Unknown', fn($id) => true, fn($n) => 5));
    }
}
