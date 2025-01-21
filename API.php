<?php
namespace Piwik\Plugins\SimpleABTesting;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Archive;
use Piwik\Db;
use Piwik\Date;
use Piwik\Plugins\SimpleABTesting\Dao\Experiments;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\Site;

class API extends \Piwik\Plugin\API
{
    private $experiments;

    public function __construct()
    {
        $this->experiments = StaticContainer::get(Experiments::class);
    }

    public function insertExperiment(int $idSite, string $name, string $hypothesis, string $description, string $fromDate, string $toDate, string $cssInsert, string $customJs): void
{
    Piwik::checkUserHasSomeAdminAccess();

    // Clean HTML and scripts from input fields
    $name = $this->cleanInput($name);
    $hypothesis = $this->cleanInput($hypothesis);
    $description = $this->cleanInput($description);

    // Validate required fields
    if (empty($name) || empty($hypothesis)) {
        throw new \Exception('Name and hypothesis are required fields');
    }

    // Validate site exists
    try {
        new Site($idSite);
    } catch (\Exception $e) {
        throw new \Exception('Invalid site ID');
    }

    try {
        $fromDateObj = Date::factory($fromDate);
        $toDateObj = Date::factory($toDate);
    } catch (\Exception $e) {
        throw new \Exception('Invalid date format');
    }

    if ($fromDateObj->isLater($toDateObj)) {
        throw new \Exception('From date cannot be later than to date');
    }

    if (!empty($cssInsert)) {
        if (!$this->isValidCss($cssInsert)) {
            throw new \Exception('Invalid CSS syntax');
        }
    }

    if (!empty($customJs)) {
        if (!$this->isValidJavaScript($customJs)) {
            throw new \Exception('Invalid JavaScript syntax');
        }
    }

    $table = Common::prefixTable('simple_ab_testing_experiments');
    $exists = (int)Db::fetchOne(
        "SELECT COUNT(*) FROM " . $table . " WHERE idsite = ? AND name = ?",
        array($idSite, $name)
    );

    if ($exists > 0) {
        throw new \Exception('An experiment with this name already exists for this site');
    }

    $maxLength = 256;
    if (strlen($name) > $maxLength) {
        throw new \Exception('Name fields exceed maximum length');
    }

    $this->experiments->insertExperiment($idSite, $name, $hypothesis, $description, $fromDate, $toDate, $cssInsert, $customJs);
  }

    public function deleteExperiment(bool $id): void
    {
        Piwik::checkUserHasSomeAdminAccess();
        $table = Common::prefixTable('simple_ab_testing_experiments');
        $id = intval($id);
        if ($id <= 0) {
            throw new \Exception('Invalid experiment ID');
        }
        $experiment = Db::fetchRow("SELECT * FROM " . $table . " WHERE id = ?", array($id));
        if (!$experiment) {
            throw new \Exception('Experiment does not exist');
        }
        // Check if experiment is running
        $today = Date::today()->toString();
        if ($experiment['from_date'] <= $today && $experiment['to_date'] >= $today) {
            throw new \Exception('Cannot delete a running experiment');
        }
        // Delete associated log data first
        $logTable = Common::prefixTable('simple_ab_testing_log');
        Db::query("DELETE FROM " . $logTable . " WHERE experiment_name = ?", array($experiment['name']));

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

    private function isValidCss(string $css): bool
    {
        // Basic CSS validation
        if (substr_count($css, '{') !== substr_count($css, '}')) {
            return false;
        }
        if (!preg_match('/^[^{}]+\{[^{}]*\}/', $css)) {
            return false;
        }

        return true;
    }

    private function isValidJavaScript(string $js): bool
    {
        if (empty($js)) {
            return true;
        }
        try {
            // Check for basic syntax errors
            if (strpos($js, '<script') !== false || strpos($js, '</script>') !== false) {
                return false;
            }

            $braces = 0;
            $parentheses = 0;
            for ($i = 0; $i < strlen($js); $i++) {
                switch ($js[$i]) {
                    case '{': $braces++; break;
                    case '}': $braces--; break;
                    case '(': $parentheses++; break;
                    case ')': $parentheses--; break;
                }
                if ($braces < 0 || $parentheses < 0) {
                    return false;
                }
            }
            if ($braces !== 0 || $parentheses !== 0) {
                return false;
            }

            if (!empty($js) && !preg_match('/[;{}]\s*$/', trim($js))) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function cleanInput(string $input): string
    {
        // Remove script tags and their contents
        $input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);

        // Remove remaining HTML tags
        $input = strip_tags($input);

        // Trim whitespace
        return trim($input);
    }
}