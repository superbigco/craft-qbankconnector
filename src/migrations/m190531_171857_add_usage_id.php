<?php

namespace superbig\qbankconnector\migrations;

use Craft;
use craft\db\Migration;
use superbig\qbankconnector\records\QbankConnectorUsageRecord;

/**
 * m190531_171857_add_usage_id migration.
 */
class m190531_171857_add_usage_id extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(QbankConnectorUsageRecord::tableName(), 'usageId', $this->integer());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190531_171857_add_usage_id cannot be reverted.\n";

        return false;
    }
}
