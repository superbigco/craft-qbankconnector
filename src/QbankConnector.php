<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

namespace superbig\qbankconnector;

use superbig\qbankconnector\services\QbankConnectorService;
use superbig\qbankconnector\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;

/**
 * Class QbankConnector
 *
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 *
 * @property  QbankConnectorService $qbankConnectorService
 *
 * @method Settings getSettings()
 */
class QbankConnector extends Plugin
{
    use PluginTrait;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.3';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'superbig\qbankconnector\console\controllers';
        }

        $this->_setComponents();
        $this->_setEvents();
        $this->_setLogging();

        Craft::info(
            Craft::t(
                'qbank-connector',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'qbank-connector/settings',
            [
                'settings'            => $this->getSettings(),
                'availableProperties' => self::$plugin->getSearch()->getAvailableProperties(),
            ]
        );
    }
}
