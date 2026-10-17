<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SimpleABTesting\Columns;

use Piwik\Common;
use Piwik\Columns\Dimension;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;
use Piwik\Tracker\Action;

class ExperimentName extends Dimension
{
    protected $columnName = 'experiment_name';
    protected $columnType = 'VARCHAR(255) DEFAULT NULL';
    protected $nameSingular = 'SimpleABTesting_ExperimentName';
    protected $namePlural = 'SimpleABTesting_ExperimentNames';
    protected $dbTableName = 'log_visit';
    protected $category = 'SimpleABTesting_ExperimentName';
    protected $sqlSegment = 'log_visit.experiment_name';
    protected $segmentName = 'experimentName';
    protected $acceptValues = 'The name of the A/B testing experiment';

    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        $paramValue = Common::getRequestVar('sabn', '', 'string', $request->getParams());
        if (!empty($paramValue)) {
            return $paramValue;
        }
        return false;
    }

    public function onExistingVisit(Request $request, Visitor $visitor, $action)
    {
        $paramValue = Common::getRequestVar('sabn', '', 'string', $request->getParams());
        if (!empty($paramValue)) {
            return $paramValue;
        }
        return false;
    }
}
