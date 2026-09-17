<?php

namespace Piwik\Plugins\SimpleABTesting\Dao;

use Piwik\Common;
use Piwik\Db;
use Exception;

class Experiments
{
    public function install()
    {
        try {
            $sql = "CREATE TABLE " . Common::prefixTable('simple_ab_testing_experiments') . " (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `idsite` int(11) NOT NULL,
                  `name` varchar(255) NOT NULL,
                  `hypothesis` text,
                  `description` text,
                  `from_date` date NOT NULL,
                  `to_date` date NOT NULL,
                  `css_insert` text,
                  `js_insert` text,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unique_name` (`name`)
                  )  DEFAULT CHARSET=utf8 ";
            Db::exec($sql);
        } catch (Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }
    }

    public function insertExperiment(int $idSite, string $name, string $hypothesis, string $description, string $fromDate, string $toDate, string $cssInsert, string $customJs)
    {
        $this->assertValidAndNotOverlapping($idSite, $name, $fromDate, $toDate);

        $query = "INSERT INTO `" . Common::prefixTable('simple_ab_testing_experiments') .
        "` (idsite, name, hypothesis, description, from_date, to_date, css_insert, js_insert) " .
        "VALUES (?,?,?,?,?,?,?,?)";
        $params = [
        $idSite,
        $name,
        $hypothesis,
        $description,
        $fromDate,
        $toDate,
        $cssInsert,
        $customJs
        ];
        try {
            $db = $this->getDb();
            $db->query($query, $params);
        } catch (Exception $e) {
            if ($db->isErrNo($e, '1062')) {
                throw new Exception("An experiment named \"{$name}\" already exists. Experiment names must be unique across all sites.");
            }
            throw $e;
        }
    }

    public function updateExperiment(int $id, int $idSite, string $name, string $hypothesis, string $description, string $fromDate, string $toDate, string $cssInsert, string $customJs): void
    {
        $this->assertValidAndNotOverlapping($idSite, $name, $fromDate, $toDate, $id);

        $query = "UPDATE `" . Common::prefixTable('simple_ab_testing_experiments') . "` SET " .
            "idsite = ?, name = ?, hypothesis = ?, description = ?, from_date = ?, to_date = ?, css_insert = ?, js_insert = ? " .
            "WHERE id = ?";
        $params = [$idSite, $name, $hypothesis, $description, $fromDate, $toDate, $cssInsert, $customJs, $id];
        try {
            $db = $this->getDb();
            $db->query($query, $params);
        } catch (Exception $e) {
            if ($db->isErrNo($e, '1062')) {
                throw new Exception("An experiment named \"{$name}\" already exists. Experiment names must be unique across all sites.");
            }
            throw $e;
        }
    }

    public function getById(int $id, int $idSite): ?array
    {
        $query = "SELECT * FROM `" . Common::prefixTable('simple_ab_testing_experiments') . "` WHERE id = ? AND idsite = ?";
        $row = Db::fetchRow($query, [$id, $idSite]);
        return $row ?: null;
    }

    public function deleteExperiment(int $id): void
    {
        $query = "DELETE FROM `" . Common::prefixTable('simple_ab_testing_experiments') . "` WHERE id = ?";
        $params = [$id];
        try {
            $db = $this->getDb();
            $db->query($query, $params);
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function getDb()
    {
        return Db::get();
    }

    /**
     * @return array<int, array{from_date: string, to_date: string}>
     */
    public function getDateRangesForSite(int $idSite, ?int $excludeId = null): array
    {
        $query = "SELECT from_date, to_date FROM `" . Common::prefixTable('simple_ab_testing_experiments') . "` WHERE idsite = ?";
        $params = [$idSite];
        if ($excludeId !== null) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        return $this->getDb()->fetchAll($query, $params);
    }

    /**
     * Shared by insertExperiment() and updateExperiment() so the two rules
     * (name, schedule overlap) live in exactly one place. $excludeId is the
     * row being updated (null for a fresh insert) — it must not count as
     * "overlapping itself".
     */
    private function assertValidAndNotOverlapping(int $idSite, string $name, string $fromDate, string $toDate, ?int $excludeId = null): void
    {
        $error = \Piwik\Plugins\SimpleABTesting\Validation\ExperimentValidator::validateName($name);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        $existingRanges = $this->getDateRangesForSite($idSite, $excludeId);
        if (\Piwik\Plugins\SimpleABTesting\Validation\ExperimentValidator::hasOverlap($existingRanges, $fromDate, $toDate)) {
            throw new Exception("Site {$idSite} already has an experiment scheduled between {$fromDate} and {$toDate}. Only one experiment can run at a time per site (see the plugin's tracker.js limitation).");
        }
    }

    public function uninstall()
    {
        Db::dropTables(Common::prefixTable('simple_ab_testing_experiments'));
    }
}
