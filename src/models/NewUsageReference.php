<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

namespace superbig\qbankconnector\models;

use craft\base\Model;
use QBNK\QBank\API\Model\MediaUsage;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 *
 * @property UserInfo $userInfo
 */
class NewUsageReference extends Model
{
    // Public Properties
    // =========================================================================

    public $sourceElementId;
    public $pageTitle;
    public $pageUrl;
    public $assetId;
    public $fileId;
    public $mediaId;
    public $mediaUrl;
    public $userInfo;

    public function getMediaUsageForQbank()
    {
        return new MediaUsage([
            'mediaId'  => $this->mediaId,
            'mediaUrl' => $this->mediaUrl,
            'pageUrl'  => $this->pageUrl,
            'context'  => [
                'localID'   => $this->assetId,
                'pageTitle' => $this->pageTitle,
            ],
            // @todo Add language as setting?
            'language' => 'NO',
        ]);
    }
}
