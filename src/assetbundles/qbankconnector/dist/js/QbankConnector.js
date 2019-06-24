/**
 * QBank Connector plugin for Craft CMS
 *
 * QBank Connector JS
 *
 * @author    Superbig
 * @copyright Copyright (c) 2019 Superbig
 * @link      https://superbig.co
 * @package   QbankConnector
 * @since     1.0.0
 */

if (typeof Craft.QbankConnector === typeof undefined) {
    Craft.QbankConnector = {};
}

(function ($) {
    Craft.QbankConnector = Garnish.Base.extend({
        settings: {
            folderId: null,
            sessionSourceId: null,
            deploymentSiteId: null,
            currentSource: null,
            qbankAccessToken: null,
            qbankBaseUrl: null,
            qbankBaseDomain: null,
            defaultImageSize: 1000,
            defaultImageExtension: 'jpg'
        },
        $qbankConnectorButton: null,
        $modal: null,
        $connectorModal: null,
        qbankFields: [],
        activeField: null,
        activeAssetIndex: null,
        activeFolderId: null,
        init: function (options) {
            Garnish.on(Craft.AssetSelectorModal, 'show', $.proxy(this, 'onAssetModalOpen'));
            //Garnish.on(Craft.AssetSelectorModal, 'fadeIn', $.proxy(this, 'onFadeIn'));
            Garnish.on(Craft.AssetSelectorModal, 'afterInit', $.proxy(this, 'onFadeIn'));
            Garnish.on(Craft.Uploader, 'afterInit', $.proxy(this, 'onFadeIn'));
            Garnish.on(Craft.AssetSelectorModal, 'hide', $.proxy(this, 'onAssetModalHide'));
            Garnish.on(Craft.AssetIndex, 'afterInit', $.proxy(this, 'onAssetIndex'));
            Garnish.on(Craft.AssetIndex, 'selectSource', $.proxy(this, 'onSelectSource'));
            Garnish.on(Craft.BaseElementIndex, 'init', $.proxy(this, 'onAssetIndex'));

            this.settings = $.extend({}, this.settings, options);

            //this.setup();
            //this.setupConnector();

            // Handle element index on page load where afterInit is fired too early
            if (Craft.elementIndex && Craft.elementIndex['elementType'] === "craft\\elements\\Asset") {
                //Craft.elementIndex.on('selectSource', $.proxy(this, 'onSelectSource'));
            }
        },

        setup: function () {
            if (this._getAssetFields().length > 0) {
                this.setupAssetFields();
                this.getAccessToken();
            }
        },

        setupAssetFields: function () {
            Garnish.on(Craft.QbankField, 'onClickButton', $.proxy(this, 'onClickAssetButton'))
        },

        getAccessToken: function () {
            var self = this;
            Craft.postActionRequest('qbank-connector/default/access-token', function (response) {
                var token = response.token;

                if (!token) {
                    Craft.cp.displayError(Craft.t('qbank-connect', 'Could not get access token from QBank'))
                }

                self.settings.qbankAccessToken = token;
            });
        },

        onAssetModalOpen: function () {
            this.$modal = $('.modal.elementselectormodal').first();

            if (!this.$qbankConnectorButton) {
                this._appendButton();
            }
        },

        onFadeIn: function () {
            if (!this.$qbankConnectorButton) {
                this._appendButton();
            }
        },

        onAssetModalHide: function () {
            this.$modal = null;
        },

        onSelectElements: function () {
        },

        onRemoveElements: function () {
        },
        onSelectSource: function (e) {
            if (typeof e.target.sourcesByKey !== 'undefined') {
                var $sourceElement = e.target.sourcesByKey[e.sourceKey];
                var sourceData = $sourceElement.data();
                this.activeFolderId = $sourceElement.data('folder-id');
            }
        },

        onAssetIndex: function (event) {
            var $assetIndex = event.target;
            var $uploadButton = $assetIndex.$uploadButton;

            if ($uploadButton.length) {
                this.getAccessToken();

                var selectedSourceKey = $assetIndex.instanceState.selectedSource;
                var activeSource = $assetIndex.sourcesByKey[selectedSourceKey];
                var sourceKeys = Object.keys($assetIndex.sourcesByKey);
                var firstSourceKey = sourceKeys.length > 0 ? sourceKeys[0] : null;

                if (!firstSourceKey) {
                    console.log('Returning because no source')
                    return;
                }

                this.activeAssetIndex = $assetIndex;
                this.activeFolderId = activeSource ? $assetIndex.sourcesByKey[selectedSourceKey][0].dataset.folderId : null;
                var $qbankButton = $uploadButton
                    .clone()
                    .text(Craft.t('qbank-connector', 'Upload from QBank'))
                    .addClass('qbank-upload-button--assetindex')
                    .removeClass('submit');

                $uploadButton.parent().prepend($qbankButton);
                $qbankButton.on('click', $.proxy(this, 'onClickAssetIndexButton'))
            }
        },

        onClickAssetIndexButton: function () {
            if (this.activeAssetIndex) {
                var settings = {
                    folderId: this.activeFolderId,
                    viewMode: this.activeAssetIndex.getSelectedViewMode()
                };

                // Update settings
                this.settings = $.extend({}, this.settings, settings);
                this._openConnectorModal();
            }
        },

        onClickAssetButton: function (event) {
            var settings = {
                fieldId: event.fieldId,
                fieldLimit: event.fieldLimit,
                sourceElementId: event.sourceElementId,
                viewMode: 'list'
            };

            // Update settings
            this.settings = $.extend({}, this.settings, settings);
            this.activeField = event.field;

            this._openConnectorModal();
        },

        _openConnectorModal: function () {
            var newSettings = $.extend({}, this.settings, {folderId: this.activeFolderId});

            if (!this.$connectorModal) {
                this.$connectorModal = new Craft.QbankConnectorModal(newSettings);

                Garnish.on(Craft.QbankConnectorModal, 'selectAsset', $.proxy(this, '_onSelectQdamAsset'))
            } else {
                this.$connectorModal.updateSettings(newSettings);
                this.$connectorModal.show();
            }
        },

        _onSelectQdamAsset: function (eventData) {
            this.$connectorModal.hide();

            if (this.activeAssetIndex) {
                this.activeAssetIndex._uploadedAssetIds.push(eventData.elementInfo.id);
                this.activeAssetIndex._updateAfterUpload();
            }

            this.activeField = null;
        },

        _getAssetFields: function () {
            if (!this.$assetFields) {
                this.$assetFields = $('.field').filter(function (index, element) {
                    var type = $(element).data('type');
                    return type && type.includes('craft\\fields\\Assets');
                })
            }

            return this.$assetFields;
        },

        _appendButton: function () {
            var $uploadButton = this.$modal.find('.btn.submit').first();
        },

        _getCurrentSource: function () {
            return Craft.elementIndex.sourceKey;
        }
    });
})(jQuery);