<?php

namespace Piwik\Plugins\SimpleABTesting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\SimpleABTesting\RowGrouper;

/**
 * Confirmed live against the real dev stack: a flat row per
 * (experiment_name, variant) with `variant` as a plain metric column gets
 * corrupted by Matomo's period rollup, which sums every numeric column of
 * rows sharing the same label — variant 1 and variant 2 of the same
 * experiment share a label, so a real week-period archive produced
 * variant=3 (1+2) with nb_visits summed across both variants into one
 * meaningless row. RowGrouper keeps variant (and idgoal) out of the metric
 * columns entirely by nesting them, so this class's job is exactly the
 * property these tests check: two variants of the same experiment must
 * never collapse into one entry.
 */
final class RowGrouperTest extends TestCase
{
    public function test_one_level_groups_variants_under_their_experiment(): void
    {
        $rows = [
            ['label' => 'spring-sale', 'variant' => 1, 'nb_visits' => 40],
            ['label' => 'spring-sale', 'variant' => 2, 'nb_visits' => 38],
            ['label' => 'autumn-sale', 'variant' => 1, 'nb_visits' => 12],
        ];

        $grouped = RowGrouper::groupByOneLevel($rows, 'variant', ['nb_visits']);

        $this->assertSame(
            [
                'spring-sale' => [
                    '1' => ['nb_visits' => 40],
                    '2' => ['nb_visits' => 38],
                ],
                'autumn-sale' => [
                    '1' => ['nb_visits' => 12],
                ],
            ],
            $grouped
        );
    }

    public function test_one_level_carries_multiple_metric_columns(): void
    {
        $rows = [
            ['label' => 'x', 'variant' => 1, 'nb_visits' => 5, 'nb_unique_visitors' => 4],
        ];

        $grouped = RowGrouper::groupByOneLevel($rows, 'variant', ['nb_visits', 'nb_unique_visitors']);

        $this->assertSame(['x' => ['1' => ['nb_visits' => 5, 'nb_unique_visitors' => 4]]], $grouped);
    }

    public function test_no_rows_produces_empty_tree(): void
    {
        $this->assertSame([], RowGrouper::groupByOneLevel([], 'variant', ['nb_visits']));
    }

    public function test_two_levels_nests_goal_under_variant_under_experiment(): void
    {
        $rows = [
            ['label' => 'spring-sale', 'variant' => 2, 'idgoal' => 1, 'nb_visits_converted' => 3, 'nb_conversions' => 4],
            ['label' => 'spring-sale', 'variant' => 2, 'idgoal' => 2, 'nb_visits_converted' => 1, 'nb_conversions' => 1],
            ['label' => 'spring-sale', 'variant' => 1, 'idgoal' => 1, 'nb_visits_converted' => 2, 'nb_conversions' => 2],
        ];

        $grouped = RowGrouper::groupByTwoLevels(
            $rows,
            'variant',
            'idgoal',
            ['nb_visits_converted', 'nb_conversions']
        );

        $this->assertSame(
            [
                'spring-sale' => [
                    '2' => [
                        '1' => ['nb_visits_converted' => 3, 'nb_conversions' => 4],
                        '2' => ['nb_visits_converted' => 1, 'nb_conversions' => 1],
                    ],
                    '1' => [
                        '1' => ['nb_visits_converted' => 2, 'nb_conversions' => 2],
                    ],
                ],
            ],
            $grouped
        );
    }
}
