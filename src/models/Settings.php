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

use craft\elements\Entry;
use craft\elements\MatrixBlock;
use craft\elements\User;
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

    public $connectionTimeout            = 10;
    public $clientId                     = '';
    public $sessionSourceId              = '';
    public $username                     = '';
    public $password                     = '';
    public $defaultImageSize             = 1000;
    public $qbankBaseDomain              = 'sales.qbank.se';
    public $qbankBaseUrl                 = 'https://sales.qbank.se/connector/';
    public $deploymentSiteId             = null;
    public $enableForAssetFields         = true;
    public $enableForAssetIndex          = true;
    public $cacheSessionId               = true;
    public $reportUsage                  = true;
    public $cacheDuration                = 60 * 30;
    public $searchableProperties         = [];
    public $elementTypesToCheckForAssets = [
        Entry::class,
        User::class,
        MatrixBlock::class,
    ];

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
