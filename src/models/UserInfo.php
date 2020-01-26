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

use superbig\qbankconnector\QbankConnector;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 */
class UserInfo extends Model
{
    // Public Properties
    // =========================================================================

    public $userAgent = 'Chrome';
    public $userIp    = '127.0.0.1';

    public function getSessionCacheKey()
    {
        $key = \md5("{$this->userAgent}:{$this->userIp}");

        return "qbank-session-{$key}";
    }
}
