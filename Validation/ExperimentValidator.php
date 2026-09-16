<?php

namespace Piwik\Plugins\SimpleABTesting\Validation;

/**
 * Pure validation rules for an experiment's name and schedule. No Matomo
 * calls — every rule here is testable in isolation, and Dao\Experiments
 * calls these before touching the database.
 */
final class ExperimentValidator
{
    /**
     * @return string|null An error message, or null if $name is valid.
     */
    public static function validateName(string $name): ?string
    {
        if (trim($name) === '') {
            return 'Experiment name must not be empty.';
        }
        if (strpos($name, ',') !== false) {
            // Template/Tag/SimpleABTestingTag.php concatenates
            // "name,from,to,css,js" into one Tag Manager parameter string
            // and splits it back on "," at runtime without ever URL-encoding
            // the name — a comma here silently corrupts every field after it.
            return 'Experiment name must not contain a comma.';
        }
        return null;
    }
}
