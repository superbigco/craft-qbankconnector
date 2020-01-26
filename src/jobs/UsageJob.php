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

use craft\helpers\Json;
use QBNK\QBank\API\Exception\RequestException;
use QBNK\QBank\API\Model\MediaUsage;
use superbig\qbankconnector\models\DeleteReference;
use superbig\qbankconnector\models\NewUsageReference;
use superbig\qbankconnector\models\UsageModel;
use superbig\qbankconnector\models\UserInfo;
use superbig\qbankconnector\QbankConnector;

use Craft;
use craft\queue\BaseJob;
use superbig\qbankconnector\records\QbankConnectorUsageRecord;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 *
 * @property NewUsageReference[] newUsageReferences
 * @property DeleteReference[]   $deleteUsageReferences
 */
class UsageJob extends BaseJob
{
    // Public Properties
    // =========================================================================

    public $userIp;
    public $userAgent;
    public $sessionId;
    public $newUsageReferences    = [];
    public $deleteUsageReferences = [];

    public function execute($queue)
    {
        $service     = QbankConnector::$plugin->getService();
        $qbankClient = $service->getQbankClient();

        if (!$this->sessionId) {
            $userInfo        = new UserInfo([
                'userIp'    => $this->userIp,
                'userAgent' => $this->userAgent,
            ]);
            $this->sessionId = $service->getSessionId($userInfo, true);
        }

        // If QBank is having problems on their end, this should fail because there is no session id
        if (!$this->sessionId) {
            $error = Craft::t('qbank-connector', 'QBank did not return a session id');

            QbankConnector::error($error);

            return false;
        }

        if ($this->hasNewUsageReferences()) {
            foreach ($this->newUsageReferences as $reference) {
                $existingUsageRecord = $service
                    ->createUsageQuery()
                    ->where([
                        'fileId'    => $reference->fileId,
                        'elementId' => $reference->sourceElementId,
                    ])
                    ->one();

                if ($existingUsageRecord) {
                    QbankConnector::error("Found existing record for {$reference->assetId} to element {$reference->sourceElementId}, skipping");

                    continue;
                }

                try {
                    // @todo Handle exception
                    $response = $qbankClient
                        ->events()
                        ->addUsage($this->sessionId, $reference->getMediaUsageForQbank());
                } catch (RequestException $e) {
                    $this->handleException($e);
                }

                QbankConnector::error("Reported usage for {$reference->assetId} to element {$reference->sourceElementId}. Got id {$response->getId()}");

                $recordCreated = $service
                    ->createUsageQuery()
                    ->createCommand()
                    ->insert(QbankConnectorUsageRecord::tableName(), [
                        'fileId'    => $reference->fileId,
                        'elementId' => $reference->sourceElementId,
                        'usageId'   => $response->getId(),
                    ])
                    ->execute();

                if (!$recordCreated) {
                    QbankConnector::error('Failed to create record for usage ' . $response->getId());
                }
            }
        }

        if ($this->hasDeleteUsageReferences()) {
            foreach ($this->deleteUsageReferences as $reference) {
                QbankConnector::error("Reporting removal of usage for {$reference->usageId} to element {$reference->sourceElementId}.");

                $existingUsageRecord = $service
                    ->createUsageQuery()
                    ->where([
                        'id' => $reference->recordId,
                    ])
                    ->one();

                if (!$existingUsageRecord) {
                    // @todo This might be because the deletion cascades when a element is deleted
                    // @todo Should this be a setting? A smarter check? Happen inline?
                    QbankConnector::error("Record for {$reference->recordId} to element {$reference->sourceElementId} does not exist anymore, skipping.");

                    continue;
                }

                try {
                    $qbankClient
                        ->events()
                        ->removeUsage($reference->usageId);
                } catch (RequestException $e) {
                    $this->handleException($e);
                }

                $service
                    ->createUsageQuery()
                    ->createCommand()
                    ->delete(QbankConnectorUsageRecord::tableName(), [
                        'id'        => [$reference->recordId],
                        'elementId' => $reference->sourceElementId,
                    ])
                    ->execute();

                QbankConnector::error("Removed record {$reference->recordId} for {$reference->usageId}.");
            }
        }
    }

    public function hasNewUsageReferences()
    {
        return !empty($this->newUsageReferences);
    }

    public function hasDeleteUsageReferences()
    {
        return !empty($this->deleteUsageReferences);
    }

    public function handleException($e)
    {
        if ($e instanceof RequestException) {
            /** @var RequestException $e */
            $message = $e->getMessage();
            $details = Json::encode($e->getDetails());

            QbankConnector::error("QBank call failed: {$message}. Details: {$details}");
        }

        throw $e;
    }

    protected function defaultDescription(): string
    {
        return Craft::t('qbank-connector', 'QBank Usage Reporting');
    }
}
