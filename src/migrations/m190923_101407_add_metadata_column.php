<?php

namespace superbig\qbankconnector\migrations;

use Craft;
use craft\db\Migration;
use superbig\qbankconnector\records\QbankConnectorRecord;

/**
 * m190923_101407_add_metadata_column migration.
 */
class m190923_101407_add_metadata_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(QbankConnectorRecord::tableName(), 'metadata', $this->longText()->after('objectHash'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190923_101407_add_metadata_column cannot be reverted.\n";

        return false;
    }
}
