<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

/**
 * QBank Connector config.php
 *
 * This file exists only as a template for the QBank Connector settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'qbank-connector.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
    // These will be supplied by QBank
    'clientId'             => '',
    'sessionSourceId'      => '',
    'username'             => '',
    'password'             => '',
    'deploymentSiteId'     => null,
    'enableForAssetFields' => true,
    'enableForAssetIndex'  => true,

    // By default 'sales.qbank.se'
    'qbankBaseDomain'      => '',
];
