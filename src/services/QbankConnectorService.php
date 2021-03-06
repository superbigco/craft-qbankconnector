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
use craft\elements\actions\Delete;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\MatrixBlock;
use craft\elements\User;
use craft\fields\Matrix;
use craft\events\ModelEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\ElementHelper;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use QBNK\QBank\API\CachePolicy;
use QBNK\QBank\API\Credentials;
use QBNK\QBank\API\QBankApi;
use superbig\qbankconnector\base\Cache;
use superbig\qbankconnector\jobs\UsageJob;
use superbig\qbankconnector\models\DeleteReference;
use superbig\qbankconnector\models\MediaModel;
use superbig\qbankconnector\models\NewUsageReference;
use superbig\qbankconnector\models\UsageModel;
use superbig\qbankconnector\models\UserInfo;
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
     * @param array $assetIds
     *
     * @return MediaModel[]
     */
    public function getFilesByAssetIds(array $assetIds = [])
    {
        $existingFiles = $this
            ->_createQuery()
            ->where(['assetId' => $assetIds])
            ->all();

        return array_map(function($row) {
            return new MediaModel($row);
        }, $existingFiles);
    }

    /**
     * @param $element
     *
     * @return UsageModel[]
     */
    public function getUsageForElement($element)
    {
        $usage = $this
            ->createUsageQuery()
            ->select([
                'id'      => 'usage.id',
                'usageId' => 'usage.usageId',
                'fileId'  => 'usage.fileId',
                'assetId' => 'files.assetId',
            ])
            ->innerJoin(QbankConnectorRecord::tableName() . ' files', '[[files.id]] = [[usage.fileId]]')
            ->where([
                'usage.elementId' => $element->id,
            ])
            ->all();

        return array_map(function($row) {
            return new UsageModel($row);
        }, $usage);
    }

    /**
     * @param Asset $asset
     *
     * @return UsageModel[]
     */
    public function getUsageForAsset(Asset $asset)
    {
        $usage = $this
            ->createUsageQuery()
            ->select([
                'id'      => 'usage.id',
                'usageId' => 'usage.usageId',
                'fileId'  => 'usage.fileId',
                'assetId' => 'files.assetId',
            ])
            ->innerJoin(QbankConnectorRecord::tableName() . ' files', '[[files.id]] = [[usage.fileId]]')
            ->where([
                'files.assetId' => $asset->id,
            ])
            ->all();

        return array_map(function($row) {
            return new UsageModel($row);
        }, $usage);
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
     * @throws \Throwable
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

            $media->setProperties($this->getPropertiesForMedia($media));
            $this->saveMedia($media);

            Craft::$app->getElements()->saveElement($asset);

            return true;
        } catch (RequestException $e) {
            $media->addError('url', $e->getMessage());

            Craft::error('Error: ' . $e->getMessage(), 'qbank-connector');

            return false;
        }
    }

    public function getPropertiesForMedia(MediaModel $media)
    {
        $client   = $this->getQbankClient();
        $response = $client->media()->retrieveMedia($media->mediaId);

        $properties = QbankConnector::$plugin->getSearch()->getPropertiesAsString($response);

        return $properties;
    }

    public function onElementSave(ModelEvent $event)
    {
        if (!$this->reportUsageEnabled()) {
            return;
        }

        $fileMap  = [];
        $usageMap = [];
        $userInfo = $this->_getUserInfo();

        /** @var Element $element */
        $element = $event->sender;

        // Skip drafts and propagating elements
        if (ElementHelper::isDraftOrRevision($element) || $element->propagating || $element->resaving || empty($element->getUrl())) {
            return;
        }

        if ($element instanceof MatrixBlock) {
            $element = $element->getOwner();
        }

        $pageTitle       = $element->title ?? '';
        $relatedAssetIds = $this->getRelatedAssetsIdsForElement($element);
        $existingUsage   = $this->getUsageForElement($element);
        $existingFiles   = $this->getFilesByAssetIds($relatedAssetIds);

        $existingAssetIds = \array_map(function(UsageModel $usage) {
            return $usage->assetId;
        }, $existingUsage);

        // Map files to array for easier access
        foreach ($existingFiles as $file) {
            $fileMap[ $file->assetId ] = $file;
        }

        foreach ($existingUsage as $usage) {
            $usageMap[ $usage->assetId ] = $usage;
        }

        $newUsageReferences  = [];
        $deletedReferences   = [];
        $nonExistingAssetIds = array_diff($relatedAssetIds, $existingAssetIds);
        $removedAssetIds     = array_diff($existingAssetIds, $relatedAssetIds);

        if (!empty($nonExistingAssetIds)) {
            // This is new asset ids that haven't got a usage record
            foreach ($nonExistingAssetIds as $assetId) {
                $objectId = ArrayHelper::getValue($fileMap, "{$assetId}.objectId");

                if ($objectId) {
                    $fileId               = ArrayHelper::getValue($fileMap, "{$assetId}.id");
                    $mediaId              = ArrayHelper::getValue($fileMap, "{$assetId}.mediaId");
                    $asset                = Asset::find()->id($assetId)->one();
                    $newUsageReferences[] = new NewUsageReference([
                        'sourceElementId' => $element->id,
                        'pageTitle'       => $pageTitle,
                        'pageUrl'         => $element->getUrl(),
                        'assetId'         => $assetId,
                        'mediaId'         => $mediaId,
                        'mediaUrl'        => $asset->getUrl(),
                        'fileId'          => $fileId,
                    ]);
                }
            }

            $this->reportUsage($newUsageReferences, $userInfo);
        }

        if (!empty($removedAssetIds)) {
            foreach ($removedAssetIds as $deletedAssetId) {
                $deletedReferences[] = new DeleteReference([
                    'recordId'        => ArrayHelper::getValue($usageMap, "{$deletedAssetId}.id"),
                    'usageId'         => ArrayHelper::getValue($usageMap, "{$deletedAssetId}.usageId"),
                    'sourceElementId' => $element->id,
                ]);
            }

            $this->reportRemovedUsage($deletedReferences, $userInfo);
        }
    }

    public function reportUsage(array $references, UserInfo $userInfo)
    {
        $job = new UsageJob([
            'newUsageReferences' => $references,
            'userIp'             => $userInfo->userIp,
            'userAgent'          => $userInfo->userAgent,
        ]);

        Craft::$app->getQueue()->push($job);
    }

    /**
     * @param DeleteReference[] $references
     * @param UserInfo          $userInfo
     */
    public function reportRemovedUsage(array $references, UserInfo $userInfo)
    {
        $job = new UsageJob([
            'deleteUsageReferences' => $references,
            'userIp'                => $userInfo->userIp,
            'userAgent'             => $userInfo->userAgent,
        ]);

        Craft::$app->getQueue()->push($job);
    }

    public function onElementBeforeDelete(ModelEvent $event)
    {
        if (!$this->reportUsageEnabled()) {
            return;
        }

        $deletedReferences = [];
        /** @var Element $element */
        $element = $event->sender;

        if ($element instanceof Asset) {
            $existingUsage = $this->getUsageForAsset($element);

            if (!empty($existingUsage)) {
                foreach ($existingUsage as $usage) {
                    $deletedReferences[] = new DeleteReference([
                        'recordId'        => $usage->id,
                        'usageId'         => $usage->usageId,
                        'sourceElementId' => $usage->elementId,
                    ]);
                }

                $this->reportRemovedUsage($deletedReferences, new UserInfo());
            }

        }
        else {
            $existingUsage = $this->getUsageForElement($element);

            if (!empty($existingUsage)) {
                foreach ($existingUsage as $usage) {
                    $deletedReferences[] = new DeleteReference([
                        'recordId'        => $usage->id,
                        'usageId'         => $usage->usageId,
                        'sourceElementId' => $usage->elementId,
                    ]);
                }

                $this->reportRemovedUsage($deletedReferences, new UserInfo());
            }
        }
    }

    public function saveMedia(MediaModel $media)
    {
        return $this
            ->_createQuery()
            ->createCommand()
            ->insert(QbankConnectorRecord::tableName(), [
                'assetId'    => $media->assetId,
                'mediaId'    => $media->mediaId,
                'objectId'   => $media->objectId,
                'objectHash' => $media->getObjectHash(),
                'metadata'   => Json::encode($media->getMetadata()),
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

    public function getSessionId(UserInfo $info, $isQueueJob = false)
    {
        $settings     = QbankConnector::$plugin->getSettings();
        $getSessionId = function() use ($info, $settings) {
            $user = Craft::$app->getUser()->getIdentity() ?? User::find()->one();

            $sessionId = $this->getQbankClient()->events()->session(
                $settings->sessionSourceId,
                $user->uid,
                $info->userIp,
                $info->userAgent
            );

            return $sessionId;
        };

        if (Craft::$app->getRequest()->getIsConsoleRequest() || $isQueueJob) {
            if ($settings->cacheSessionId) {
                $cachedSessionId = Craft::$app->getCache()->getOrSet(
                    $info->getSessionCacheKey(),
                    function() use ($getSessionId) {
                        return $getSessionId();
                    },
                    $settings->cacheDuration
                );

                if ($cachedSessionId) {
                    return $cachedSessionId;
                }
            }

            return $getSessionId();
        }

        $session = Craft::$app->getSession();

        if (!$session->has('QBANK_SESSION')) {
            $session->set('QBANK_SESSION', $getSessionId());
        }

        return $session->get('QBANK_SESSION');
    }

    public function getRelatedAssetsIdsForElement(Element $element)
    {
        $ids                = Asset::find()->relatedTo($element)->ids();
        $matrixFieldHandles = $this->getMatrixFieldHandlesForEntry($element);

        foreach ($matrixFieldHandles as $handle) {
            $childIds = Asset::find()->relatedTo([
                'sourceElement' => $element,
                // @todo Might need to loop over block type handles with asset fields
                'field'         => $handle,
            ])->ids();

            $ids = array_merge($ids, $childIds);
        }

        return array_unique($ids);
    }

    public function getMatrixFieldHandlesForEntry(Element $element)
    {
        $fieldLayout = $element->getFieldLayout();
        $fields      = array_filter($fieldLayout->getFields(), function($field) {
            return $field instanceof Matrix;
        });

        return array_map(function($field) {
            return $field->handle;
        }, $fields);
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
                'metadata',
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

    /**
     * @return UserInfo
     */
    private function _getUserInfo()
    {
        $userIp    = '127.0.0.1';
        $userAgent = 'Chrome';

        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            $userIp    = Craft::$app->getRequest()->getUserIP();
            $userAgent = Craft::$app->getRequest()->getUserAgent();
        }

        return new UserInfo([
            'userIp'    => $userIp,
            'userAgent' => $userAgent,
        ]);
    }

    private function reportUsageEnabled()
    {
        return QbankConnector::$plugin->getSettings()->reportUsage;
    }
}
