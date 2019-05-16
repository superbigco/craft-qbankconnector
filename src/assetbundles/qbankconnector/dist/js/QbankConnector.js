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
    Craft.QbankConnector.Init = Garnish.Base.extend({
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
        init: function (options) {
            Garnish.on(Craft.AssetSelectorModal, 'show', $.proxy(this, 'onAssetModalOpen'));
            //Garnish.on(Craft.AssetSelectorModal, 'fadeIn', $.proxy(this, 'onFadeIn'));
            Garnish.on(Craft.AssetSelectorModal, 'afterInit', $.proxy(this, 'onFadeIn'));
            Garnish.on(Craft.Uploader, 'afterInit', $.proxy(this, 'onFadeIn'));
            Garnish.on(Craft.AssetSelectorModal, 'hide', $.proxy(this, 'onAssetModalHide'));
            Garnish.on(Craft.AssetSelectInput, 'init', $.proxy(this, 'onInputInit'));
            Garnish.on(Craft.AssetIndex, 'afterInit', $.proxy(this, 'onAssetIndex'));
            Garnish.on(Craft.AssetIndex, 'selectSource', $.proxy(this, 'onSelectSource'));

            this.settings = {...this.settings, ...options}

            this.setup();
            //this.setupConnector();


            if (Craft.elementIndex) {
                Craft.elementIndex.on('selectSource', $.proxy(this, 'onSelectSource'));
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

            this
                ._getAssetFields()
                .each((index, field) => {
                    const qbankField = new Craft.QbankField(field, this.settings);
                    this.qbankFields.push(qbankField);


                    /*
                    const $field = $(field)
                    const $select = $field.find('.elementselect');

                    if ($select) {
                        const elementSelect = $select.data('elementSelect');
                        const $addElementBtn = elementSelect.$addElementBtn;
                        const $qbankButton = $addElementBtn.clone().text(Craft.t('qbank-connector', 'Add from Qbank'));

                        $addElementBtn.parent().append($qbankButton);
                    }*/
                })
        },

        getAccessToken() {
            Craft.postActionRequest('qbank-connector/default/access-token', response => {
                const {token} = response;

                if (!token) {
                    Craft.cp.displayError(Craft.t('qbank-connect', 'Could not get access token from QBank'))
                }

                this.settings.qbankAccessToken = token;
                console.log(this.settings);
                this.setupConnector();
            });
        },

        setupConnector() {

        },

        onAssetModalOpen: function () {
            this.$modal = $('.modal.elementselectormodal').first();
            console.log('Showing asset modal', this.$modal.find('.footer').first().html());

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
            $('.elementselect').each(function () {
                console.log($(this).data('elementSelect'));
            })
            console.log('Input init')
        },

        onAssetModalHide: function () {
            this.$modal = null;
            console.log('Hiding asset modal', this.$modal);
        },

        onSelectElements: function () {
            console.log('Selecting elements', arguments);
        },

        onRemoveElements: function () {
            console.log('Removing elements', this.$modal);
        },
        onSelectSource: function (e) {
            if (typeof e.target.sourcesByKey !== 'undefined') {
                const sourceElement = e.target.sourcesByKey[e.sourceKey];
                const sourceData = sourceElement.data();

                console.log(sourceElement, sourceElement.data())
            }
            console.log('onSelectSource', arguments, e.target, e.target.sourcesByKey);
        },

        onAssetIndex: function () {
            const $uploadButton = this.$modal.find('.btn.submit[data-icon="upload"]');

            if ($uploadButton.length) {
                const $qbankButton = $uploadButton.clone().text(Craft.t('qbank-connector', 'Upload from QBank'));
                $uploadButton.parent().append($qbankButton)
            }

            // console.log(this.$modal, arguments);
            // console.log(this.$modal.find('.btn.submit[data-icon="upload"]'));
            // console.log(this.$modal.data('modal'))
        },

        onClickAssetButton: function (event) {
            const {field, fieldId, fieldLimit, sourceElementId} = event;
            const settings = {
                fieldId, fieldLimit, sourceElementId,
                //siteId: Craft.siteId,
                viewMode: 'list',
            }

            // Update settings
            this.settings = {...this.settings, ...settings};
            this.activeField = field;
            console.log('Open modal now', event)
            this._openConnectorModal();

            console.log(this)
        },

        _openConnectorModal: function () {
            if (!this.$connectorModal) {
                this.$connectorModal = new Craft.QbankConnectorModal(this.settings);

                Garnish.on(Craft.QbankConnectorModal, 'selectAsset', $.proxy(this, '_onSelectQdamAsset'))
            } else {
                this.$connectorModal.updateSettings(this.settings);
                this.$connectorModal.show();
            }
        },

        _onSelectQdamAsset: function (eventData) {
            const {media, crop, previousUsage, elementInfo} = eventData;
            const {$element} = elementInfo;
            this.$connectorModal.hide();

            console.log({media, crop, previousUsage, elementInfo});

            if (this.activeField) {
                this.activeField.selectElements([ elementInfo ]);
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
            console.log(this.$modal.data('modal'))
            //debugger;
            const $uploadButton = this.$modal.find('.btn.submit').first();
            //console.log(this.$modal.find('.btn.submit'))
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

                elementSelect.on('selectElements', () => { console.log(arguments) });
                Garnish.on(Craft.AssetSelectInput, 'selectElements', $.proxy(this, 'onSelectElements'));
                Garnish.on(Craft.AssetSelectInput, 'removeElements', $.proxy(this, 'onRemoveElements'));
                Garnish.on(Craft.AssetSelectInput, 'selectionChange', $.proxy(this, 'onSelectionChange'));

                this.fieldLimit = parseInt(limit);
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

                // Setup event listeners
                // this.addListener(elementSelect.elementSelect, 'selectElements', 'onSelectElements')
                // this.addListener(elementSelect, 'removeElements', $.proxy(this, 'onRemoveElements'))

                console.log($select.data('elementSelect'));

                this._initialized = true;
            }
        },

        onSelectElements: function (event) {
            const {elements, target} = event

            if (target === this.$fieldInstance) {
                this.updateAddElementsBtn();
            }
            console.log('Selecting elements', target, target === this.$fieldInstance);
        },

        onRemoveElements: function (event) {
            const {elements, target} = event

            if (target === this.$fieldInstance) {
                this.updateAddElementsBtn();
            }
            console.log('Removing elements', target, target === this.$fieldInstance);
        },

        onSelectionChange: function (event) {
            const {elements, target} = event
            console.log('Selection change', target, target === this.$fieldInstance);
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

(function ($) {
    Craft.QbankConnectorModal = Garnish.Modal.extend({
        settings: {
            sessionSourceId: null,
            deploymentSiteId: null,
            currentSource: null,
            qbankAccessToken: null,
            qbankBaseUrl: null,
            qbankBaseDomain: null,
            defaultImageSize: 1000,
            defaultImageExtension: 'jpg',
            $fieldInstance: null,
            folderId: null,
            fieldId: null,
            fieldLimit: null,
            sourceElementId: null,
            siteId: Craft.siteId,
            viewMode: 'list',
        },
        $container: null,
        $footer: null,
        $body: null,
        $buttons: null,
        $closeBtn: null,
        $cancelBtn: null,
        $saveBtn: null,
        $footerSpinner: null,
        loading: null,
        progressBar: null,

        init: function (container, settings) {
            var viewportWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
            var viewportHeight = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;
            this.desiredWidth = viewportWidth - 100;
            this.desiredHeight = viewportHeight - 100;

            // Param mapping
            if (settings === undefined && $.isPlainObject(container)) {
                settings = container;
                container = null;
            }

            this.setSettings(settings, Garnish.Modal.defaults);

            // console.log(this.settings);

            // Build the modal
            this.$container = $('<div class="qbank-connector-modal modal elementselectormodal"></div>').appendTo(Garnish.$bod);
            this.$footer = $('<div class="footer"/>').appendTo(this.$container);

            var bodyHtml = '<div class="body">' +
                '<div class="content">' +
                '<div class="main" id="js-qbankModalWrapper">' +
                '</div>' +
                '</div>' +
                '</div>';

            $body = $(bodyHtml).appendTo(this.$container);
            this.$body = $body;

            this.base(this.$container, this.settings);

            this.$mainContainer = this.$body.find('.main');
            this.$referenceNumber = this.$body.find('.js-reference');
            this.progressBar = new Craft.ProgressBar($('<div class="progress-shade"></div>').appendTo(this.$mainContainer));

            //this.$recipientInput = this.$body.find('.js-recipientInput');
            //this.$previewInput = this.$body.find('.js-previewInput');
            this.$footerSpinner = $('<div class="spinner hidden"/>').appendTo(this.$toolbar);
            this.$buttons = $('<div class="qbank-modal-buttons buttons right"/>').appendTo(this.$footer);
            this.$closeBtn = $('<div class="btn close hidden">' + Craft.t('qbank-connector', 'Close') + '</div>').appendTo(this.$buttons);
            this.$cancelBtn = $('<div class="btn cancel">' + Craft.t('qbank-connector', 'Cancel') + '</div>').appendTo(this.$buttons);
            //this.$saveBtn = $('<div class="btn submit disabled">' + Craft.t('qbank-connector', 'Download') + '</div>').appendTo(this.$buttons);
            this.$statusContainer = $('<div class="passport-status-container hidden"></div>').appendTo(this.$container);

            //this.updateModal(this.settings);

            this.addListener(this.$closeBtn, 'activate', 'cancel');
            this.addListener(this.$cancelBtn, 'activate', 'cancel');
            //this.addListener(this.$saveBtn, 'activate', 'submit');

            this.setupConnector();
            /*
            this.$recipientInput.on('input', e => {
                this.$saveBtn.toggleClass('disabled', this.$recipientInput.val().length === 0);
            });
            */
        },

        setupConnector() {
            const qbcConfig = {
                deploymentSite: this.settings.deploymentSiteId,
                api: {
                    host: this.settings.qbankBaseDomain,
                    access_token: this.settings.qbankAccessToken,
                    protocol: 'https'
                },
                gui: {
                    basehref: this.settings.qbankBaseUrl
                }
            };
            //const $wrapper = $('<div class="qbank-modal" id="js-qbankWrapper" />').appendTo(Garnish.$bod);
            const QBC = new QBankConnector(qbcConfig);
            const mediaPicker = new QBC.mediaPicker({
                container: '#js-qbankModalWrapper',
                defaultUseSize: this.settings.defaultImageSize,
                defaultUseExtension: this.settings.defaultImageExtension,
                modules: {
                    content: {
                        header: false
                    }
                },
                onSelect: this.onSelect.bind(this),
            });
        },

        onSelect(media, crop, previousUsage) {
            //console.log(media, crop, previousUsage)
            //console.log('selected', media, crop, previousUsage);
            //$('#loader').show();
            //$('div.media-modal-backdrop').addClass('media-modal-backdrop-on-top');

            if (!previousUsage) {
                var data = {
                    //action: 'qbank_process_media_import',
                    //data: {media: JSON.stringify(media), crop: JSON.stringify(crop)}
                };

                /*
                $.post('<?= admin_url('admin-ajax.php') ?>', data, function (response) {
                    //console.log(response);
                    $('#loader').hide();
                    $('#loader-msg').show().delay(3000).fadeOut();
                    switchAndReload();

                });
                */

            } else {
                // This might be used in future version.
                //console.log('use this image:' + previousUsage.mediaUrl);
            }

            this._onDownloadStart();

            let currentInterval = 20;
            this.uploadTimer = setInterval(() => {
                currentInterval = currentInterval + 5;
                if (currentInterval > 100) {
                    currentInterval = 100;
                }
                this.progressBar.setProgressPercentage(currentInterval, true);
            }, 500);

            const self = this;
            const firstCrop = crop[0];
            const {filename, mediaId, name, dimensions, objectId} = media;
            const payload = {
                url: firstCrop.url,
                crop: firstCrop,
                media: {filename, mediaId, name, dimensions, objectId},
                folderId: this.folderId,
                fieldId: this.fieldId,
                fieldLimit: this.fieldLimit,
                sourceElementId: this.sourceElementId,
            }

            Craft.postActionRequest('qbank-connector/default/download-asset', payload, (response) => {
                self._onDownloadComplete({media, crop, previousUsage, response});
            });

            //this.hide();
        },

        /**
         * On upload start.
         */
        _onDownloadStart: function (data) {

            this.progressBar.$progressBar.css({
                top: Math.round(this.$container.outerHeight() / 2) - 6
            });

            this.$container.addClass('uploading');
            //this.progressBar.setItemCount(3);
            //this.progressBar.setProcessedItemCount(3);
            this.progressBar.resetProgressBar();
            this.progressBar.showProgressBar();
            this.progressBar.setProgressPercentage(20, true);
        },

        _onDownloadComplete: function (event) {
            const self = this;
            const {media, crop, previousUsage, response} = event;
            const payload = {
                elementId: response.assetId,
                siteId: this.settings.siteId,
                size: this.settings.viewMode
            };

            Craft.postActionRequest('elements/get-element-html', payload, (data) => {
                clearInterval(self.uploadTimer);
                self.progressBar.hideProgressBar();
                self.$container.removeClass('uploading');
                self.progressBar.setProgressPercentage(100, true);

                if (data.error) {
                    Craft.cp.displayError(data.error);
                } else {
                    const html = $(data.html);

                    console.log(Craft.getElementInfo(html))

                    //console.log(self, data);
                    self.trigger('selectAsset', {
                        ...event,
                        elementInfo: Craft.getElementInfo(html)
                    });

                }

                //console.log(self, data);
                //self.trigger('selectAsset', data);
            })
        },

        onFadeOut: function () {
            //this.hide();

            this.cleanup();
        },

        cleanup: function () {
            //this.$recipientInput.val('');
        },

        submit: function () {
            const {} = this.settings;
        },

        cancel: function () {
            this.hide();
        },

        showSpinner: function () {
            this.$footerSpinner.removeClass('hidden');
        },

        hideSpinner: function () {
            this.$footerSpinner.addClass('hidden');
        },

        updateSelectBtnState: function () {
            if (this.$saveBtn) {
                if (this.elementIndex.getSelectedElements().length) {
                    this.enableSelectBtn();
                } else {
                    this.disableSelectBtn();
                }
            }
        },

        enableSelectBtn: function () {
            this.$saveBtn.removeClass('disabled');
        },

        disableSelectBtn: function () {
            this.$saveBtn.addClass('disabled');
        },

        showSidebar: function () {
            this.$sidebar.removeClass('hidden');
        },

        hideSidebar: function () {
            this.$sidebar.addClass('hidden');
        },

        showElements: function () {
            this.$elements.removeClass('hidden');
        },

        hideElements: function () {
            this.$elements.addClass('hidden');
        },

        updateSettings: function (settings) {
            this.settings = {...this.settings, ...settings};
        }
    });
})(jQuery);