<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

namespace superbig\qbankconnector\controllers;

use Sainsburys\Guzzle\Oauth2\AccessToken;
use superbig\qbankconnector\QbankConnector;

use Craft;
use craft\web\Controller;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 */
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = [];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionDownloadAsset()
    {
        $request   = Craft::$app->getRequest();
        $folderId  = $request->getBodyParam('folderId');
        $fieldId   = $request->getBodyParam('fieldId');
        $elementId = $request->getBodyParam('elementId');

        return $this->asJson([
            'success' => false,
        ]);
    }

    public function actionAccessToken()
    {
        $client = QbankConnector::$plugin->getService()->getQbankClient();
        /** @var AccessToken $token */
        $token = $client->getTokens()['accessToken'] ?? null;

        if (!$token) {
            return $this->asJson([
                'token' => null,
            ]);
        }

        return $this->asJson([
            'token' => $token->getToken(),
        ]);
    }
}
