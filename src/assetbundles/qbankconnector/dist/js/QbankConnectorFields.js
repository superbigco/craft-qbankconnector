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
            this.settings = $.extend({}, this.settings, options);

            this.setup();
        },

        setup: function () {
            if (this._getAssetFields().length > 0) {
                this.setupAssetFields();
                this.getAccessToken();
            }
        },

        setupAssetFields: function () {
            Garnish.on(Craft.QbankField, 'onClickButton', $.proxy(this, 'onClickAssetButton'));
            var self = this;

            this
                ._getAssetFields()
                .each(function (index, field) {
                    var qbankField = new Craft.QbankField(field, self.settings);
                    self.qbankFields.push(qbankField);
                })
        },

        getAccessToken: function() {
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
                var sourceElement = e.target.sourcesByKey[e.sourceKey];
                var sourceData = sourceElement.data();
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
            this.settings = $.extend(this.settings, settings);
            this.activeField = event.field;

            this._openConnectorModal();
        },

        _openConnectorModal: function () {
            var newSettings = $.extend(this.settings, {folderId: this.activeFolderId});
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

            if (this.activeField) {
                this.activeField.selectElements([eventData.elementInfo]);
            } else if (this.activeAssetIndex) {

            }

            this.activeField = null;
            this.activeAssetIndex = null;
        },

        _getAssetFields: function () {
            if (!this.$assetFields) {
                this.$assetFields = $('.field').filter(function (index, element) {
                    var type = $(element).data('type');
                    var isAssetField = type ? type.indexOf('craft\\fields\\Assets') !== -1 : false;

                    return type && isAssetField;
                })
            }

            return this.$assetFields;
        },

        _appendButton: function () {
        },

        _getCurrentSource: function () {
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
            defaultSourceKey: null
        },
        $qbankConnectorButton: null,
        $fieldInstance: null,
        fieldId: null,
        fieldLimit: null,
        sourceElementId: null,
        init: function (field, options) {
            this.settings = $.extend({}, this.settings, options);

            this.setup(field);
        },

        setup: function (field) {
            var $field = $(field);
            var $select = $field.find('.elementselect');

            if ($select) {
                var elementSelect = $select.data('elementSelect');
                var $addElementBtn = elementSelect.$addElementBtn;
                var $qbankButton = $addElementBtn.clone().text(Craft.t('qbank-connector', 'Add from Qbank'));
                var fieldId = elementSelect.settings.fieldId,
                    sourceElementId = elementSelect.settings.sourceElementId,
                    limit = elementSelect.settings.limit;

                Garnish.on(Craft.AssetSelectInput, 'selectElements', $.proxy(this, 'onSelectElements'));
                Garnish.on(Craft.AssetSelectInput, 'removeElements', $.proxy(this, 'onRemoveElements'));
                Garnish.on(Craft.AssetSelectInput, 'selectionChange', $.proxy(this, 'onSelectionChange'));

                var fieldLimit = parseInt(limit);

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

                $select.addClass('elementselect--qbank');

                // Append button to Asset field
                $addElementBtn.parent().append($qbankButton);

                this.$qbankConnectorButton.on('click', $.proxy(this, 'onClick'));

                this._initialized = true;
            }
        },

        onSelectElements: function (event) {
            if (event.target === this.$fieldInstance) {
                this.updateAddElementsBtn();
            }
        },

        onRemoveElements: function (event) {
            if (event.target === this.$fieldInstance) {
                this.updateAddElementsBtn();
            }
        },

        onSelectionChange: function (event) {
            //var {elements, target} = event
        },

        onClick: function (e) {
            e.preventDefault();

            this.trigger('onClickButton', {
                field: this.$fieldInstance,
                fieldId: this.fieldId,
                fieldLimit: this.fieldLimit,
                sourceElementId: this.sourceElementId
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