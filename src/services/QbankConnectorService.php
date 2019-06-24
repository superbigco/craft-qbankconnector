<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

namespace superbig\qbankconnector\services;

use craft\base\Element;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\User;
use craft\events\ModelEvent;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use QBNK\QBank\API\CachePolicy;
use QBNK\QBank\API\Credentials;
use QBNK\QBank\API\Model\MediaUsage;
use QBNK\QBank\API\QBankApi;
use superbig\qbankconnector\base\Cache;
use superbig\qbankconnector\jobs\UsageJob;
use superbig\qbankconnector\models\MediaModel;
use superbig\qbankconnector\models\UsageModel;
use superbig\qbankconnector\QbankConnector;

use Craft;
use craft\base\Component;
use superbig\qbankconnector\records\QbankConnectorRecord;
use superbig\qbankconnector\records\QbankConnectorUsageRecord;
use yii\web\BadRequestHttpException;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 */
class QbankConnectorService extends Component
{
    // Public Methods
    // =========================================================================

    public function getAssetByObjectHash($hash = null)
    {
        $query = $this
            ->_createQuery()
            ->where('objectHash = :hash', [':hash' => $hash])
            ->one();

        if (!$query) {
            return null;
        }

        return new MediaModel($query);
    }

    /**
     * @return QBankApi
     */
    public function getQbankClient()
    {
        $settings    = QbankConnector::$plugin->getSettings();
        $credentials = new Credentials($settings->clientId, $settings->username, $settings->password);
        $options     = [
            'cachePolicy' => new CachePolicy(CachePolicy::TOKEN_ONLY, 60 * 60),
            'cache'       => new Cache(),
        ];
        $qbankApi    = new QBankApi($settings->qbankBaseDomain, $credentials, $options);

        return $qbankApi;
    }

    /**
     * @param MediaModel $media
     *
     * @return bool
     * @throws \yii\base\Exception
     */
    public function downloadFile(MediaModel $media)
    {
        $settings = QbankConnector::$plugin->getSettings();
        $tempPath = Craft::$app->getPath()->getTempPath() . '/qbank/';
        $filePath = $tempPath . $media->getTempFilename();

        // Make sure folder exists
        FileHelper::createDirectory($tempPath);

        try {
            $client = new Client();
            $client->get($media->url, [
                'sink'            => $filePath,
                'connect_timeout' => $settings->connectionTimeout,
            ]);

            // @todo Check if there already exists an Asset with these dimensions and template?
            // @todo Pass source id

            // Insert as Asset
            //Craft::$app->getAssetIndexer()->indexFile()
            $filename  = $media->getTempFilename();
            $folderUid = $media->getFolderId();
            $folder    = Craft::$app->getAssets()->getFolderById($folderUid);

            if (!$folder) {
                throw new BadRequestHttpException('The target folder provided for uploading is not valid');
            }

            if ($this->mediaExists($media)) {
                //return true;
            }

            $volume                        = $folder->getVolume();
            $asset                         = new Asset();
            $asset->tempFilePath           = $filePath;
            $asset->volumeId               = $volume->id;
            $asset->newFolderId            = $folder->id;
            $asset->folderPath             = $folder->path;
            $asset->filename               = AssetsHelper::prepareAssetName($filename);
            $asset->title                  = $media->name;
            $asset->kind                   = AssetsHelper::getFileKindByExtension($filename);
            $asset->avoidFilenameConflicts = true;
            $asset->setScenario(Asset::SCENARIO_CREATE);
            // $asset->avoidFilenameConflicts = true;


            if (!Craft::$app->getElements()->saveElement($asset)) {
                Craft::error('Could not save asset: ' . Json::encode($asset->getErrors()), 'qbank-connector');

                return false;
            }

            $media->assetId = $asset->id;

            $this->saveMedia($media);

            return true;
        } catch (RequestException $e) {
            $media->addError('url', $e->getMessage());

            Craft::error('Error: ' . $e->getMessage(), 'qbank-connector');

            return false;
        }
    }

    /*
     * @return mixed
     */
    public function onElementSave(ModelEvent $event)
    {
        /** @var Element $element */
        $element         = $event->sender;
        $relatedAssetIds = Asset::find()->relatedTo($element)->ids();
        $qbankClient     = $this->getQbankClient();
        $existingUsage   = $this
            ->createUsageQuery()
            ->select([
                'usageRecordId' => 'usage.id',
                'usageId'       => 'usage.usageId',
                'fileId'        => 'usage.fileId',
                'assetId'       => 'files.assetId',
            ])
            ->innerJoin(QbankConnectorRecord::tableName() . ' files', '[[files.id]] = [[usage.fileId]]')
            ->where([
                'usage.elementId' => $element->id,
            ])
            ->all();

        $existingFiles = $this
            ->_createQuery()
            ->where(['assetId' => $relatedAssetIds])
            ->all();

        Craft::info('Related usage info: ' . Json::encode($existingUsage), 'qbank-connector');
        Craft::info('Object ids for linked assets: ' . Json::encode($existingFiles), 'qbank-connector');

        $existingAssetIds = \array_map(function($row) {
            return $row['assetId'];
        }, $existingUsage);
        $objectMap        = [];
        $usageMap         = [];

        foreach ($existingFiles as $object) {
            $objectMap[ $object['assetId'] ] = $object;
        }

        foreach ($existingUsage as $usage) {
            $usageMap[ $usage['assetId'] ] = $usage;
        }

        $sessionId = $this->getSessionId();
        foreach ($relatedAssetIds as $assetId) {
            if (!\in_array($assetId, $existingAssetIds)) {
                $objectId = $objectMap[ $assetId ]['objectId'] ?? null;

                if ($objectId) {
                    $pageTitle = $element->title ?? '';
                    $usage     = new UsageModel([
                        'fileId'    => $objectMap[ $assetId ]['id'],
                        'elementId' => $element->id,
                    ]);

                    $asset = Asset::find()->id($assetId)->one();

                    $mediaUsage = new MediaUsage([
                        'mediaId'  => $objectMap[ $assetId ]['mediaId'],
                        'mediaUrl' => $asset->getUrl(),
                        'pageUrl'  => $element->getUrl(),
                        // @todo Add context as setting?
                        'context'  => [
                            'localID'   => $assetId,
                            'pageTitle' => $pageTitle,
                        ],
                        // @todo Add language as setting?
                        'language' => 'NO',
                    ]);

                    $job = new UsageJob([
                        'mediaUsage' => $mediaUsage,
                        'usage'      => $usage,
                    ]);

                    Craft::$app->getQueue()->push($job);

                    // @todo Handle exception
                    /*
                    $response = $qbankClient
                        ->events()
                        ->addUsage($sessionId, $mediaUsage);

                    $this
                        ->createUsageQuery()
                        ->createCommand()
                        ->insert(QbankConnectorUsageRecord::tableName(), [
                            'fileId'    => $usage->fileId,
                            'elementId' => $usage->elementId,
                            'usageId'   => $response->getId(),
                        ])
                        ->execute();
                    */
                }
            }
        }

        $deleteUsageIds = [];
        foreach ($existingAssetIds as $assetId) {
            if (!\in_array($assetId, $relatedAssetIds)) {
                $key                    = $usageMap[ $assetId ]['usageRecordId'];
                $deleteUsageIds[ $key ] = $usageMap[ $assetId ]['usageId'];
            }
        }

        if (!empty($deleteUsageIds)) {
            Craft::info('Removing usage: ' . Json::encode($deleteUsageIds), 'qbank-connector');

            $job = new UsageJob([
                'usageIds'        => $deleteUsageIds,
                'sourceElementId' => $element->id,
            ]);

            Craft::$app->getQueue()->push($job);
        }

        // @todo if this is a Asset, should keep folder id updated to check for existing ones?

        Craft::info('Related asset ids: ' . \implode(', ', $relatedAssetIds), 'qbank-connector');
    }

    public function onElementBeforeDelete(ModelEvent $event)
    {
        /** @var Element $element */
        $element     = $event->sender;
        $qbankClient = $this->getQbankClient();

        if ($element instanceof Asset) {
            // @todo Check if this is a Qbank Asset, and unregister it
            $existingUsage = $this
                ->createUsageQuery()
                ->select([
                    'usageRecordId' => 'usage.id',
                    'usageId'       => 'usage.usageId',
                    'fileId'        => 'usage.fileId',
                    'assetId'       => 'files.assetId',
                ])
                ->innerJoin(QbankConnectorRecord::tableName() . ' files', '[[files.id]] = [[usage.fileId]]')
                ->where([
                    'usage.elementId' => $element->id,
                ])
                ->all();
        }
        else {
            $relatedAssetIds = Asset::find()->relatedTo($element)->ids();
            $existingFiles   = $this
                ->_createQuery()
                ->where(['assetId' => $relatedAssetIds])
                ->all();

            foreach ($existingFiles as $row) {
                $usageId = $row['usageId'] ?? null;

                if (!empty($usageId)) {
                    // @todo Handle exception
                    $qbankClient
                        ->events()
                        ->removeUsage($usageId);
                }
            }
        }
    }

    public function syncAssetUsageForElement()
    {
        // @todo Loop through all media references for this asset
    }

    public function saveMedia(MediaModel $media)
    {
        /*
        $existingQuery = $this
            ->_createQuery()
            ->where([
                'objectId'   => $media->objectId,
                'objectHash' => $media->objectHash,
            ])
            ->one();

        if ($existingQuery) {
            // @todo What to do here? Don't need to download if already exists
            // @todo Probably should include asset's folder id as unique constraint
            return true;
        }
        */

        return $this
            ->_createQuery()
            ->createCommand()
            ->insert(QbankConnectorRecord::tableName(), [
                'assetId'    => $media->assetId,
                'mediaId'    => $media->mediaId,
                'objectId'   => $media->objectId,
                'objectHash' => $media->getObjectHash(),
            ])
            ->execute();
    }

    public function mediaExists(MediaModel $media)
    {
        $query = $existingQuery = $this
            ->_createQuery()
            ->where(['objectId' => $media->objectId, 'objectHash' => $media->getObjectHash()])
            ->one();

        if (!$query) {
            return false;
        }

        $media->assetId = $query['assetId'];

        return true;
    }

    public function getSessionId($isQueueJob = false)
    {
        $getSessionId = function() {
            $settings  = QbankConnector::$plugin->getSettings();
            $user      = Craft::$app->getUser()->getIdentity() ?? User::find()->one();
            $userIp    = '127.0.0.1';
            $userAgent = 'Chrome';

            if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
                $userIp    = Craft::$app->getRequest()->getUserIP();
                $userAgent = Craft::$app->getRequest()->getUserAgent();
            }

            $sessionId = $this->getQbankClient()->events()->session(
                $settings->sessionSourceId,
                $user->uid,
                $userIp,
                $userAgent
            );

            return $sessionId;
        };

        if (Craft::$app->getRequest()->getIsConsoleRequest() || $isQueueJob) {
            return $getSessionId();
        }

        $session = Craft::$app->getSession();

        if (!$session->has('QBANK_SESSION')) {
            $session->set('QBANK_SESSION', $getSessionId());
        }

        return $session->get('QBANK_SESSION');
    }

    public function _createQuery()
    {
        return (new Query())
            ->from(QbankConnectorRecord::tableName())
            ->select([
                'id',
                'assetId',
                'mediaId',
                'objectId',
                'objectHash',
            ]);
    }


    /**
     * @return Query
     */
    public function createUsageQuery()
    {
        return (new Query())
            ->from([
                'usage' => QbankConnectorUsageRecord::tableName(),
            ])
            ->select([
                'id',
                'elementId',
                'fileId',
            ]);
    }
}
