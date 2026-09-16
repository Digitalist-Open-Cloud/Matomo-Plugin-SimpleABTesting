<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SimpleABTesting\Template\Tag;

use Piwik\Piwik;
use Piwik\Settings\FieldConfig;
use Piwik\Plugins\TagManager\Template\Tag\BaseTag;
use Piwik\Db;
use Piwik\Common;

class SimpleABTestingTag extends BaseTag
{
    public function getName()
    {
        return "Simple A/B Testing";
    }

    public function getIcon()
    {
        return 'plugins/SimpleABTesting/assets/simple-ab-testing.svg';
    }

    public function getParameters()
    {

        return array(
            $this->makeSetting('experiment', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
                $field->title = Piwik::translate('SimpleABTesting_TagChooseExperiment');
                ;
                $field->availableValues = $this->getExperiments();
                $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
                $field->description = Piwik::translate('SimpleABTesting_SimpleABTestingTagDescription');
                ;
                // Setting availableValues alone makes Matomo re-validate this
                // exact stored string against a FRESH getExperiments() call
                // every time this tag is re-saved — including when Tag
                // Manager copies all of a container's tags forward while
                // creating a new version, which happens even for tags the
                // user never touched. The stored value bakes in the
                // experiment's name/dates/css/js as one string (so the
                // published JS tag needs no extra runtime lookup), so editing
                // *any* of those fields, or deleting the experiment, changes
                // or removes the matching option and throws "value not
                // allowed" — which then blocks publishing the WHOLE
                // container, not just this one tag, even ones the user never
                // touched. Overriding validate() (checked before
                // availableValues, see Piwik\Settings\Setting::validateValue)
                // keeps the dropdown UI unchanged for picking a NEW value —
                // it just stops re-validating an already-stored one against
                // live experiments at all, since publishing must never be
                // blockable by an edit or deletion made somewhere else
                // entirely. A tag whose experiment was since edited or
                // deleted keeps firing with whatever dates/css/js it already
                // had baked in — stale rather than blocking; re-selecting the
                // experiment in this dropdown is still how you pick up a
                // change.
                $field->validate = function ($value) {
                    // No-op: FieldConfig::TYPE_STRING already coerces the
                    // value. Defining validate() at all is what matters here
                    // — see the comment above.
                };
            }),
        );
    }

    public function getCategory()
    {
        return self::CATEGORY_DEVELOPERS;
    }

    private function getExperiments()
    {
        $sql = "SELECT id, name, from_date, to_date, css_insert, js_insert FROM " . Common::prefixTable('simple_ab_testing_experiments');
        $result = Db::fetchAll($sql);

        $options = [];
        foreach ($result as $experiment) {
            $cssInsert = urlencode($experiment['css_insert']);
            $jsInsert = urlencode($experiment['js_insert']);

            // Create a string of concatenated values for the experiment
            $values = $experiment['id'] . ','
                . $experiment['name'] . ','
                . $experiment['from_date'] . ','
                . $experiment['to_date'] . ','
                . $cssInsert . ','
                . $jsInsert . ',';

            // Use the experiment name as the key and values as the value
            $options[$values] = $experiment['name'];
        }
        return $options;
    }
}
