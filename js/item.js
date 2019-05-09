var snowgirlApp = function (snowgirlCore) {
    this.core = snowgirlCore;

    this.initArgs();
//    this.initDOM();
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
//     this.btnCheckSelector = this.detailsSelector + ' .btn-check';
    this.btnShowRelatedSelector = this.detailsSelector + ' .btn-related';
    this.relatedSelector = '.related-items';

    if (this.core.getConfig('typeOwn', false)) {
        this.typeOwnSelector = '.type-own';
        this.btnCartSizeSelector = this.typeOwnSelector + ' .item-cart-sizes .btn-size';
        var tmp = this.typeOwnSelector + ' .item-cart-quantity';
        this.btnCartQuantityDecSelector = tmp + ' .btn-quantity-dec';
        this.inpCartQuantityValSelector = tmp + ' .inp-quantity-value';
        this.btnCartQuantityIncSelector = tmp + ' .btn-quantity-inc';
        this.btnCartAddSelector = this.typeOwnSelector + ' .item-cart-buttons .btn-add-to-cart';
    }

    this.core.addVisitedItem(this.core.getConfig('itemId'));
};
snowgirlApp.prototype.initCallbacks = function () {
//     var img = $(this.easyZoomSelector).find('img')[0];

//     if ((img.naturalHeight > img.height) || (img.naturalWidth > img.width)) {
    if (this.core.getConfig('deviceDesktop', false)) {
        $(this.easyZoomSelector).easyZoom({
            loadingNotice: 'Загружаю картинку...',
            errorNotice: 'К сожалению, картинка не может быть загружена',
            preventClicks: true
        });
    }

//         this.core.addWindowResizeCallback($.proxy(this.onWindowResize, this), true);
//     }

    this.core.$document
    //         .one('click', this.btnCheckSelector, $.proxy(this.onCheckButtonClick, this))
        .on('click', this.btnShowRelatedSelector, $.proxy(this.onShowRelatedButtonClick, this))
        .on('click', this.thumbnailsLinksSelector, $.proxy(this.onImageThumbnailClick, this))
        .on('click', this.ajaxLoaderButtonSelector, $.proxy(this.onAjaxLoaderClick, this));

    if (this.core.getConfig('typeOwn', false)) {
        this.core.$document
            .on('click', this.btnCartSizeSelector, $.proxy(this.onCartSizeButtonClick, this))
            .on('click', this.btnCartQuantityDecSelector, $.proxy(this.onCartQuantityDecButtonClick, this))
            .on('click', this.btnCartQuantityIncSelector, $.proxy(this.onCartQuantityIncButtonClick, this))
            .on('click', this.btnCartAddSelector, $.proxy(this.onCartAddButtonClick, this))
        ;

        $(this.btnCartSizeSelector + ':first').trigger('click');
    }

//     $(this.btnCheckSelector).trigger('click');
};
snowgirlApp.prototype.onCartSizeButtonClick = function (ev) {
    var $btn = $(ev.target).getButton();

    $(this.btnCartSizeSelector).removeClass('active');
    $btn.addClass('active');
};
snowgirlApp.prototype.onCartQuantityDecButtonClick = function (ev) {
    var $btn = $(ev.target).getButton();
    var value = parseInt($(this.inpCartQuantityValSelector).text());

    $(this.inpCartQuantityValSelector).text(--value);

    if (1 === value) {
        $btn.attr('disabled', true);
    }

    $btn.blur();
};
snowgirlApp.prototype.onCartQuantityIncButtonClick = function (ev) {
    var $btn = $(ev.target).getButton();
    var value = parseInt($(this.inpCartQuantityValSelector).text());

    $(this.inpCartQuantityValSelector).text(++value);

    if (value > 1) {
        $(this.btnCartQuantityDecSelector).attr('disabled', false);
    }

    $btn.blur();
};
snowgirlApp.prototype.getItemId = function () {
    return this.core.getConfig('itemId');
};
snowgirlApp.prototype.getSizeId = function () {
    return $(this.btnCartSizeSelector + '.active').data('id');
};
snowgirlApp.prototype.getQuantity = function () {
    return parseInt($(this.inpCartQuantityValSelector).text());
};
snowgirlApp.prototype.onCartAddButtonClick = function (ev) {
    window.location.href = this.core.getUriByRoute('default', {
        action: 'contacts',
        item_id: this.getItemId(),
        size_id: this.getSizeId(),
        quantity: this.getQuantity()
    });
    var $btn = $(ev.target).getButton();

//     this.core.addCartItem(this.getItemId(), this.getSize(), this.getQuantity(), function () {
//
//     });

    $btn.blur();
};
snowgirlApp.prototype.onCheckButtonClick = function (ev) {
    var $btn = $(ev.target).getButton();

    //@todo add loading state for image label also! (user shouldnt think that it is out of stock!)
    $btn.toggleLoading('Проверяю');

    var $label = $(this.imagesSelector + ' .item-out-of-stock');
//    $label.toggleLoading('Проверяю');

    var event = $.proxy(function (isInStock) {
        this.core.gtag.sendEvent('item_check', {
            _category: 'item is in stock',
            _action: 'click',
            _label: 'item',
            item_id: this.itemId,
            vendor_id: this.vendorId,
            in_stock: isInStock,
            value: null === isInStock ? 0 : !!isInStock
        });
    }, this);

    var unknown = $.proxy(function () {
        $(this.detailsSelector + ' .item-buttons')
            .append($('<a/>', {
                href: this.core.getUriByRoute('default', {
                    action: 'buy',
                    id: this.itemId,
                    src: 'item_check'
                }),
                class: 'btn btn-lg btn-primary',
                target: '_blank',
                rel: 'nofollow'
            })
                .append($('<span/>', {class: 'fa fa-refresh'}))
                .append(' ')
                .append('Проверить на ' + this.core.getConfig('vendorName')));
    }, this);

    this.core.makeRequestByRoute('default', {action: 'check-item-is-in-stock', id: this.itemId})
        .then($.proxy(function (body) {
            $btn.toggleLoading();

            if (!body.hasOwnProperty('in_stock')) {
                body = {in_stock: null};
            }

            if (true === body['in_stock']) {
                $label.remove();
                $(this.detailsSelector + ' .item-buttons')
                    .append($('<a/>', {
                        href: this.core.getUriByRoute('default', {
                            action: 'buy',
                            id: this.itemId,
                            src: 'item_photos'
                        }),
                        class: 'btn btn-lg btn-info btn-photos',
                        target: '_blank',
                        rel: 'nofollow',
                        text: 'Еще фото'
                    }))
                    .append(' ')
                    .append($('<a/>', {
                        href: this.core.getUriByRoute('default', {
                            action: 'buy',
                            id: this.itemId,
                            src: 'item_buy'
                        }),
                        class: 'btn btn-lg btn-primary btn-buy',
                        target: '_blank',
                        rel: 'nofollow'
                    })
                        .append($('<span/>', {class: 'fa fa-shopping-cart'}))
                        .append(' ')
                        .append('Купить на ' + this.core.getConfig('vendorName')));
                $btn.remove();
                event(true);
            } else if (false === body['in_stock']) {
                $label.addClass('checked');
                $(this.detailsSelector + ' .item-buttons')
                    .append($('<span/>', {class: 'btn btn-lg btn-out-of-stock'}))
                    .append($('<button/>', {
                        type: 'button',
                        class: 'btn btn-lg btn-primary btn-related'
                    })
                        .append($('<span/>', {class: 'fa fa-random'}))
                        .append(' ')
                        .append('Похожие'));
                $btn.remove();
                event(false);
            } else {
//                $label.toggleLoading();
                unknown();
                $btn.remove();
                event(null);
            }
        }, this))
        .catch(function () {
//            $label.toggleLoading();
            unknown();
            $btn.remove();
            event(null);
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
//                $window.scrollTop($ajaxLoader.offset().top - window.innerHeight + $ajaxLoader.outerHeight() + 10);
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