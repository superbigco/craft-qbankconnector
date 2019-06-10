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
            defaultImageExtension: 'jpg',
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

            this.settings = {...this.settings, ...options}

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

        getAccessToken() {
            Craft.postActionRequest('qbank-connector/default/access-token', response => {
                const {token} = response;

                if (!token) {
                    Craft.cp.displayError(Craft.t('qbank-connect', 'Could not get access token from QBank'))
                }

                this.settings.qbankAccessToken = token;
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
                const $sourceElement = e.target.sourcesByKey[e.sourceKey];
                const sourceData = $sourceElement.data();
                this.activeFolderId = $sourceElement.data('folder-id');
            }
        },

        onAssetIndex: function (event) {
            const $assetIndex = event.target;
            const $uploadButton = $assetIndex.$uploadButton;

            if ($uploadButton.length) {
                this.getAccessToken();

                const selectedSourceKey = $assetIndex.instanceState.selectedSource;
                this.activeAssetIndex = $assetIndex;
                this.activeFolderId = $assetIndex.sourcesByKey[selectedSourceKey][0].dataset.folderId;
                const $qbankButton = $uploadButton
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
                const settings = {
                    folderId: this.activeFolderId,
                    viewMode: this.activeAssetIndex.getSelectedViewMode(),
                }

                // Update settings
                this.settings = {...this.settings, ...settings};
                this._openConnectorModal();
            }
        },

        onClickAssetButton: function (event) {
            const {field, fieldId, fieldLimit, sourceElementId} = event;
            const settings = {
                fieldId, fieldLimit, sourceElementId,
                viewMode: 'list',
            }

            // Update settings
            this.settings = {...this.settings, ...settings};
            this.activeField = field;

            this._openConnectorModal();
        },

        _openConnectorModal: function () {
            const newSettings = {...this.settings, folderId: this.activeFolderId}
            if (!this.$connectorModal) {
                this.$connectorModal = new Craft.QbankConnectorModal(newSettings);

                Garnish.on(Craft.QbankConnectorModal, 'selectAsset', $.proxy(this, '_onSelectQdamAsset'))
            } else {
                this.$connectorModal.updateSettings(newSettings);
                this.$connectorModal.show();
            }
        },

        _onSelectQdamAsset: function (eventData) {
            const {elementInfo} = eventData;
            this.$connectorModal.hide();

            if (this.activeAssetIndex) {
                this.activeAssetIndex._uploadedAssetIds.push(elementInfo.id)
                this.activeAssetIndex._updateAfterUpload();
            }

            this.activeField = null;
        },

        _getAssetFields: () => {
            if (!this.$assetFields) {
                this.$assetFields = $('.field').filter((index, element) => {
                    const type = $(element).data('type');
                    return type && type.includes('craft\\fields\\Assets');
                })
            }

            return this.$assetFields;
        },

        _appendButton: function () {
            const $uploadButton = this.$modal.find('.btn.submit').first();
        },

        _getCurrentSource: () => {
            return Craft.elementIndex.sourceKey;
        }
    });
})(jQuery);