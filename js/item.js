var snowgirlApp = function (snowgirlCore) {
    this.core = snowgirlCore;

    this.initArgs();
    this.initCallbacks();
};
snowgirlApp.prototype.initArgs = function () {
    this.itemId = this.core.getConfig('itemId');
    this.vendorId = this.core.getConfig('vendorId');
    this.imagesSelector = '.images';
    this.easyZoomSelector = this.imagesSelector + ' .easyzoom';
    this.thumbnailsLinksSelector = this.imagesSelector + ' .item-thumbnails a';
    this.ajaxLoaderSelector = '.items-ajax-loader';
    this.ajaxLoaderButtonSelector = this.ajaxLoaderSelector + ' .btn';
    this.detailsSelector = '.details';
    this.btnsSelector = this.detailsSelector + ' .item-buttons';
    this.btnCheckSelector = this.btnsSelector + ' .btn-check';
    this.btnRelatedSelector = this.btnsSelector + ' .btn-related';
    this.btnDetailsSelector = this.btnsSelector + ' .btn-details';
    this.btnBuySelector = this.btnsSelector + ' .btn-buy';
    this.relatedSelector = '.related-items';
};
snowgirlApp.prototype.initCallbacks = function () {
    if (this.core.getConfig('deviceDesktop', false)) {
        $(this.easyZoomSelector).easyZoom({
            loadingNotice: 'Загружаю картинку...',
            errorNotice: 'К сожалению, картинка не может быть загружена',
            preventClicks: true
        });
    }

    this.core.$document
        .on('click', this.btnRelatedSelector, $.proxy(this.onShowRelatedButtonClick, this))
        .on('click', this.thumbnailsLinksSelector, $.proxy(this.onImageThumbnailClick, this))
        .on('click', this.ajaxLoaderButtonSelector, $.proxy(this.onAjaxLoaderClick, this));

    if (this.core.getConfig('isInStockCheck', false) &&
        this.core.getConfig('isInStock', false)) {
        this.checkIsInStock();
    }
};
snowgirlApp.prototype.getItemId = function () {
    return this.core.getConfig('itemId');
};
snowgirlApp.prototype.checkIsInStock = function () {
    this.core.makeRequestByRoute('default', {action: 'check-item-is-in-stock', id: this.itemId})
        .then($.proxy(function (body) {
            if (!body.hasOwnProperty('in_stock')) {
                body = {in_stock: null};
            }

            if (false === body['in_stock']) {
                $(this.btnsSelector)
                    .prepend($('<span/>', {class: 'btn btn-lg btn-out-of-stock'}));
                $(this.btnDetailsSelector).remove();
                $(this.btnBuySelector).remove();
            }
        }, this))
        .catch(function () {
        });
};
snowgirlApp.prototype.onShowRelatedButtonClick = function () {
    $('html, body').animate({scrollTop: $(this.relatedSelector).offset().top}, 500);
    this.core.gtag.sendEvent('item_show_related', {
        _category: 'show related button',
        _action: 'click',
        _label: 'item',
        item_id: this.itemId,
        vendor_id: this.vendorId
    });
};
snowgirlApp.prototype.onWindowResize = function (width) {
    //@todo optimize... change in case if they are different only...
    var api = $(this.easyZoomSelector).data('easyZoom');

    if (width < 992) {
        api.teardown();
    } else {
        api._init();
    }
};
snowgirlApp.prototype.onImageThumbnailClick = function (ev) {
    ev.preventDefault();
    var $this = $(ev.target).closestUp('a');

    $(this.easyZoomSelector).data('easyZoom').swap($this.data('standard'), $this.attr('href'));
};
snowgirlApp.prototype.onAjaxLoaderClick = function (ev) {
    var $btn = $(ev.target).getButton();

    if ($btn.data('isLoading')) {
        return true;
    }

    $btn.data('isLoading', true);

    var request = {action: 'get-catalog-items'};

    var params = {};
    params = $.extend(true, {}, params, this.core.getConfig('filterParams', {}));
    params = $.extend(true, {}, params, this.core.getConfig('viewParams', {}));

    request = $.extend(true, {}, request, params);

    var page = $btn.data('page');

    if (page) {
        request.page = page;
    } else if (!request.hasOwnProperty('page')) {
        request.page = 1;
    }

    request.page++;

    $btn.addClass('disabled').attr('disabled', 'disabled')
        .find('.fa').addClass('fa-spin').end()
        .find('.text').text('Загружаю');

    this.core.makeRequestByRoute('default', request)
        .then($.proxy(function (body) {
            if (!body.view) {
                $(this.ajaxLoaderSelector).remove();
            } else {
                var $window = $(window);
                var tmp = $window.scrollTop();
                $('.widget-grid-items .row').append(body.view);
                $window.scrollTop(tmp);

                if (('isLastPage' in body) && body['isLastPage']) {
                    $(this.ajaxLoaderSelector).remove();
                } else {
                    $btn.data('page', request.page);
                    $btn.removeClass('disabled').attr('disabled', null)
                        .find('.fa').removeClass('fa-spin').end()
                        .find('.text').text('Показать еще');
                }

                $btn.data('isLoading', false);
            }

            if (('pageUri' in body) && window.ga) {
                window.ga('set', 'page', body['pageUri']);
                window.ga('send', 'pageview');
            }
        }, this));

    if (params.hasOwnProperty('page')) {
        delete params.page;
    }

    this.core.gtag.sendEvent('item_load_more_items', {
        _category: 'load more button',
        _action: 'click',
        _label: 'item',
        item_id: this.itemId,
        vendor_id: this.vendorId,
        page: request.page
    });
};

new snowgirlApp(snowgirlCore);