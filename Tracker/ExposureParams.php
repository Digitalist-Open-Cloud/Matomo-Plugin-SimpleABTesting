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
}
