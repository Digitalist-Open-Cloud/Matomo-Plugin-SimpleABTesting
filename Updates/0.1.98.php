<?php

namespace Piwik\Plugins\SimpleABTesting;

use Piwik\Common;
use Piwik\Updater;
use Piwik\Updater\Migration;
use Piwik\Updates as PiwikUpdates;

/**
 * 0.1.98 — the log row carries the experiment id and the assignment id, and
 * gets an index for per-visit lookups.
 */
class Updates_0_1_98 extends PiwikUpdates
{
    private const DUPLICATE_COLUMN = 1060;
    private const DUPLICATE_KEY = 1061;

    public function getMigrations(Updater $updater)
    {
        $table = Common::prefixTable('simple_ab_testing_log');

        return [
            $this->migration->db->sql(
                "ALTER TABLE `$table` ADD COLUMN `idexperiment` INT NULL",
                self::DUPLICATE_COLUMN
            ),
            $this->migration->db->sql(
                "ALTER TABLE `$table` ADD COLUMN `assignment_id` CHAR(32) NULL",
                self::DUPLICATE_COLUMN
            ),
            $this->migration->db->sql(
                "ALTER TABLE `$table` ADD INDEX `idx_visit_time` (idvisit, server_time)",
                self::DUPLICATE_KEY
            ),
        ];
    }

    public function doUpdate(Updater $updater)
    {
        $updater->executeMigrations(__FILE__, $this->getMigrations($updater));
    }
}
