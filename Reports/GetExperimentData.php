<?php

namespace Piwik\Plugins\SimpleABTesting\Reports;

use Piwik\Piwik;
use Piwik\Plugins\SimpleABTesting\Columns\ExperimentName;

/**
 * Report metadata for the SimpleABTesting.getExperimentData API method.
 *
 * Controller::getExperimentReport()/getExperimentData() call this API method
 * directly (via Factory::build('table', 'SimpleABTesting.getExperimentData'))
 * instead of going through this class's configureView() — but Matomo's
 * DataTableManipulator (used by flatten, and by the expand-row subtable
 * loader) still looks up report metadata by module+action via
 * API.getReportMetadata, keyed on the SAME module/action the data was
 * originally fetched with, before it will recurse into a subtable. Without a
 * Report class registered for this exact action, that lookup returns empty
 * and DataTableManipulator::getApiMethodForSubtable() throws "Metadata for
 * report SimpleABTesting.getExperimentData could not be found".
 *
 * Deliberately NOT isSubtableReport: that flag only hides a report that is
 * itself someone else's subtable target from getMetadata()'s default
 * showSubtableReports=false lookup — but getExperimentData IS the report
 * DataTableManipulator looks up directly (it's both the root fetch and,
 * recursively via idSubtable, the variant-level fetch), so marking it
 * subtable-only would make this exact lookup fail again.
 *
 * actionToLoadSubTables = own action (same pattern as Actions'
 * GetOutlinks): ViewDataTable::__construct() reads this to set
 * subtable_controller_action, i.e. which controller action the expand (+)
 * arrow requests. Without it, subtable_controller_action would default to
 * whatever controllerAction Controller::getExperimentReport() passes to
 * Factory::build() for its OWN top-level reloads (getExperimentReport) —
 * which ignores idSubtable entirely and would re-render the whole report
 * instead of one experiment's variant rows.
 */
class GetExperimentData extends Base
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('SimpleABTesting_ExperimentsReport');
        $this->dimension = new ExperimentName();
        $this->metrics = [
            'nb_visits' => Piwik::translate('SimpleABTesting_NbVisits'),
        ];
        $this->actionToLoadSubTables = $this->action;
    }
}
