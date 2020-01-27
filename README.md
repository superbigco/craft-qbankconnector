# QBank Connector plugin for Craft CMS 3.x

Connect Craft to QBank's DAM

![Screenshot](resources/icon.png)

## Requirements

This plugin requires Craft CMS 3.2.0 or later.

## Installation

To install the plugin, follow these instructions.

1.  Open your terminal and go to your Craft project:

        cd /path/to/project

2.  Then tell Composer to load the plugin:

        composer require superbig/craft-qbankconnector

3.  In the Control Panel, go to Settings → Plugins and click the “Install” button for QBank Connector.

## QBank Connector Overview

> QBank is a smart Digital Asset Management that store, manage and publish your digital assets. Connect with your communications tool for more effective workflow.

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

    // Toggle usage reporting to QBank
    'reportUsage'          => true,

    // Toggle on Asset Index/Fields
    'enableForAssetFields' => true,
    'enableForAssetIndex'  => true,

    // By default 'sales.qbank.se'
    'qbankBaseDomain'          => '',
];
```

## Using QBank Connector

After you install the plugin, a `Upload from QBank` button will appear under all Asset fields and in Asset Indexes (including the modal when you click the assets select button).

This allow you to add assets from QBank directly to Assets fields or through the normal Assets selection modal.

### Searchable assets

To import metadata in QBank from custom fields like keywords, tags etc. into Craft and make those searchable, there is a custom fieldtype.

After you have setup the fields to import via the plugin settings, you may add the custom field to your Assets Volume. Make sure to toggle the `Use this field’s values as search keywords?` option in the field settings.

_Note: Already existing assets downloaded from QBank will not have the imported metadata. If you change which fields to import, already existing assets will not import the new fields._

Brought to you by [Superbig](https://superbig.co)
