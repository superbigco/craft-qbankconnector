<?php

namespace superbig\qbankconnector\migrations;

use Craft;
use craft\db\Migration;
use superbig\qbankconnector\records\QbankConnectorRecord;

/**
 * m190531_165208_add_media_id migration.
 */
class m190531_165208_add_media_id extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(QbankConnectorRecord::tableName(), 'mediaId', $this->integer()->after('objectId'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190531_165208_add_media_id cannot be reverted.\n";

        return false;
    }
}
