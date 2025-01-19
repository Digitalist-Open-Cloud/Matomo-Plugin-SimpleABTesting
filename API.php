<?php
namespace Piwik\Plugins\SimpleABTesting;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Archive;
use Piwik\Db;
use Piwik\Plugins\SimpleABTesting\Dao\Experiments;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;

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
    public function insertExperiment(bool $idSite, string $name, string $hypothesis, string $description, string $fromDate, string $toDate, string $cssInsert, string $customJs): void
    {
        Piwik::checkUserHasSomeAdminAccess();
        $this->experiments->insertExperiment($idSite, $name, $hypothesis, $description, $fromDate, $toDate, $cssInsert, $customJs);
    }

    public function deleteExperiment(bool $id): void
    {
        Piwik::checkUserHasSomeAdminAccess();
        $this->experiments->deleteExperiment($id);
    }

    /**
     * Get experiment data
     */
    public function getExperimentData($idSite, $period, $date, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $archive = Archive::build($idSite, $period, $date, $segment);
        $dataTable = $archive->getDataTable(Archiver::EXPERIMENT_RECORD_NAME);

        // Make sure subtables are loaded
        $dataTable->enableRecursiveFilters();

        return $dataTable;
    }

    /**
     * Get variant data for a specific experiment
     */
    public function getVariantData($idSite, $period, $date, $experimentName = '', $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $archive = Archive::build($idSite, $period, $date, $segment);
        $dataTable = $archive->getDataTable(Archiver::EXPERIMENT_RECORD_NAME);

        // If an experiment name is provided, filter for that specific experiment
        if (!empty($experimentName)) {
            $dataTable->filter('Pattern', array('label', $experimentName));
        }

        // Get the subtable if it exists
        if ($dataTable->getRowsCount() > 0) {
            $row = $dataTable->getFirstRow();
            if ($row->getIdSubDataTable()) {
                return $archive->getDataTable(Archiver::EXPERIMENT_RECORD_NAME, $row->getIdSubDataTable());
            }
        }

        return new DataTable();
    }
}