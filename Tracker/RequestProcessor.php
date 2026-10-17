<?php

namespace Piwik\Plugins\SimpleABTesting\Tracker;

use Piwik\Date;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visit\VisitProperties;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\SimpleABTesting\Dao\Experiments;
use Piwik\Plugins\SimpleABTesting\Dao\LogExperiment;
use Piwik\Tracker\RequestProcessor as MatomoRequestProcessor;
use Piwik\Common;
use Piwik\Db;

class RequestProcessor extends MatomoRequestProcessor
{
    public const TRACK_SABT = 'sabt';
    public const TRACK_EXPERIMENT = 'experiment';

    /**
     * @var LogExperiment
     */
    private $logExperiment;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LogExperiment $logExperiment, LoggerInterface $logger)
    {
        $this->logExperiment = $logExperiment;
        $this->logger = $logger;
    }


    public function onExistingVisit(&$valuesToUpdate, VisitProperties $visitProperties, Request $request)
    {

        // @todo: add logic to update visits.
    }

    public function recordLogs(VisitProperties $visitProperties, Request $request)
    {

        $exposure = ExposureParams::fromRequestParams($request->getParams());
        if ($exposure === null || $exposure['variant'] === null) {
            return;
        }

        $idSite = (int) $request->getIdSite();
        $experiments = new Experiments();
        $rows = [];
        $rowFor = function (int $id) use (&$rows, $experiments, $idSite): ?array {
            if (!array_key_exists($id, $rows)) {
                $rows[$id] = $experiments->getById($id, $idSite);
            }
            return $rows[$id];
        };
        $idExperiment = ExposureParams::resolveExperimentId(
            $exposure['idexperiment'],
            $exposure['experiment_name'],
            fn(int $id): bool => $rowFor($id) !== null,
            fn(string $name): ?int => $experiments->getIdByName($idSite, $name)
        );
        // Outside the test's schedule (e.g. after it was concluded) the
        // request is logged without an experiment id, so it never counts.
        if ($idExperiment !== null && !ExposureParams::isWithinSchedule((int) $request->getCurrentTimestamp(), $rowFor($idExperiment))) {
            $idExperiment = null;
        }

        $props = [
          'idsite' => $idSite,
          'experiment_name' => $exposure['experiment_name'],
          'idexperiment' => $idExperiment,
          'assignment_id' => $exposure['assignment_id'],
          'variant' => $exposure['variant'],
          'idvisit' => $visitProperties->getProperty('idvisit'),
          'idvisitor' => $visitProperties->getProperty('idvisitor'),
          'server_time' => date('Y-m-d H:i:s', $request->getCurrentTimestamp()),
          'created_time' => date('Y-m-d H:i:s', time()),
          'category' => 'Experiment',
          'idaction_url' => $visitProperties->getProperty('visit_exit_idaction_url') ?? $visitProperties->getProperty('visit_entry_idaction_url'),
          'idaction_name' => $visitProperties->getProperty('visit_exit_idaction_name') ?? $visitProperties->getProperty('visit_entry_idaction_name'),
        ];
        $this->logExperiment->createLog($props);
    }
}
