# QBank Connector plugin for Craft CMS 3.x

Connect Craft to QBank's DAM

![Screenshot](resources/icon.png)

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require superbig/craft-qbankconnector

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for QBank Connector.

## QBank Connector Overview

>QBank is a smart Digital Asset Management that store, manage and publish your digital assets. Connect with your communications tool for more effective workflow.

Read more at [qbankdam.com](https://www.qbankdam.com/en/start).

## Configuring QBank Connector

There is a sample config file located at `src/config.php`. Copy it to `craft/config` as `qbank-connector.php` 
and make your changes there to override default settings.

```php
<?php
return [
    // These will be supplied by QBank
    'clientId'         => '',
    'sessionSourceId'  => '',
    'username'         => '',
    'password'         => '',
    'deploymentSiteId' => null,

    // By default 'sales.qbank.se'
    'baseRef'          => '',
];

```

## Using QBank Connector

After you install the plugin, a `Upload from QBank` button will appear under all Asset fields and in Asset Indexes (including the modal when you click the assets select button).

This allow you to add assets from QBank directly to Assets fields or through the normal Assets selection modal.

## QBank Connector Roadmap

* Log usage async with queue jobs

Brought to you by [Superbig](https://superbig.co)
