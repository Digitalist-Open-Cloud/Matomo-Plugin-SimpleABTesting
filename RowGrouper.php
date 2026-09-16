<?php

namespace Piwik\Plugins\SimpleABTesting;

/**
 * Turns flat Db::fetchAll() rows into a nested tree, keyed by `label` and
 * then by each identity column in turn, with only the metric columns as
 * leaf values. Deliberately plain arrays, not Matomo\DataTable — this class
 * has no Matomo dependency at all, so the one property that matters (two
 * different identity values, e.g. two variants of the same experiment,
 * never collapse into one entry) is directly unit-testable. Archiver.php
 * converts the result into real DataTable/Row objects with subtables.
 */
final class RowGrouper
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param string[] $metricColumns
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function groupByOneLevel(array $rows, string $identityColumn, array $metricColumns): array
    {
        $tree = [];
        foreach ($rows as $row) {
            $label = (string) $row['label'];
            $identity = (string) $row[$identityColumn];
            $tree[$label][$identity] = self::extractMetrics($row, $metricColumns);
        }
        return $tree;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param string[] $metricColumns
     * @return array<string, array<string, array<string, array<string, mixed>>>>
     */
    public static function groupByTwoLevels(
        array $rows,
        string $outerIdentityColumn,
        string $innerIdentityColumn,
        array $metricColumns
    ): array {
        $tree = [];
        foreach ($rows as $row) {
            $label = (string) $row['label'];
            $outer = (string) $row[$outerIdentityColumn];
            $inner = (string) $row[$innerIdentityColumn];
            $tree[$label][$outer][$inner] = self::extractMetrics($row, $metricColumns);
        }
        return $tree;
    }

    /**
     * @param array<string, mixed> $row
     * @param string[] $metricColumns
     * @return array<string, mixed>
     */
    private static function extractMetrics(array $row, array $metricColumns): array
    {
        $metrics = [];
        foreach ($metricColumns as $column) {
            $metrics[$column] = $row[$column];
        }
        return $metrics;
    }
}
