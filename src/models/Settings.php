<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

namespace superbig\qbankconnector\models;

use superbig\qbankconnector\QbankConnector;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    public $clientId         = '';
    public $sessionSourceId  = '';
    public $username         = '';
    public $password         = '';
    public $defaultImageSize = 1000;
    public $qbankBaseDomain  = 'sales.qbank.se';
    public $qbankBaseUrl     = 'https://sales.qbank.se/connector/';
    public $deploymentSiteId = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['clientId', 'sessionSourceId'], 'string'],
            [['clientId', 'sessionSourceId'], 'required'],
        ];
    }
}
