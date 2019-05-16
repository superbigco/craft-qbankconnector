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
use craft\events\ModelEvent;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use QBNK\QBank\API\CachePolicy;
use QBNK\QBank\API\Credentials;
use QBNK\QBank\API\QBankApi;
use superbig\qbankconnector\base\Cache;
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
            $folderUid = 1;
            $folder    = Craft::$app->getAssets()->getFolderById($folderUid);

            if (!$folder) {
                throw new BadRequestHttpException('The target folder provided for uploading is not valid');
            }

            if ($this->mediaExists($media)) {
                return true;
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
        $existingUsage   = $this
            ->_createUsageQuery()
            ->select([
                'usageId' => 'usage.id',
                'fileId'  => 'usage.fileId',
                'assetId' => 'files.assetId',
            ])
            ->innerJoin(QbankConnectorRecord::tableName() . ' files', '[[files.id]] = [[usage.fileID]]')
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

        $objectMap = [];
        $usageMap  = [];
        foreach ($existingFiles as $object) {
            $objectMap[ $object['assetId'] ] = $object;
        }
        foreach ($existingUsage as $usage) {
            $usageMap[ $usage['assetId'] ] = $usage;
        }

        foreach ($relatedAssetIds as $assetId) {
            if (!\in_array($assetId, $existingAssetIds)) {
                $objectId = $objectMap[ $assetId ]['objectId'] ?? null;

                if ($objectId) {
                    $usage = new UsageModel([
                        'fileId'    => $objectMap[ $assetId ]['id'],
                        'elementId' => $element->id,
                    ]);

                    $this
                        ->_createUsageQuery()
                        ->createCommand()
                        ->insert(QbankConnectorUsageRecord::tableName(), [
                            'fileId'    => $usage->fileId,
                            'elementId' => $usage->elementId,
                        ])
                        ->execute();
                }
            }
        }

        $deleteUsageIds = [];
        foreach ($existingAssetIds as $assetId) {
            if (!\in_array($assetId, $relatedAssetIds)) {
                $deleteUsageIds[] = $usageMap[ $assetId ]['usageId'];

            }
        }

        if (!empty($deleteUsageIds)) {
            Craft::info('Removing usage: ' . Json::encode($deleteUsageIds), 'qbank-connector');

            $this
                ->_createUsageQuery()
                ->createCommand()
                ->delete(QbankConnectorUsageRecord::tableName(), [
                    'id'        => $deleteUsageIds,
                    'elementId' => $element->id,
                ])
                ->execute();
        }

        // @todo if this is a Asset, should keep folder id updated to check for existing ones?

        Craft::info('Related asset ids: ' . \implode(', ', $relatedAssetIds), 'qbank-connector');

        // @todo Check for assets
        // @todo Get asset ids already from Qbank
    }

    public function onElementBeforeDelete(ModelEvent $event)
    {
        /** @var Element $element */
        $element = $event->sender;

        if ($element instanceof Asset) {
            // @todo Check if this is a Qbank Asset, and unregister it
        }
    }

    public function syncAssetUsageForElement()
    {
        // @todo Loop through all media references for this asset
    }

    public function toggleUsage()
    {
        // @todo either add or delete usage records
    }

    public function saveMedia(MediaModel $media)
    {
        $existingQuery = $this
            ->_createQuery()
            ->where(['objectId' => $media->objectId, 'objectHash' => $media->objectHash])
            ->one();

        if ($existingQuery) {
            // @todo What to do here? Don't need to download if already exists
            return true;
        }

        return $this
            ->_createQuery()
            ->createCommand()
            ->insert(QbankConnectorRecord::tableName(), [
                'assetId'    => $media->assetId,
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

    public function _createQuery()
    {
        return (new Query())
            ->from(QbankConnectorRecord::tableName())
            ->select([
                'id',
                'assetId',
                'objectId',
                'objectHash',
            ]);
    }


    public function _createUsageQuery()
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
