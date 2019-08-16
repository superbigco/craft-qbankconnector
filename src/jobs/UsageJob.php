<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

namespace superbig\qbankconnector\jobs;

use QBNK\QBank\API\Model\MediaUsage;
use superbig\qbankconnector\models\UsageModel;
use superbig\qbankconnector\QbankConnector;

use Craft;
use craft\queue\BaseJob;
use superbig\qbankconnector\records\QbankConnectorUsageRecord;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 *
 * @property UsageModel $usage
 * @property MediaUsage $mediaUsage
 */
class UsageJob extends BaseJob
{
    // Public Properties
    // =========================================================================

    public $sessionId;
    public $usage;
    public $mediaUsage;
    public $deleteUsageIds = [];
    public $sourceElementId;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        $service     = QbankConnector::$plugin->getService();
        $qbankClient = $service->getQbankClient();

        if (!$this->sessionId) {
            $this->sessionId   = $service->getSessionId(true);
        }

        if ($this->mediaUsage && $this->usage) {
            // @todo Handle exception
            $response = $qbankClient
                ->events()
                ->addUsage($this->sessionId, $this->mediaUsage);

            $service
                ->createUsageQuery()
                ->createCommand()
                ->insert(QbankConnectorUsageRecord::tableName(), [
                    'fileId'    => $this->usage->fileId,
                    'elementId' => $this->usage->elementId,
                    'usageId'   => $response->getId(),
                ])
                ->execute();

            return true;
        }

        if (!empty($this->deleteUsageIds)) {
            foreach ($this->deleteUsageIds as $usageId) {
                $qbankClient
                    ->events()
                    ->removeUsage($usageId);
            }

            $service
                ->createUsageQuery()
                ->createCommand()
                ->delete(QbankConnectorUsageRecord::tableName(), [
                    'id'        => \array_keys($this->deleteUsageIds),
                    'elementId' => $this->sourceElementId,
                ])
                ->execute();

            return true;
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('qbank-connector', 'QBank Usage Reporting');
    }
}
