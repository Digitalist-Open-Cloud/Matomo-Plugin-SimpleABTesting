<?php

namespace Piwik\Plugins\SimpleABTesting\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\SimpleABTesting\Columns\ExperimentName;

/**
 * Surfaces Archiver::RECORD_NAME_GOALS (API.getExperimentGoalData) in the UI.
 *
 * Before this, the plugin's own goal-conversion-per-variant data (verified
 * correct at the data level: variant -> idgoal -> nb_visits_converted /
 * nb_conversions) was only reachable via the API, never shown in a report —
 * "Experiments Report" only ever displayed nb_visits. This is a plain,
 * Report-driven widget (no Controller override, unlike GetExperimentReport /
 * Controller::getExperimentReport()): Matomo auto-renders any Report that
 * has a subcategoryId, calling configureView() below, so there is no
 * separate self_url/controllerAction mismatch to get wrong here.
 *
 * Three levels deep: experiment -> variant -> goal. actionToLoadSubTables
 * pointing at this report's own action makes BOTH expand arrows (experiment
 * row -> its variants, variant row -> its goals) recurse through the same
 * API.getExperimentGoalData call with idSubtable set — Matomo's generic
 * subtable mechanism handles the extra depth transparently.
 */
class GetExperimentGoalData extends Base
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('SimpleABTesting_GoalsReport');
        $this->dimension = new ExperimentName();
        $this->metrics = [
            'nb_visits_converted' => Piwik::translate('SimpleABTesting_NbVisitsConverted'),
            'nb_conversions' => Piwik::translate('SimpleABTesting_NbConversions'),
        ];
        $this->processedMetrics = [];
        $this->order = 2;
        $this->subcategoryId = $this->name;
        $this->documentation = Piwik::translate('SimpleABTesting_GoalsReportHelpText');
        $this->actionToLoadSubTables = $this->action;
    }

    /**
     * @param ViewDataTable $view
     */
    public function configureView(ViewDataTable $view)
    {
        $view->config->show_table = true;
        $view->config->columns_to_display = [
            'label',
            'nb_visits_converted',
            'nb_conversions',
        ];

        $view->config->translations['label'] = Piwik::translate('SimpleABTesting_ExperimentName');
        $view->config->translations['nb_visits_converted'] = Piwik::translate('SimpleABTesting_NbVisitsConverted');
        $view->config->translations['nb_conversions'] = Piwik::translate('SimpleABTesting_NbConversions');
        $view->config->show_footer_message = true;
    }
}
