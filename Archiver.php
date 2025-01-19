<?php
namespace Piwik\Plugins\SimpleABTesting;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Plugin\Archiver as MatomoArchiver;
use Piwik\Metrics;

class Archiver extends MatomoArchiver
{
    const EXPERIMENT_RECORD_NAME = 'SimpleABTesting_experiments';

    public function aggregateDayReport()
    {
        $experiments = new DataTable();
        $variants = array();

        $experimentQuery = "SELECT
                experiment_name,
                COUNT(*) as nb_visits,
                COUNT(DISTINCT idvisitor) as nb_uniq_visitors
            FROM " . Common::prefixTable('simple_ab_testing_log') . "
            WHERE idsite = ?
                AND server_time >= ?
                AND server_time <= ?
            GROUP BY experiment_name";

        $bind = array(
            $this->getProcessor()->getParams()->getSite()->getId(),
            $this->getProcessor()->getParams()->getDateStart()->getDateStartUTC(),
            $this->getProcessor()->getParams()->getDateEnd()->getDateEndUTC(),
        );

        $experimentRows = \Piwik\Db::fetchAll($experimentQuery, $bind);

        foreach ($experimentRows as $row) {
            $experiments->addRow(new DataTable\Row([
                DataTable\Row::COLUMNS => [
                    'label' => $row['experiment_name'],
                    Metrics::INDEX_NB_VISITS => (int)$row['nb_visits'],
                    Metrics::INDEX_NB_UNIQ_VISITORS => (int)$row['nb_uniq_visitors']
                ]
            ]));
        }

        $variantQuery = "SELECT
                experiment_name,
                variant,
                COUNT(*) as nb_visits,
                COUNT(DISTINCT idvisitor) as nb_uniq_visitors
            FROM " . Common::prefixTable('simple_ab_testing_log') . "
            WHERE idsite = ?
                AND server_time >= ?
                AND server_time <= ?
            GROUP BY experiment_name, variant";

        $variantRows = \Piwik\Db::fetchAll($variantQuery, $bind);

        foreach ($variantRows as $row) {
            if (!isset($variants[$row['experiment_name']])) {
                $variants[$row['experiment_name']] = new DataTable();
            }

            $variants[$row['experiment_name']]->addRow(new DataTable\Row([
                DataTable\Row::COLUMNS => array(
                    'label' => $row['variant'],
                    Metrics::INDEX_NB_VISITS => (int)$row['nb_visits'],
                    Metrics::INDEX_NB_UNIQ_VISITORS => (int)$row['nb_uniq_visitors']
                )
            ]));
        }

        foreach ($experiments->getRows() as $row) {
            $experimentName = $row->getColumn('label');
            if (isset($variants[$experimentName])) {
                $row->setSubtable($variants[$experimentName]);
            }
        }

        $this->getProcessor()->insertBlobRecord(self::EXPERIMENT_RECORD_NAME, $experiments->getSerialized());
    }

    public function aggregateMultipleReports()
    {
        $columnsAggregationOperation = [
            Metrics::INDEX_NB_VISITS => 'sum',
            Metrics::INDEX_NB_UNIQ_VISITORS => 'max'
        ];

        $this->getProcessor()->aggregateDataTableRecords(
            [self::EXPERIMENT_RECORD_NAME],
            $maximumRows = 0,
            $maximumRowsInSubDataTable = 0,
            $columnToSort = null,
            $columnsAggregationOperation
        );
    }
}