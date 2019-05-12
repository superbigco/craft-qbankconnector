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

    // Public Methods
    // =========================================================================

    public function getTempFilename(): string
    {
        $filename = \str_replace(".{$this->filename}", '', $this->filename);

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
