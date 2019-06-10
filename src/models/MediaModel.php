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

use craft\fields\Assets;
use superbig\qbankconnector\QbankConnector;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 */
class MediaModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $url = null;
    public $id;
    public $assetId;
    public $mediaId;
    public $template;
    public $filename;
    public $extension;
    public $name;
    public $dimensions;
    public $objectId;
    public $objectHash;
    public $data;

    // Asset settings
    public $folderId;
    public $sourceElementId;
    public $fieldId;

    // Public Methods
    // =========================================================================

    public function getTempFilename(): string
    {
        $filename = \str_replace(".{$this->extension}", '', $this->filename);

        if ($this->template) {
            $filename = $filename . $this->getObjectHash();
        }

        return $filename . ".{$this->extension}";
    }

    public function getObjectHash(): string
    {
        $object = $this->objectId;

        if ($this->template) {
            $object = "{$object}-{$this->template}";
        }

        return \md5($object);
    }

    public function getFolderId()
    {
        $folderId = (int)$this->folderId;

        if (!empty($this->sourceElementId) && !empty($this->fieldId)) {
            /** @var Assets $field */
            $field   = Craft::$app->getFields()->getFieldById((int)$this->fieldId);
            $element = Craft::$app->getElements()->getElementById((int)$this->sourceElementId);

            if ($element) {
                $dynamicFolderId = $field->resolveDynamicPathToFolderId($element);
                $folderId        = !empty($dynamicFolderId) ? $dynamicFolderId : $folderId;
            }
        }

        return $folderId;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['url', 'string'],
            ['url', 'required'],
        ];
    }
}
