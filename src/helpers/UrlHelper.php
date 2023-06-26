<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 * @since 1.1.0
 */

namespace superbig\qbankconnector\helpers;

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
 * @property DeleteReference[] $deleteUsageReferences
 */
class UrlHelper extends \craft\helpers\UrlHelper {
    public static function getBaseHost(string $url, $default = null, $fallbackProtocol = 'https')
    {
        // Check if URL starts with '//' or doesn't have a scheme
        if (!preg_match("~^((?:f|ht)tps?:)?//~i", $url)) {
            $url = "{$fallbackProtocol}://" . $url;
        }

        $parsedUrl = parse_url($url);

        if ($parsedUrl && isset($parsedUrl['host'])) {
            return $parsedUrl['host'];
        }

        return $default;
    }
}