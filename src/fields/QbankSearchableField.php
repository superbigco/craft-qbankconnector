<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

namespace superbig\qbankconnector\fields;

use craft\elements\Asset;
use craft\helpers\StringHelper;
use craft\models\UserGroup;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use superbig\qbankconnector\QbankConnector;
use yii\db\Schema;
use craft\helpers\Json;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 */
class QbankSearchableField extends Field
{
    // Public Properties
    // =========================================================================

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('qbank-connector', 'QBank Searchable Properties Mapper');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getContentColumnType(): string
    {
        return Schema::TYPE_TEXT;
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        if (!$element instanceof Asset) {
            return null;
        }

        return QbankConnector::$plugin->getSearch()->getQbankKeywordsForAsset($element);

        /*
        if (\is_string($value) && !empty($value)) {
            $value = Json::decodeIfJson($value);

            if (\is_string($value)) {
                $value = [$value];
            }
        }
        */

        return null;
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
        // @todo Join in content from table
        $value = '';

        return parent::serializeValue($value, $element);
    }

    /**
     * @inheritdoc
     */
    public function getSearchKeywords($value, ElementInterface $element): string
    {
        if (!$element instanceof Asset) {
            return '';
        }

        return QbankConnector::$plugin->getSearch()->getQbankKeywordsForAsset($element);
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        // Get our id and namespace
        $id           = Craft::$app->getView()->formatInputId($this->handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);

        // Render the input template
        return Craft::$app->getView()->renderTemplate(
            'qbank-connector/field_input',
            [
                'name'         => $this->handle,
                'value'        => $value,
                'field'        => $this,
                'id'           => $id,
                'namespacedId' => $namespacedId,
                'keywords'     => $this->getSearchKeywords(null, $element),
            ]
        );
    }
}
