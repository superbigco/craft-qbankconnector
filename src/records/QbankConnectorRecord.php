<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

namespace superbig\qbankconnector\records;

use superbig\qbankconnector\QbankConnector;

use Craft;
use craft\db\ActiveRecord;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 */
class QbankConnectorRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%qbankconnector_files}}';
    }
}
