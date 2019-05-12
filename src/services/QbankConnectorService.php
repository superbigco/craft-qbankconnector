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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use QBNK\QBank\API\CachePolicy;
use QBNK\QBank\API\Credentials;
use QBNK\QBank\API\QBankApi;
use superbig\qbankconnector\base\Cache;
use superbig\qbankconnector\models\MediaModel;
use superbig\qbankconnector\QbankConnector;

use Craft;
use craft\base\Component;
use superbig\qbankconnector\records\QbankConnectorRecord;
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
        $tempPath = Craft::$app->getPath()->getTempPath() . '/qbank/';
        FileHelper::createDirectory($tempPath);
        $filePath = $tempPath . $media->getTempFilename();

        try {
            $client = new Client();
            $client->get($media->url, [
                'sink' => $filePath,
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

            $volume              = $folder->getVolume();
            $asset               = new Asset();
            $asset->tempFilePath = $filePath;
            $asset->volumeId     = $volume->id;
            $asset->newFolderId  = $folder->id;
            $asset->folderPath   = $folder->path;
            $asset->filename     = AssetsHelper::prepareAssetName($filename);
            $asset->title        = $media->name;
            $asset->kind         = AssetsHelper::getFileKindByExtension($filename);
            $asset->setScenario(Asset::SCENARIO_CREATE);
            // $asset->avoidFilenameConflicts = true;


            if (!Craft::$app->getElements()->saveElement($asset)) {
                return false;
            }

            return true;
        } catch (RequestException $e) {
            $media->addError('url', $e->getMessage());

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

    }

    public function toggleUsage()
    {
        // @todo either add or delete usage records
    }

    public function saveMedia(MediaModel $media)
    {

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
}
