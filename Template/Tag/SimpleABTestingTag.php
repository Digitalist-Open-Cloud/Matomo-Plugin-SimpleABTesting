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
use Piwik\Validators\NotEmpty;

/**
 * Publishes a stable "id,idSite" reference to one experiment — never the
 * experiment's own css/js/dates. Those are fetched live at runtime by
 * SimpleABTestingTag.web.js from Controller::getExperimentPublic(), so
 * editing an experiment takes effect on the next page load with no need to
 * re-select it here or republish. (The previous design baked a full
 * "id,name,from,to,css,js" snapshot into this setting at selection time,
 * which went stale on every edit — and Matomo's single-select field doesn't
 * detect a change when the freshly re-fetched option has the same label as
 * what's already stored, so even manually re-selecting it didn't help.)
 */
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
        $defaultOrigin = $this->detectMatomoOrigin();

        return array(
            $this->makeSetting('experiment', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
                $field->title = Piwik::translate('SimpleABTesting_TagChooseExperiment');
                $field->availableValues = $this->getExperiments();
                $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
                $field->description = Piwik::translate('SimpleABTesting_SimpleABTestingTagDescription');
                // Bypass Matomo's default re-validation of the stored value
                // against a fresh availableValues call on every save — a
                // deleted experiment must not block publishing the whole
                // container. A tag whose experiment was since deleted just
                // gets a 404 from getExperimentPublic() at runtime (no-op),
                // rather than blocking here.
                $field->validate = function ($value) {
                };
            }),
            $this->makeSetting(
                'matomoOrigin',
                $defaultOrigin,
                FieldConfig::TYPE_STRING,
                function (FieldConfig $field) use ($defaultOrigin) {
                    $field->title = 'Matomo origin';
                    $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                    $field->description = 'The origin (scheme + host + port) of your Matomo installation, '
                        . 'without a trailing slash. Used at runtime to fetch this experiment\'s current '
                        . 'CSS/JS so edits take effect without republishing.';
                    $field->inlineHelp = 'Auto-detected from this Matomo instance'
                        . ($defaultOrigin !== '' ? ': <code>' . $defaultOrigin . '</code>' : '')
                        . '. Override only if your Matomo is reachable via a different origin than the one you administer it from.';
                    $field->validators[] = new NotEmpty();
                    $field->validate = function ($value) {
                        $value = (string) $value;
                        if (!preg_match('#^https?://[a-zA-Z0-9.\-]+(:[0-9]+)?$#', $value)) {
                            throw new \Exception('Matomo origin must look like https://host[:port] with no path.');
                        }
                    };
                }
            ),
        );
    }

    public function getCategory()
    {
        return self::CATEGORY_DEVELOPERS;
    }

    /**
     * Options are keyed "id,idSite" — a reference, not a snapshot. Stable
     * for the experiment's lifetime; only changes if the experiment is
     * deleted and recreated.
     */
    private function getExperiments()
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        $sql = "SELECT id, name FROM " . Common::prefixTable('simple_ab_testing_experiments') . " WHERE idsite = ?";
        $result = Db::fetchAll($sql, [$idSite]);

        $options = [];
        foreach ($result as $experiment) {
            $options[$experiment['id'] . ',' . $idSite] = $experiment['name'];
        }
        return $options;
    }

    /**
     * Resolve the default Matomo origin so admins never have to type it
     * manually. Mirrors RebelRobTag::detectMatomoOrigin().
     */
    private function detectMatomoOrigin(): string
    {
        try {
            $url = \Piwik\SettingsPiwik::getPiwikUrl();
            if (!is_string($url) || $url === '') {
                return '';
            }
            $parts = parse_url($url);
            if (empty($parts['scheme']) || empty($parts['host'])) {
                return '';
            }
            $origin = $parts['scheme'] . '://' . $parts['host'];
            if (!empty($parts['port'])) {
                $origin .= ':' . $parts['port'];
            }
            return $origin;
        } catch (\Throwable $e) {
            return '';
        }
    }
}
