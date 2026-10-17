<?php

namespace Piwik\Plugins\SimpleABTesting\Tracker;

/**
 * Validates the experiment tracking parameters of a request. Everything the
 * visitor's browser sends is untrusted: anything malformed becomes null.
 */
class ExposureParams
{
    /**
     * @return array{variant: ?int, idexperiment: ?int, experiment_name: string, assignment_id: ?string}|null
     *         null when the request carries no sabt parameter at all
     */
    public static function fromRequestParams(array $params): ?array
    {
        if (!isset($params['sabt']) || $params['sabt'] === '') {
            return null;
        }

        $variant = in_array((string) $params['sabt'], ['1', '2'], true) ? (int) $params['sabt'] : null;

        $id = $params['sabi'] ?? null;
        $idexperiment = (is_string($id) || is_int($id)) && preg_match('/^[1-9][0-9]{0,9}$/', (string) $id)
            ? (int) $id : null;

        $name = 'Unknown';
        foreach (['sabn', 'experiment'] as $key) {
            if (isset($params[$key]) && is_string($params[$key]) && $params[$key] !== '') {
                $name = mb_substr($params[$key], 0, 255);
                break;
            }
        }

        $assignment = $params['saba'] ?? null;
        $assignmentId = is_string($assignment) && preg_match('/^[0-9a-f]{32}$/', $assignment) ? $assignment : null;

        return [
            'variant' => $variant,
            'idexperiment' => $idexperiment,
            'experiment_name' => $name,
            'assignment_id' => $assignmentId,
        ];
    }

    /**
     * Resolves the experiment id without trusting the request: a supplied id
     * is kept only if it exists for the request's site, otherwise the
     * (site-scoped) name lookup decides.
     *
     * @param callable(int): bool $existsForSite
     * @param callable(string): ?int $idByName
     */
    public static function resolveExperimentId(?int $sabi, string $name, callable $existsForSite, callable $idByName): ?int
    {
        if ($sabi !== null && $existsForSite($sabi)) {
            return $sabi;
        }
        if ($name !== 'Unknown') {
            return $idByName($name);
        }
        return null;
    }

    /**
     * Whether a request at $timestamp falls inside the experiment row's
     * schedule [from_date, to_date], compared as UTC days (the tag uses UTC
     * too). A concluded test has its to_date moved back, but a returning
     * visitor's stored assignment keeps sending the old id until it expires
     * in the browser; such requests must not count for the test.
     *
     * @param array{from_date: string, to_date: string}|null $row
     */
    public static function isWithinSchedule(int $timestamp, ?array $row): bool
    {
        if ($row === null) {
            return false;
        }
        $day = gmdate('Y-m-d', $timestamp);
        return $day >= (string) $row['from_date'] && $day <= (string) $row['to_date'];
    }
}
