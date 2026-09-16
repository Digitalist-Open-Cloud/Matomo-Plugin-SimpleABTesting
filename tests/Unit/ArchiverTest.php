<?php

namespace Piwik\Plugins\SimpleABTesting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\SimpleABTesting\Archiver;

/**
 * Which records Matomo's period rollup (aggregateDataTableRecords) is told
 * to sum. Visits and conversions sum correctly across days (a Matomo visit
 * belongs to exactly one day, same as Matomo's own nb_visits everywhere);
 * unique VISITORS do not (the same browser across three days becomes three).
 * RECORD_NAME_UNIQUE_VISITORS must be absent here — deliberately, so Matomo
 * simply stores no multi-period blob for it rather than a wrong sum.
 */
final class ArchiverTest extends TestCase
{
    public function test_multi_period_records_exclude_unique_visitors(): void
    {
        $this->assertSame(
            [Archiver::RECORD_NAME, Archiver::RECORD_NAME_GOALS],
            Archiver::recordNamesForMultiPeriod()
        );
        $this->assertNotContains(Archiver::RECORD_NAME_UNIQUE_VISITORS, Archiver::recordNamesForMultiPeriod());
    }
}
