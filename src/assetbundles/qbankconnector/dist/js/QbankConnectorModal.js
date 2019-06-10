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
            this.updateSettings(settings)

            // Build the modal
            this.$container = $('<div class="qbank-connector-modal modal elementselectormodal"></div>').appendTo(Garnish.$bod);
            this.$footer = $('<div class="footer"/>').appendTo(this.$container);

            var bodyHtml = '<div class="body">' +
                '<div class="content">' +
                '<div class="main">' +
                '</div>' +
                '</div>' +
                '</div>';

            $body = $(bodyHtml).appendTo(this.$container);
            this.$body = $body;

            this.base(this.$container, this.settings);

            this.$mainContainer = this.$body.find('.main');
            this.$referenceNumber = this.$body.find('.js-reference');
            this.progressBar = new Craft.ProgressBar($('<div class="progress-shade"></div>').appendTo(this.$mainContainer));
            this.$footerSpinner = $('<div class="spinner hidden"/>').appendTo(this.$toolbar);
            this.$buttons = $('<div class="qbank-modal-buttons buttons right"/>').appendTo(this.$footer);
            this.$closeBtn = $('<div class="btn close hidden">' + Craft.t('qbank-connector', 'Close') + '</div>').appendTo(this.$buttons);
            this.$cancelBtn = $('<div class="btn cancel">' + Craft.t('qbank-connector', 'Cancel') + '</div>').appendTo(this.$buttons);
            this.$statusContainer = $('<div class="status-container hidden"></div>').appendTo(this.$container);

            this.addListener(this.$closeBtn, 'activate', 'cancel');
            this.addListener(this.$cancelBtn, 'activate', 'cancel');
        },

        onFadeIn() {
            this.base(this.$container, this.settings);
            this.setupConnector();
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

            if (this._connector) {
                this._connector = null;
                this._mediaPicker = null;

                this.$mainContainer.find('iframe').remove();
            }

            if (!this._connector) {
                this._connector = new QBankConnector(qbcConfig);
                this._mediaPicker = new this._connector.mediaPicker({
                    container: this.$mainContainer,
                    defaultUseSize: this.settings.defaultImageSize,
                    defaultUseExtension: this.settings.defaultImageExtension,
                    modules: {
                        content: {
                            header: false
                        }
                    },
                    onSelect: this.onSelect.bind(this),
                });
            }
        },

        onSelect(media, crop, previousUsage) {
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
                folderId: this.settings.folderId,
                fieldId: this.settings.fieldId,
                fieldLimit: this.settings.fieldLimit,
                sourceElementId: this.settings.sourceElementId,
            }

            Craft.postActionRequest('qbank-connector/default/download-asset', payload, (response) => {
                self._onDownloadComplete({media, crop, previousUsage, response});
            });
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
            const {response} = event;
            const payload = {
                elementId: response.assetId,
                siteId: this.settings.siteId,
                viewMode: this.settings.viewMode
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

                    self.trigger('selectAsset', {
                        ...event,
                        elementInfo: Craft.getElementInfo(html)
                    });

                }
            })
        },

        onFadeOut: function () {
            //this.hide();
        },

        submit: function () {
            const {} = this.settings;
        },

        cancel: function () {
            this.hide();
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

        updateSettings: function (settings) {
            this.settings = {...this.settings, ...settings};
        }
    });
})(jQuery);