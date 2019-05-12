<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

namespace superbig\qbankconnector\migrations;

use superbig\qbankconnector\QbankConnector;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;
use superbig\qbankconnector\records\QbankConnectorRecord;
use superbig\qbankconnector\records\QbankConnectorUsageRecord;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();

            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema(QbankConnectorRecord::tableName());
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                QbankConnectorRecord::tableName(),
                [
                    'id'          => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid'         => $this->uid(),
                    'assetId'     => $this->integer()->notNull(),
                    'objectId'    => $this->integer(),
                    'objectHash'  => $this->string(),
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema(QbankConnectorUsageRecord::tableName());
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                QbankConnectorUsageRecord::tableName(),
                [
                    'id'          => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid'         => $this->uid(),
                    'fileId'      => $this->integer()->notNull(),
                    'elementId'   => $this->integer()->notNull(),
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * @return void
     */
    protected function createIndexes()
    {
        $this->createIndex(
            $this->db->getIndexName(
                QbankConnectorRecord::tableName(),
                'assetId',
                true
            ),
            QbankConnectorRecord::tableName(),
            'assetId',
            true
        );

        $this->createIndex(
            $this->db->getIndexName(
                QbankConnectorRecord::tableName(),
                'objectHash',
                false
            ),
            QbankConnectorRecord::tableName(),
            'objectHash',
            false
        );

        $this->createIndex(
            $this->db->getIndexName(
                QbankConnectorUsageRecord::tableName(),
                'fileId',
                false
            ),
            QbankConnectorUsageRecord::tableName(),
            'fileId',
            false
        );

        $this->createIndex(
            $this->db->getIndexName(
                QbankConnectorUsageRecord::tableName(),
                'elementId',
                false
            ),
            QbankConnectorUsageRecord::tableName(),
            'elementId',
            false
        );

        /*
        $this->createIndex(
            $this->db->getIndexName(
                QbankConnectorRecord::tableName(),
                'objectId',
                false
            ),
            QbankConnectorRecord::tableName(),
            'objectId',
            false
        );*/

        // Additional commands depending on the db driver
        switch ($this->driver) {
            case DbConfig::DRIVER_MYSQL:
                break;
            case DbConfig::DRIVER_PGSQL:
                break;
        }
    }

    /**
     * @return void
     */
    protected function addForeignKeys()
    {
        // Files table

        $this->addForeignKey(
            $this->db->getForeignKeyName(QbankConnectorRecord::tableName(), 'assetId'),
            QbankConnectorRecord::tableName(),
            'assetId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Usage table
        $this->addForeignKey(
            $this->db->getForeignKeyName(QbankConnectorUsageRecord::tableName(), 'fileId'),
            QbankConnectorUsageRecord::tableName(),
            'fileId',
            QbankConnectorRecord::tableName(),
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName(QbankConnectorUsageRecord::tableName(), 'elementId'),
            QbankConnectorUsageRecord::tableName(),
            'elementId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists(QbankConnectorRecord::tableName());
        $this->dropTableIfExists(QbankConnectorUsageRecord::tableName());
    }
}
