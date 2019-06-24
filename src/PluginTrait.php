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

use Craft;
use craft\helpers\Json;
use craft\log\FileTarget;
use craft\services\Plugins;
use craft\web\View;
use superbig\qbankconnector\assetbundles\QbankConnector\QbankConnectorAsset;
use superbig\qbankconnector\services\QbankConnectorService;
use yii\base\Event;
use yii\log\Logger;
use craft\base\Element;


/**
 * Trait PluginTrait
 *
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 *
 */
trait PluginTrait
{
    // Static Properties
    // =========================================================================

    /**
     * @var QbankConnector
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    public static function log($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'qbank-connector');
    }

    public static function error($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'qbank-connector');
    }

    /**
     * @return QbankConnectorService
     */
    public function getService()
    {
        return $this->get('qbankConnectorService');
    }

    private function _setLogging()
    {
        Craft::getLogger()->dispatcher->targets[] = new FileTarget([
            'logFile'    => Craft::getAlias('@storage/logs/qbank-connector.log'),
            'categories' => ['qbank-connector'],
        ]);
    }

    private function _setComponents()
    {
        $this->setComponents([
            'qbankConnectorService' => QbankConnectorService::class,
        ]);
    }

    private function _setEvents()
    {
        Event::on(Plugins::class, Plugins::EVENT_AFTER_LOAD_PLUGINS, function() {
            if ($this->isInstalled && !Craft::$app->plugins->doesPluginRequireDatabaseUpdate($this)) {
                Event::on(
                    Element::class,
                    Element::EVENT_AFTER_SAVE,
                    [QbankConnector::$plugin->getService(), 'onElementSave']
                );

                Event::on(
                    Element::class,
                    Element::EVENT_BEFORE_DELETE,
                    [QbankConnector::$plugin->getService(), 'onElementBeforeDelete']
                );

                if (!Craft::$app->getRequest()->getIsCpRequest() || Craft::$app->request->getAcceptsJson()) {
                    return;
                }

                $connectorJsUrl  = 'https://sales.qbank.se/connector/qbank-connector.min.js';
                $settings        = QbankConnector::$plugin->getSettings();
                $encodedSettings = Json::encode([
                    'sessionSourceId'  => $settings->sessionSourceId,
                    'deploymentSiteId' => $settings->deploymentSiteId,
                    'qbankBaseDomain'  => $settings->qbankBaseDomain,
                    'qbankBaseUrl'     => $settings->qbankBaseUrl,
                ]);
                $view            = Craft::$app->getView();
                $view->registerAssetBundle(QbankConnectorAsset::class);
                $view->registerJsFile($connectorJsUrl);

                if ($settings->enableForAssetFields) {
                    $view->registerJs("new Craft.QbankConnectorFields({$encodedSettings});");
                }

                if ($settings->enableForAssetIndex) {
                    $view->registerJs("new Craft.QbankConnector({$encodedSettings});", View::POS_END);
                }

            }
        });
    }
}