<?php

namespace Piwik\Plugins\SimpleABTesting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\SimpleABTesting\Validation\ExperimentValidator;

/**
 * The Tag Manager tag's parameter value is a comma-joined string
 * ("name,from,to,css,js" — see Template/Tag/SimpleABTestingTag.php) that is
 * never URL-encoded on the name. A comma in the experiment name silently
 * shifts every field after it, corrupting the dates, CSS and JS the tag
 * reads at runtime — not a validation nicety, a silent-corruption bug.
 */
final class ExperimentValidatorTest extends TestCase
{
    public function test_name_with_comma_is_rejected(): void
    {
        $error = ExperimentValidator::validateName('spring sale, v2');
        $this->assertNotNull($error);
        $this->assertStringContainsString('comma', $error);
    }

    public function test_empty_name_is_rejected(): void
    {
        $error = ExperimentValidator::validateName('');
        $this->assertNotNull($error);
    }

    public function test_blank_name_is_rejected(): void
    {
        $error = ExperimentValidator::validateName('   ');
        $this->assertNotNull($error);
    }

    public function test_ordinary_name_is_accepted(): void
    {
        $this->assertNull(ExperimentValidator::validateName('spring-sale-v2'));
    }

    public function test_no_overlap_when_ranges_are_disjoint(): void
    {
        $existing = [['from_date' => '2026-01-01', 'to_date' => '2026-01-15']];
        $this->assertFalse(ExperimentValidator::hasOverlap($existing, '2026-02-01', '2026-02-15'));
    }

    public function test_overlap_when_new_range_starts_during_existing(): void
    {
        $existing = [['from_date' => '2026-01-01', 'to_date' => '2026-01-15']];
        $this->assertTrue(ExperimentValidator::hasOverlap($existing, '2026-01-10', '2026-01-20'));
    }

    public function test_overlap_when_new_range_fully_contains_existing(): void
    {
        $existing = [['from_date' => '2026-01-05', 'to_date' => '2026-01-10']];
        $this->assertTrue(ExperimentValidator::hasOverlap($existing, '2026-01-01', '2026-01-20'));
    }

    public function test_adjacent_ranges_do_not_overlap(): void
    {
        // Ends on the 15th, next one starts on the 16th — no shared day.
        $existing = [['from_date' => '2026-01-01', 'to_date' => '2026-01-15']];
        $this->assertFalse(ExperimentValidator::hasOverlap($existing, '2026-01-16', '2026-01-31'));
    }

    public function test_no_existing_ranges_means_no_overlap(): void
    {
        $this->assertFalse(ExperimentValidator::hasOverlap([], '2026-01-01', '2026-01-31'));
    }
}
