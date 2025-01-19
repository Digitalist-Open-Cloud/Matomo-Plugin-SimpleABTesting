<?php
namespace Piwik\Plugins\SimpleABTesting\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetVariantData extends Report
{
    protected function init()
    {
        parent::init();

        $this->name = 'SimpleABTesting_Variants';
        $this->dimension = null;
        $this->documentation = Piwik::translate('SimpleABTesting_VariantsReportDocumentation');
        $this->categoryId = 'SimpleABTesting_Experiments';
        $this->subcategoryId = 'SimpleABTesting_Experiments';
        $this->order = 2;
        $this->isSubtableReport = true;
        $this->actionToLoadSubTables = 'getVariantData';
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = false;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('SimpleABTesting_Variant'));

        $view->config->columns_to_display = [
            'label',
            'nb_visits',
            'nb_uniq_visitors'
        ];

        $view->requestConfig->filter_sort_column = 'nb_visits';
        $view->requestConfig->filter_sort_order = 'desc';

        // Enable subtable features
        $view->config->show_expand_datatable_icon = true;
        $view->config->show_embedded_subtable = true;
        $view->config->filters[] = function($dataTable) {
            $dataTable->enableRecursiveFilters();
        };
    }

    public function getMetrics()
    {
        $metrics = parent::getMetrics();

        $metrics['nb_visits'] = Piwik::translate('SimpleABTesting_ColumnNbVisits');
        $metrics['nb_uniq_visitors'] = Piwik::translate('SimpleABTesting_ColumnNbUniqVisitors');

        return $metrics;
    }
}