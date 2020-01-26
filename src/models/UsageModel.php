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

use craft\base\Model;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 */
class UsageModel extends Model
{
    // Public Properties
    // =========================================================================

    public $elementId;
    public $id;
    public $fileId;
    public $objectId;
    public $assetId;
    public $usageId;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['elementId', 'fileId'], 'required'],
        ];
    }
}
