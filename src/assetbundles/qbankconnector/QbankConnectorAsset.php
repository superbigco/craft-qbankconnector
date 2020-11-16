<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

namespace superbig\qbankconnector\assetbundles\qbankconnector;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 */
class QbankConnectorAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@superbig/qbankconnector/assetbundles/qbankconnector/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/QbankConnectorModal.js',
            'js/QbankConnectorFields.js',
            'js/QbankConnector.js',
        ];

        $this->css = [
            'css/QbankConnector.css',
        ];

        parent::init();
    }
}
