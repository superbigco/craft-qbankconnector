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
use craft\helpers\ElementHelper;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use QBNK\QBank\API\CachePolicy;
use QBNK\QBank\API\Credentials;
use QBNK\QBank\API\Exception\PropertyNotFoundException;
use QBNK\QBank\API\Model\MediaResponse;
use QBNK\QBank\API\Model\MediaUsage;
use QBNK\QBank\API\Model\PropertyType;
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
class SearchService extends Component
{
    // Public Methods
    // =========================================================================

    public function getQbankKeywordsForAsset(Asset $asset)
    {
        $metadata = (new Query())
            ->select('metadata')
            ->where('assetId = :id', [':id' => $asset->id])
            ->from(QbankConnectorRecord::tableName())
            ->scalar();

        if (!$metadata) {
            return '';
        }

        $metadata   = Json::decodeIfJson($metadata);
        $properties = $metadata['properties'] ?? [];
        $keywords   = \implode(' ', \array_filter(\array_values($properties)));

        return $keywords;
    }

    public function getAvailableProperties()
    {
        $client        = QbankConnector::$plugin->getService()->getQbankClient();
        $propertyTypes = $client->propertysets()->listPropertyTypes();
        //$objectTypes   = $client->objecttypes()->listObjectTypes();
        //$properties    = $client->propertysets()->listPropertySets();
        $options = \array_map(function($property) {
            /** @var PropertyType $property */
            return [
                'label'       => "{$property->getName()} ({$property->getSystemName()})",
                'description' => $property->getDescription(),
                'value'       => $property->getSystemName(),

                // Data type for the Property (1: Boolean, 2: DateTime, 3: Decimal, 4: Float, 5: Integer, 6: String)
                // In addition, definition can alter the way a Property should be displayed.
                'dataTypeId'  => $property->getDataTypeId(),
            ];
        }, $propertyTypes);

        usort($options, [$this, 'sortByName']);

        return $options;
    }

    public function getPropertiesAsString(MediaResponse $response)
    {
        $values                       = [];
        $propertySystemNamesToInclude = QbankConnector::$plugin->getSettings()->searchableProperties;

        foreach ($propertySystemNamesToInclude as $systemName) {
            try {
                $line = $response->getProperty($systemName);
            } catch (PropertyNotFoundException $e) {
                $line = null;
            }

            if ($line) {
                $value = $line->getValue();

                if (\is_array($value)) {
                    $value = \implode(' ', $value);
                }

                if ($value !== null && !\is_string($value)) {
                    $value = Json::encode($value);
                }

                if (!empty($value)) {
                    $value = StringHelper::stripHtml($value);
                }

                $values[ $systemName ] = $value;
            }
        }

        return $values;
    }

    public function sortByName($a, $b)
    {
        return \strcmp($a['label'], $b['label']);
    }
}
