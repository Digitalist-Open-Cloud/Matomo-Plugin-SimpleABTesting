<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SimpleABTesting;

use Piwik\Piwik;
use Piwik\Common;
use Piwik\Url;
use Piwik\Plugins\SimpleABTesting\API;
use Piwik\Plugins\SimpleABTesting\Helpers;
use Piwik\Request;
use Piwik\ViewDataTable\Factory;

class Controller extends \Piwik\Plugin\Controller
{
    use Helpers;

    public function __construct()
    {
        parent::__construct();

        if (!Piwik::isUserHasSomeAdminAccess()) {
            echo "Not allowed!";
            exit();
        }
    }

    /**
     * Add an experiment
     */
    public function addExperiment()
    {
        $this->securityChecks();

        $name = trim(Request::fromRequest()->getStringParameter('name', 'string'));
        $name = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        $hypothesis = trim(Request::fromRequest()->getStringParameter('hypothesis', 'string'));
        $description = trim(Request::fromRequest()->getStringParameter('description', 'string'));
        $fromDate = trim(Request::fromRequest()->getStringParameter('from_date', 'string'));
        $toDate = trim(Request::fromRequest()->getStringParameter('to_date', 'string'));
        $cssInsert = trim(Request::fromRequest()->getStringParameter('css_insert', 'string'));
        $customJs = trim(Request::fromRequest()->getStringParameter('js_insert', 'string'));
        $idSite = trim(Request::fromRequest()->getIntegerParameter('idSite', 0));
        $redirectUrl = $_POST['redirect_url'] . "&message=Experiment%20Created";

        $api = new API();
        try {
            $api->insertExperiment(
                $idSite,
                $name,
                $hypothesis,
                $description,
                $fromDate,
                $toDate,
                $cssInsert,
                $customJs
            );
            Url::redirectToUrl($redirectUrl);
        } catch (\Exception $e) {
            // The name/overlap validators (Dao\Experiments) throw on an
            // invalid or overlapping experiment. Without this, that
            // exception would otherwise reach Matomo's generic error page
            // instead of the existing redirect-with-message UX this method
            // already uses on success.
            $errorRedirectUrl = $_POST['redirect_url'] . "&message=" . urlencode($e->getMessage());
            Url::redirectToUrl($errorRedirectUrl);
        }
    }

    /**
     * Update an experiment (edit).
     */
    public function updateExperiment()
    {
        $this->securityChecks();

        $id = trim(Request::fromRequest()->getIntegerParameter('id', 0));
        $idSite = trim(Request::fromRequest()->getIntegerParameter('idSite', 0));
        $name = trim(Request::fromRequest()->getStringParameter('name', 'string'));
        $name = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        $hypothesis = trim(Request::fromRequest()->getStringParameter('hypothesis', 'string'));
        $description = trim(Request::fromRequest()->getStringParameter('description', 'string'));
        $fromDate = trim(Request::fromRequest()->getStringParameter('from_date', 'string'));
        $toDate = trim(Request::fromRequest()->getStringParameter('to_date', 'string'));
        $cssInsert = trim(Request::fromRequest()->getStringParameter('css_insert', 'string'));
        $customJs = trim(Request::fromRequest()->getStringParameter('js_insert', 'string'));
        $redirectUrl = $_POST['redirect_url'];

        $api = new API();
        try {
            $api->updateExperiment(
                $id,
                $idSite,
                $name,
                $hypothesis,
                $description,
                $fromDate,
                $toDate,
                $cssInsert,
                $customJs
            );
            Url::redirectToUrl($redirectUrl . "&message=Experiment%20Updated");
        } catch (\Exception $e) {
            $errorRedirectUrl = $redirectUrl . "&message=" . urlencode($e->getMessage());
            Url::redirectToUrl($errorRedirectUrl);
        }
    }

    /**
     * Renders a dedicated full page for editing a single experiment.
     * Reached via a plain link (not a modal) from the Edit action in
     * experiments.twig; submits back to updateExperiment() above, same
     * pattern as Goals' "Manage Goals" admin page.
     */
    public function editExperimentForm()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $idSite = Common::getRequestVar('idSite', 0, 'int');
        $id = Common::getRequestVar('id', 0, 'int');
        $period = Common::getRequestVar('period', 'day', 'string');
        $date = Common::getRequestVar('date', 'today', 'string');

        $backUrl = $this->getCustomUrl($period, $date, 'SimpleABTesting_SimpleABTesting', 'SimpleABTesting_ExistingExperiments');

        $api = new API();
        $experiment = $api->getExperiment($id, $idSite);

        if ($experiment) {
            $experiment['css_insert'] = Common::unsanitizeInputValues($experiment['css_insert']);
            $experiment['js_insert'] = Common::unsanitizeInputValues($experiment['js_insert']);
        }

        $updateUrl = Url::getCurrentQueryStringWithParametersModified([
            'module' => 'SimpleABTesting',
            'action' => 'updateExperiment',
        ]);

        $nonce = \Piwik\Nonce::getNonce('SimpleABTesting.index');
        $message = trim(Request::fromRequest()->getStringParameter('message', ''));

        return $this->renderTemplate('edit', [
            'experiment' => $experiment,
            'idSite' => $idSite,
            'updateUrl' => $updateUrl,
            'redirectUrl' => $backUrl,
            'backUrl' => $backUrl,
            'nonce' => $nonce,
            'message' => $message,
        ]);
    }

    /**
     * Delete an experiment.
     */
    public function delete()
    {
        $this->securityChecks();

        $redirectUrl = $_POST['redirect_url'];
        $id = trim(Request::fromRequest()->getIntegerParameter('id', 0));

        $api = new API();
        $api->deleteExperiment($id);
        Url::redirectToUrl($redirectUrl);
    }

    private function securityChecks()
    {
        $nonce = Common::getRequestVar('nonce', false);
        //$nonce = trim(Request::fromRequest()->getStringParameter('nonce', 'string'));

        if ($_SERVER["REQUEST_METHOD"] != "POST" || !\Piwik\Nonce::verifyNonce('SimpleABTesting.index', $nonce)) {
            echo "Not allowed. You can go to the <a href='/'>Dashboard / Home</a>.";
            exit();
        }
    }

    public function getExperimentReport($fetch = false)
    {
        Piwik::checkUserHasSomeViewAccess();
        // Build the ViewDataTable object
        //
        // This is the actual live render path — Matomo's controller dispatch
        // prefers this action over Reports/GetExperimentReport.php's own
        // configureView() whenever both exist. variant is now a subtable row
        // (Archiver::buildOneLevelTable), not a flat column — expand an
        // experiment's row to see nb_visits per variant. nb_unique_visitors
        // is a separate record (Archiver::RECORD_NAME_UNIQUE_VISITORS) not
        // read by this report at all: it is day-only and does not roll up
        // into week/month totals (see Archiver::recordNamesForMultiPeriod),
        // so this report — which spans arbitrary periods — no longer claims
        // to show it. Keep this in sync with GetExperimentReport::configureView().
        //
        // The third Factory::build() argument pins this view's own
        // self_url/reload target ("controllerAction") to getExperimentReport.
        // Without it, ViewDataTable::__construct() defaults controllerAction
        // to the api action ('getExperimentData'), so every interactive
        // reload of THIS top-level table (sort, page, flatten toggle) would
        // re-render via getExperimentData() below instead — which sets its
        // own "Variant" column translation, wrong at this level. Subtable
        // expand still correctly routes to getExperimentData(): see
        // Reports/GetExperimentData.php's actionToLoadSubTables.
        $view = Factory::build('table', 'SimpleABTesting.getExperimentData', 'SimpleABTesting.getExperimentReport');
        $view->config->columns_to_display = ['label', 'nb_visits'];
        $view->config->addTranslation('label', Piwik::translate('SimpleABTesting_ExperimentName'));
        $view->config->addTranslation('nb_visits', Piwik::translate('SimpleABTesting_NbVisits'));

        $view->config->title = Piwik::translate('SimpleABTesting_ExperimentsReport');
        $view->config->documentation = Piwik::translate('SimpleABTesting_ReportHelpText');
        // Configure sorting options
        $view->requestConfig->filter_sort_column = 'nb_visits';
        $view->requestConfig->filter_sort_order = 'desc';

        // Render the report and return the view (fetched if required)
        return $view->render();
    }

    /**
     * Subtable action for getExperimentReport()'s expand (+) arrow.
     *
     * ViewDataTable::__construct() defaults config->subtable_controller_action
     * to the bare action name of the apiAction passed to Factory::build()
     * ('getExperimentData', split from 'SimpleABTesting.getExperimentData') —
     * so this is the action Matomo's own dataTable.js already requests on
     * expand. It was simply never defined. Same shape as getExperimentReport():
     * one experiment's variant rows (label = variant, nb_visits per variant),
     * scoped via the idSubtable request param that API::getExperimentData()
     * now forwards to Archive::createDataTableFromArchive().
     */
    public function getExperimentData($fetch = false)
    {
        Piwik::checkUserHasSomeViewAccess();

        $view = Factory::build('table', 'SimpleABTesting.getExperimentData');
        $view->config->columns_to_display = ['label', 'nb_visits'];
        $view->config->addTranslation('label', Piwik::translate('SimpleABTesting_Variant'));
        $view->config->addTranslation('nb_visits', Piwik::translate('SimpleABTesting_NbVisits'));

        $view->requestConfig->filter_sort_column = 'nb_visits';
        $view->requestConfig->filter_sort_order = 'desc';

        return $view->render();
    }
}
