<?php
/**
 * QBank Connector plugin for Craft CMS 3.x
 *
 * Connect Craft to QBank's DAM
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2019 Superbig
 */

namespace superbig\qbankconnector\base;

use Craft;
use craft\helpers\ArrayHelper;
use Psr\SimpleCache\CacheInterface;

/**
 * @author    Superbig
 * @package   QbankConnector
 * @since     1.0.0
 */
class Cache implements CacheInterface
{
    public function get($key, $default = null)
    {
        return Craft::$app->getCache()->get($key);
    }

    public function set($key, $value, $ttl = null)
    {
        return Craft::$app->getCache()->set($key, $value, $ttl);
    }

    public function clear()
    {
        return Craft::$app->getCache()->flush();
    }

    public function getMultiple($keys, $default = null)
    {
        return array_map(fn($key) => Craft::$app->getCache()->get($key), (array)$keys);
    }

    public function setMultiple($values, $ttl = null)
    {
        // TODO: Implement setMultiple() method.
    }

    public function deleteMultiple($keys)
    {
        // TODO: Implement deleteMultiple() method.
    }

    public function has($key)
    {
        return Craft::$app->getCache()->exists($key);
    }

    public function delete($key)
    {
        return Craft::$app->getCache()->delete($key);
    }
}
