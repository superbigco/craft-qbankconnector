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
 *
 * @property array    $deletedUsageIds
 * @property UserInfo $userInfo
 */
class DeleteReference extends Model
{
    public $recordId;
    public $usageId;
    public $sourceElementId;
}
