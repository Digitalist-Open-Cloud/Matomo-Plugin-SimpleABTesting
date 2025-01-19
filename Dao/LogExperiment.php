<?php

namespace Piwik\Plugins\SimpleABTesting\Dao;

use Piwik\Common;
use Piwik\Db;
use Exception;

class LogExperiment
{
    public function install()
    {
        try {
            $sql = "CREATE TABLE " . Common::prefixTable('simple_ab_testing_log') . " (
        `idlog` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `idsite` INT UNSIGNED NOT NULL,
        `idvisit` BIGINT(10) UNSIGNED NOT NULL,
        `idvisitor` BINARY(8) NOT NULL,
        `experiment_name` VARCHAR(255) NULL,
        `variant` INT DEFAULT NULL NULL,
        `server_time` DATETIME NOT NULL,
        `created_time` DATETIME NOT NULL,
        `idaction_url` INTEGER UNSIGNED NULL,
        `idaction_name` INTEGER UNSIGNED NULL,
        `idgoal` INT DEFAULT NULL,
        `category` VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (`idlog`)
        )  DEFAULT CHARSET=utf8 ";
            Db::exec($sql);
        } catch (Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }
    }

    public function createLog($parameters)
    {
        // Map array keys to column names and placeholders
        $columns = array_keys($parameters);
        $placeHolders = array_map(fn($key) => ':' . $key, $columns);
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            Common::prefixTable('simple_ab_testing_log'),
            implode(', ', $columns),
            implode(', ', $placeHolders)
        );
        Db::query($sql, $parameters);
    }

    public function uninstall()
    {
        Db::dropTables(Common::prefixTable('simple_ab_testing_log'));
    }
}
