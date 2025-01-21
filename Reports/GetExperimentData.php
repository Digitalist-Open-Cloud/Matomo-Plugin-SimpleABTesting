<?php
namespace Piwik\Plugins\SimpleABTesting\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\DataTable\Filter\PrependRowsWithSummaryRow;

class GetExperimentData extends Base
{
    protected $hasSubtable = true;
    protected $actionToLoadSubTables = 'getVariantData';

    protected function init()
    {
        parent::init();

        $this->name = 'SimpleABTesting_Experiments';
        $this->dimension = null;
        $this->documentation = Piwik::translate('SimpleABTesting_ExperimentsReportDocumentation');
        $this->subcategoryId = 'SimpleABTesting_Experiments';
        $this->order = 1;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = false;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('SimpleABTesting_Experiment'));

        $view->config->columns_to_display = [
            'label',
            'nb_visits',
            'nb_uniq_visitors'
        ];

        $view->config->show_export = true;
        $view->config->show_table = false;
        $view->config->show_all_views_icons = false;
        $view->config->subtable_controller_action = 'getVariantData';

        $view->config->show_export = true;
    }


    public function getMetrics()
    {
        $metrics = parent::getMetrics();

        $metrics['nb_visits'] = Piwik::translate('SimpleABTesting_ColumnNbVisits');
        $metrics['nb_uniq_visitors'] = Piwik::translate('SimpleABTesting_ColumnNbUniqVisitors');

        return $metrics;
    }
}