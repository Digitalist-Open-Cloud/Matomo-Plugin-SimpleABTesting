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
    private $experiments;

    public function __construct()
    {
        $this->experiments = StaticContainer::get(Experiments::class);
    }

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

    public function getExperimentData($idSite, $period, $date, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $archive = Archive::build($idSite, $period, $date, $segment);
        return $archive->getDataTable(Archiver::EXPERIMENT_RECORD_NAME);
    }

    public function getVariantData($idSite, $period, $date, $idSubtable = null, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        if ($idSubtable === null) {
            $idSubtable = Common::getRequestVar('idSubtable', 0, 'integer');
        }

        $archive = Archive::build($idSite, $period, $date, $segment);
        return $archive->getDataTable(Archiver::EXPERIMENT_RECORD_NAME, $idSubtable);
    }
}