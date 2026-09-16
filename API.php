<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SimpleABTesting;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Db;
use Piwik\Plugins\SimpleABTesting\Dao\Experiments;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\Archive;

/**
 * API for plugin SimpleABTesting.
 */
class API extends \Piwik\Plugin\API
{
    /**
     * @var Experiments
     */
    private $experiments;

    public function __construct()
    {
        $this->experiments = StaticContainer::get(Experiments::class);
    }
    /**
     * Add an experiment
     */
    public function insertExperiment(int $idSite, string $name, string $hypothesis, string $description, string $fromDate, string $toDate, string $cssInsert, string $customJs): void
    {
        Piwik::checkUserHasSomeAdminAccess();
        $this->experiments->insertExperiment($idSite, $name, $hypothesis, $description, $fromDate, $toDate, $cssInsert, $customJs);
    }

    public function deleteExperiment(int $id): void
    {
        Piwik::checkUserHasSomeAdminAccess();
        $this->experiments->deleteExperiment($id);
    }

    public function updateExperiment(int $id, int $idSite, string $name, string $hypothesis, string $description, string $fromDate, string $toDate, string $cssInsert, string $customJs): void
    {
        Piwik::checkUserHasSomeAdminAccess();
        $this->experiments->updateExperiment($id, $idSite, $name, $hypothesis, $description, $fromDate, $toDate, $cssInsert, $customJs);
    }

    /**
     * Get raw (unarchived) report data for the current moment — a quick look
     * at the log table, not backed by the archiver. Now filters by the given
     * date range and site (previously ignored, per this method's own
     * long-standing @todo) and counts distinct visits, not tracking hits —
     * the exact bug Archiver::aggregateDayReport() also had.
     */
    public function getExperimentReportData(int $idSite, string $period, string $date): array
    {
        Piwik::checkUserHasViewAccess($idSite);
        $periodObj = \Piwik\Period\Factory::build($period, $date);
        $dateStart = $periodObj->getDateStart()->toString('Y-m-d 00:00:00');
        $dateEnd = $periodObj->getDateEnd()->toString('Y-m-d 23:59:59');

        $sql = "
            SELECT
                experiment_name AS `experiment_name`,
                variant AS `variant`,
                COUNT(DISTINCT idvisitor) AS `nb_unique_visitors`,
                COUNT(DISTINCT idvisit) AS `nb_visits`
            FROM " . Common::prefixTable('simple_ab_testing_log') . "
            WHERE idsite = ?
            AND server_time BETWEEN ? AND ?
            GROUP BY experiment_name, variant
        ";
        return Db::fetchAll($sql, [$idSite, $dateStart, $dateEnd]);
    }

    /**
     * Fetch experiment data from the archive blobs for reports or APIs.
     *
     * @param int $idSite The site ID.
     * @param string $period The period (e.g., 'day', 'week', 'month').
     * @param string $date The date range (e.g., 'today', 'last7', '2024-01-01').
     * @param string|null $segment The segment string (optional, default is null).
     * @return DataTable The archived experiment data grouped by experiment_name.
     */
    public function getExperimentData(int $idSite, string $period, string $date, string $segment = null): DataTable
    {
        Piwik::checkUserHasViewAccess($idSite);
        $dataTable = Archive::createDataTableFromArchive(
            Archiver::RECORD_NAME,
            $idSite,
            $period,
            $date,
            $segment
        );
        return $dataTable;
    }

    /**
     * Fetch per-goal conversion data from the archive blobs.
     *
     * @param int $idSite The site ID.
     * @param string $period The period (e.g., 'day', 'week', 'month').
     * @param string $date The date range (e.g., 'today', 'last7', '2024-01-01').
     * @param string|null $segment The segment string (optional, default is null).
     * @return DataTable The archived per-goal experiment data.
     */
    public function getExperimentGoalData(int $idSite, string $period, string $date, string $segment = null): DataTable
    {
        Piwik::checkUserHasViewAccess($idSite);
        return Archive::createDataTableFromArchive(
            Archiver::RECORD_NAME_GOALS,
            $idSite,
            $period,
            $date,
            $segment
        );
    }
}
