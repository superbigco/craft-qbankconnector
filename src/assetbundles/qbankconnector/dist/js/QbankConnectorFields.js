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

if (typeof Craft.QbankConnectorFields === typeof undefined) {
    Craft.QbankConnectorFields = {};
}

(function ($) {
    Craft.QbankConnectorFields = Garnish.Base.extend({
        settings: {
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
            this.settings = {...this.settings, ...options}

            this.setup();
        },

        setup: function () {
            if (this._getAssetFields().length > 0) {
                this.setupAssetFields();
                this.getAccessToken();
            }
        },

        setupAssetFields: function () {
            Garnish.on(Craft.QbankField, 'onClickButton', $.proxy(this, 'onClickAssetButton'))

            this
                ._getAssetFields()
                .each((index, field) => {
                    const qbankField = new Craft.QbankField(field, this.settings);
                    this.qbankFields.push(qbankField);
                })
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

        onInputInit: function () {
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
                const sourceElement = e.target.sourcesByKey[e.sourceKey];
                const sourceData = sourceElement.data();
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
            const {media, crop, previousUsage, elementInfo} = eventData;
            const {$element} = elementInfo;
            this.$connectorModal.hide();

            if (this.activeField) {
                this.activeField.selectElements([elementInfo]);
            } else if (this.activeAssetIndex) {

            }

            this.activeField = null;
            this.activeAssetIndex = null;
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
        },

        _getCurrentSource: () => {
            return Craft.elementIndex.sourceKey;
        }
    });
})(jQuery);

// Asset Fields
(function ($) {
    Craft.QbankField = Garnish.Base.extend({
        settings: {
            sessionSourceId: null,
            deploymentSiteId: null,
            currentSource: null,
            qbankAccessToken: null,
            qbankBaseUrl: null,
            qbankBaseDomain: null,
            defaultImageSize: 1000,
            defaultImageExtension: 'jpg',
            defaultSourceKey: null,
        },
        $qbankConnectorButton: null,
        $fieldInstance: null,
        fieldId: null,
        fieldLimit: null,
        sourceElementId: null,
        init: function (field, options) {
            this.settings = {...this.settings, ...options}

            this.setup(field);
        },

        setup: function (field) {
            const $field = $(field)
            const $select = $field.find('.elementselect');

            if ($select) {
                const elementSelect = $select.data('elementSelect');
                const $addElementBtn = elementSelect.$addElementBtn;
                const $qbankButton = $addElementBtn.clone().text(Craft.t('qbank-connector', 'Add from Qbank'));
                const {fieldId, sourceElementId, limit} = elementSelect.settings;

                Garnish.on(Craft.AssetSelectInput, 'selectElements', $.proxy(this, 'onSelectElements'));
                Garnish.on(Craft.AssetSelectInput, 'removeElements', $.proxy(this, 'onRemoveElements'));
                Garnish.on(Craft.AssetSelectInput, 'selectionChange', $.proxy(this, 'onSelectionChange'));

                let fieldLimit = parseInt(limit);

                if (isNaN(fieldLimit)) {
                    fieldLimit = null;
                }

                this.fieldLimit = fieldLimit;
                this.fieldId = fieldId;
                this.sourceElementId = sourceElementId;
                this.$qbankConnectorButton = $qbankButton;
                this.$fieldInstance = elementSelect;

                if (this.fieldLimit === 1) {
                    $qbankButton
                        .css('top', $addElementBtn.outerHeight() + 9)
                    // .css(Craft.left, 0);
                }

                // Append button to Asset field
                $addElementBtn.parent().append($qbankButton);

                this.$qbankConnectorButton.on('click', $.proxy(this, 'onClick'))

                this._initialized = true;
            }
        },

        onSelectElements: function (event) {
            const {elements, target} = event

            if (target === this.$fieldInstance) {
                this.updateAddElementsBtn();
            }
        },

        onRemoveElements: function (event) {
            const {elements, target} = event

            if (target === this.$fieldInstance) {
                this.updateAddElementsBtn();
            }
        },

        onSelectionChange: function (event) {
            //const {elements, target} = event
        },

        onClick: function (e) {
            e.preventDefault();

            this.trigger('onClickButton', {
                field: this.$fieldInstance,
                fieldId: this.fieldId,
                fieldLimit: this.fieldLimit,
                sourceElementId: this.sourceElementId,
            });
        },

        updateAddElementsBtn: function () {
            if (this.$fieldInstance.canAddMoreElements()) {
                this.enableAddElementsBtn();
            } else {
                this.disableAddElementsBtn();
            }
        },

        disableAddElementsBtn: function () {
            if (this.$qbankConnectorButton && !this.$qbankConnectorButton.hasClass('disabled')) {
                this.$qbankConnectorButton.addClass('disabled');

                if (this.fieldLimit === 1) {
                    if (this._initialized) {
                        this.$qbankConnectorButton.velocity('fadeOut', Craft.BaseElementSelectInput.ADD_FX_DURATION);
                    } else {
                        this.$qbankConnectorButton.hide();
                    }
                }
            }
        },

        enableAddElementsBtn: function () {
            if (this.$qbankConnectorButton && this.$qbankConnectorButton.hasClass('disabled')) {
                this.$qbankConnectorButton.removeClass('disabled');

                if (this.fieldLimit === 1) {
                    if (this._initialized) {
                        this.$qbankConnectorButton.velocity('fadeIn', Craft.BaseElementSelectInput.REMOVE_FX_DURATION);
                    } else {
                        this.$qbankConnectorButton.show();
                    }
                }
            }
        }
    });
})(jQuery);
// Asset Modal