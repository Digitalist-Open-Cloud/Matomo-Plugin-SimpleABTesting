<?php
namespace Piwik\Plugins\SimpleABTesting\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetExperimentData extends Report
{
    protected $hasSubtable = true;
    protected $actionToLoadSubTables = 'getVariantData';

    protected function init()
    {
        parent::init();

        $this->name = 'SimpleABTesting_Experiments';
        $this->dimension = null;
        $this->documentation = Piwik::translate('SimpleABTesting_ExperimentsReportDocumentation');
        $this->categoryId = 'SimpleABTesting_Experiments';
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

        $view->config->show_expand_datatable_icon = true;
        $view->config->datatable_js_type = 'SimpleABTestingDataTable';
    }

    public function getMetrics()
    {
        $metrics = parent::getMetrics();

        $metrics['nb_visits'] = Piwik::translate('SimpleABTesting_ColumnNbVisits');
        $metrics['nb_uniq_visitors'] = Piwik::translate('SimpleABTesting_ColumnNbUniqVisitors');

        return $metrics;
    }
}