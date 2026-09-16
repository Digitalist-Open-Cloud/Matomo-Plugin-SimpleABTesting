<?php

namespace Piwik\Plugins\SimpleABTesting\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\SimpleABTesting\Columns\ExperimentName;
use Piwik\Plugins\SimpleABTesting\Reports\Base;

class GetExperimentReport extends Base
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('SimpleABTesting_ExperimentsReport');
        $this->dimension = new ExperimentName();
        $this->metrics = [
            'nb_visits' => Piwik::translate('SimpleABTesting_NbVisits'),
            'nb_unique_visitors' => Piwik::translate('SimpleABTesting_NbUniqueVisitors'),
        ];
        $this->processedMetrics = [];
        $this->order = 1;
        $this->subcategoryId = $this->name;
        $this->documentation = Piwik::translate('SimpleABTesting_ReportHelpText');
    }

    /**
     * @param ViewDataTable $view
     */
    public function configureView(ViewDataTable $view)
    {
        $view->config->show_table = true;
        // variant is now a subtable row (Archiver::buildOneLevelTable), not a
        // flat column — expand an experiment's row to see nb_visits per
        // variant. nb_unique_visitors is a separate record
        // (RECORD_NAME_UNIQUE_VISITORS) not read by this report at all: it is
        // day-only and does not roll up into week/month totals (see
        // Archiver::recordNamesForMultiPeriod), so this report — which spans
        // arbitrary periods — no longer claims to show it.
        $view->config->columns_to_display = [
            'label', // Experiment name
            'nb_visits',
        ];

        $view->config->translations['label'] = Piwik::translate('SimpleABTesting_ExperimentName');
        $view->config->translations['nb_visits'] = Piwik::translate('SimpleABTesting_NbVisits');
        $view->config->show_footer_message = true;
    }
}
