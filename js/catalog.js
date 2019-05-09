var snowgirlApp = function (snowgirlCore) {
    this.core = snowgirlCore;

    this.initArgs();
    this.initDOM();
    this.initCallbacks();
};

snowgirlApp.prototype.initArgs = function () {
    this.classMobileScreen = 'mobile-screen';
    this.selectorMobileScreen = '.' + this.classMobileScreen;
    this.selectorCatalog = '.catalog';
    this.selectorMobileControl = '.mobile-control';
    this.selectorFilters = this.selectorCatalog + ' .filters';
    this.selectorCategories = this.selectorCatalog + ' .categories';
    this.selectorOtherFilters = this.selectorCatalog + ' .other-filters';
    this.selectorCategoriesTree = this.selectorCategories + ' #category-tree';
    this.selectorMobileScreenCategoriesTree = this.selectorMobileScreen + ' ' + this.selectorCategoriesTree;
    this.classMobileOnly = 'mobile-only';
    this.classMobileHidden = 'mobile-hidden';
    this.classMobileCategoriesBack = 'mobile-back';
    this.selectorMobileCategoriesBack = '.' + this.classMobileCategoriesBack;
    this.selectorMobileScreenCategoriesBack = this.selectorMobileScreen + ' ' + this.selectorCategories + ' .' + this.classMobileCategoriesBack;
    this.classMobileCategoriesShowAll = 'mobile-show-all';
    this.selectorMobileCategoriesShowAll = '.' + this.classMobileCategoriesShowAll;
    this.selectorAjaxLoader = '.items-ajax-loader';
    this.selectorAjaxLoaderButton = this.selectorAjaxLoader + ' .btn';
    this.selectorViews = this.selectorCatalog + ' .views';
    this.filtersMap = {
        'type': 'types',
        'tag': 'tags',
        'brand': 'brands',
        'season': 'seasons',
        'color': 'colors',
        'price': 'prices',
        'material': 'materials',
        'country': 'countries',
        'size': 'sizes',
        'vendor': 'vendors'
    };
};
snowgirlApp.prototype.initDOM = function () {
    if (this.core.getConfig('hasItems', false)) {
        if (this.core.getConfig('asyncFilters', false)) {
            this.initFiltersDOM();
        }

        this.initMobileDOM();
    }
};
snowgirlApp.prototype.initFiltersDOM = function () {
    for (var attr in this.filtersMap) {
        if (this.filtersMap.hasOwnProperty(attr)) {
            var params = {action: 'get-catalog-filters-' + this.filtersMap[attr] + '-view'};
            var $filter = $(this.selectorOtherFilters + ' .' + this.filtersMap[attr]);

            if ($filter.length > 0) {
                this.core.makeRequestByRoute('default', params, 'get', this.core.getConfig('filterParams'))
                    .then(($.proxy(function ($filter) {
                        return $.proxy(function (body) {
                            if (body.view) {
                                $filter.find('.scroll').html(body.view);
                                $filter.find('.section-body').togglePending();
                            } else {
                                $filter.remove();
                            }
                        }, this);
                    }, this))($filter));
            }
        }
    }
};
snowgirlApp.prototype.initMobileDOM = function () {
    var btnClass = 'btn-sm';
    $('<div/>', {class: 'col-md-9 col-lg-10 right mobile-control ' + this.classMobileOnly + ' visible-xs visible-mb visible-sm'})
        .append($('<div/>', {class: 'row'})
            .append($('<div/>', {class: 'col-xs-5'})
                .append($('<button/>', {
                    type: 'button',
                    class: 'btn ' + btnClass + ' btn-categories btn-toggle-sm-categories',
                    text: 'Категории',
                    'data-icon-toggle': "fa fa-caret-up"
                }).append(' <span class="fa fa-caret-down"></span>')))
            .append($('<div/>', {class: 'col-xs-5'})
                .append($('<button/>', {
                    type: 'button',
                    class: 'btn ' + btnClass + ' btn-other-filters btn-toggle-sm-other-filters',
                    text: 'Фильтры',
                    'data-icon-toggle': "fa fa-caret-up"
                }).append(' <span class="fa fa-caret-down"></span>')))
            .append($('<div/>', {class: 'col-xs-2'})
                .append($('<button/>', {
                    type: 'button',
                    class: 'btn ' + btnClass + ' btn-views btn-toggle-sm-views',
                    html: '<span class="fa fa-sort-amount-desc"></span>'
                }))))
        .insertAfter($(this.selectorCatalog).find('.title'));
};
snowgirlApp.prototype.initCallbacks = function () {
    this.core.addWindowResizeCallback($.proxy(this.onWindowResize, this), true);

    this.core.$document
        .on('click', this.selectorOtherFilters + ' input[type=checkbox]', $.proxy(this.onFiltersCheckboxClick, this))
        .on('input', this.selectorOtherFilters + ' .colors.ajax-suggestions input[type=text]', $.proxy(this.onColorAttrAjaxSuggestionsSearchKeyUp, this))
        .on('input', this.selectorOtherFilters + ' .ajax-suggestions:not(.colors) input[type=text]', $.proxy(this.onAttrAjaxSuggestionsSearchKeyUp, this))
        .on('input', this.selectorOtherFilters + ' .suggestions input[type=text]', $.proxy(this.onAttrSuggestionsSearchKeyUp, this))
        .on('change', this.selectorViews + ' select', $.proxy(this.onViewsOrderChange, this))
        .on('click', this.selectorViews + ' .link-show', $.proxy(this.onViewsPerPageClick, this))
        .on('click', this.selectorMobileControl + ' .btn', $.proxy(this.onMobileControlButtonsClick, this))
        .one('click', this.selectorMobileControl + ' .btn-views', $.proxy(this.onMobileViewsButtonClick, this))
        .one('click', this.selectorMobileControl + ' .btn-categories', $.proxy(this.onMobileCategoriesButtonClick, this))
        .on('click', this.selectorOtherFilters + '.toggle-on .section-title', $.proxy(this.onMobileFiltersSectionClick, this))
        .on('click', this.selectorAjaxLoaderButton, $.proxy(this.onAjaxLoaderClick, this));
};
snowgirlApp.prototype.initMobileCategoriesEventHandlers = function () {
    this.core.$document
        .on('click', this.selectorMobileScreenCategoriesBack, $.proxy(this.onMobileCategoriesBackClick, this))
        .on('click', this.selectorMobileScreenCategoriesTree + ' .item', $.proxy(this.onMobileCategoriesItemClick, this));
};
snowgirlApp.prototype.onWindowResize = function (width) {
    //@todo optimize... change in case if they are different only...
    if (width < 992) {
        this.core.$body.addClass(this.classMobileScreen);
    } else {
        this.core.$body.removeClass(this.classMobileScreen);
    }
};
snowgirlApp.prototype.onFiltersCheckboxClick = function (ev) {
    ev.preventDefault();
    window.location.href = $(ev.target).closestUp('a').attr('href');
};
snowgirlApp.prototype.onColorAttrAjaxSuggestionsSearchKeyUp = function (ev) {
    var $this = $(ev.target);
    var $addon = $this.closestUp('.input-group').find('.input-group-addon span');

    $addon.attr('class', 'fa fa-refresh fa-spin');

    var $section = $this.closestUp('.section');
    var attrName = 'color';

    var limit = $section.data('limit');
    limit = limit ? limit : 99999;

    var value = $this.val();

    this.core.gtag.sendEvent('search', {
//        _category: 'engagement',
//        _action: 'search',
//        _label: 'search_term',
        _category: attrName + ' search',
        search_term: value
    });

    var params = {
        action: 'get-attr-suggestions',
        name: attrName,
        q: value,
        page: 1,
        per_page: limit
    };

    /**
     * @todo implement view response on backend and insert views instead of back- and front-end render logic duplicates...
     */
    this.core.makeRequestByRoute('default', params, 'get', this.core.getConfig('filterParams')).then(function (body) {
        var $container = $section.find('.scroll').empty();

        $(body).each(function () {
            var _class = [this.isWas ? 'active' : '', this.hex === 'multi' ? 'color-multi' : ''].join(' ').trim();
            var _style = 'trans' === this.hex ? 'background-color:transparent' : ('multi' === this.hex ? '' : ('background-color:#' + this.hex));

            $container.append(
                '<a href="' + this.uri + '" ' +
                'title="' + this.name + '"' +
                (_class ? (' class="' + _class + '"') : '') +
                (_style ? (' style="' + _style + '"') : '') +
                (this.isNoFollow ? ' rel="nofollow"' : '') +
                '></a>'
            );
        });

        $addon.attr('class', 'fa fa-search');
    });
};
snowgirlApp.prototype.onAttrAjaxSuggestionsSearchKeyUp = function (ev) {
    var $this = $(ev.target);
    var $addon = $this.closestUp('.input-group').find('.input-group-addon span');

    $addon.attr('class', 'fa fa-refresh fa-spin');

    var $section = $this.closestUp('.section');
    var attrName = $section.data('attr-name');

    var limit = $section.data('limit');
    limit = limit ? limit : 99999;

    var value = $this.val();

    this.core.gtag.sendEvent('search', {
//        _category: 'engagement',
//        _action: 'search',
//        _label: 'search_term',
        _category: attrName + ' search',
        search_term: value
    });

    var params = {
        action: 'get-attr-suggestions',
        name: attrName,
        q: value,
        page: 1,
        per_page: limit
    };

    /**
     * @todo implement view response on backend and insert views instead of back- and front-end render logic duplicates...
     */
    this.core.makeRequestByRoute('default', params, 'get', this.core.getConfig('filterParams')).then(function (body) {
        var $container = $section.find('.scroll').empty();

        $(body).each(function () {
            $container.append(
                '<a href="' + this.uri + '" ' +
                'class="' + ('nav-item' + (this.isWas ? ' active' : '')) + '"' + (this.isNoFollow ? ' rel="nofollow"' : '') + '>' +
                '<input type="checkbox"' + (this.isWas ? ' checked' : '') + '>' +
                ' ' + this.name + (this.count ? (' <span class="count">' + this.count + '</span>') : '') +
                '</a>'
            );
        });

        $addon.attr('class', 'fa fa-search');
    });
};
snowgirlApp.prototype.onAttrSuggestionsSearchKeyUp = function (ev) {
    var $this = $(ev.target);
    var $section = $this.closestUp('.section');
    var regexp = new RegExp($this.val(), 'i');

    $section.find('a').each(function () {
        var $this = $(this);

        if (!$this.text().match(regexp)) {
            $this.css({display: 'none'});
        } else {
            $this.css({display: 'block'});
        }
    })
};
snowgirlApp.prototype.onViewsOrderChange = function (ev) {
    ev.preventDefault();
    var $this = $(ev.target);

    var _this = this;

    this.core.gtag.sendEvent('catalog_order_change', {
        _category: 'catalog views form',
        _action: 'select',
        _label: $this.val(),
        _callback: function () {
            window.location.href = _this.getViewsFormAction();
        }
    });
};
snowgirlApp.prototype.onViewsPerPageClick = function (ev) {
    ev.preventDefault();
    var $this = $(ev.target);

    this.core.gtag.sendEvent('catalog_per_page_change', {
        _category: 'catalog views form',
        _action: 'click',
        _label: $this.text(),
        _callback: function () {
            window.location.href = $this.attr('href');
        }
    });
};
snowgirlApp.prototype.onMobileControlButtonsClick = function (ev, stop) {
    if (stop) {
        ev.preventDefault();
        return false;
    }

    var $this = $(ev.target).getButton();
    var $buttons = $this.closestUp(this.selectorMobileControl).find('.btn').not($this);

    $buttons.each(function (i, o) {
        var $o = $(o);

        if ($o.hasClass('active')) {
            $o.trigger('click', true);
        }
    });
};
snowgirlApp.prototype.onMobileViewsButtonClick = function () {
    var $target = $('.obj-toggle-sm-views');
    var $targetSelect = $target.find('[name=sort]');
    var selectedIndex = $targetSelect.find('option:selected').index();
    var $new = $('<div/>');

    $targetSelect.find('option').each($.proxy(function (i, o) {
        $(o).prop('selected', true);
        $new.append($('<a/>', {
            href: this.getViewsFormAction(),
            class: ['nav-item', i === selectedIndex ? 'active' : ''].join(' ')
        }).text(o.innerText));
    }, this));

    $targetSelect.find('option').eq(selectedIndex).prop('selected', true);
    $target.removeClass('obj-toggle-sm-views toggle-on').addClass('obj-toggle-sm-views2');
    $new.addClass('col-md-9 col-lg-10 right mobile-views obj-toggle-sm-views nav-sm ' + this.classMobileOnly + ' toggle-on');

    $new.insertAfter($target);
};
snowgirlApp.prototype.getCategoryId = function () {
    return this.core.getConfig('filterParams', {category_id: null}).category_id;
};
snowgirlApp.prototype.onMobileCategoriesButtonClick = function () {
    var $categories = $(this.selectorCategories);

    $categories.find('.section-body')
        .prepend($('<a/>', {
            class: [this.classMobileCategoriesShowAll, this.classMobileOnly, 'nav-item'].join(' '),
            text: 'Посмотреть все товары'
        }))
        .prepend($('<div/>', {class: [this.classMobileCategoriesBack, this.classMobileOnly, 'nav-item', 'nav-item-caret', 'nav-item-caret-left', 'active'].join(' ')}));

    var $tree = $(this.selectorCategoriesTree);

    $tree.find('li').each(function (index, li) {
        var $li = $(li);

        if ($li.children('ul').length) {
            $li.find('> .item-wrap > .item').addClass('nav-item-caret nav-item-caret-right');
        }
    });

    $tree.find('.count').addClass(this.classMobileHidden);

    var categoryId = this.getCategoryId();
    var $category = $categories.find('li[data-id=' + categoryId + ']');

    if (!$category.children('ul').length) {
        categoryId = $category.data('parent-id');
    }

    this.makeMobileCategoriesMenu(categoryId);
    this.initMobileCategoriesEventHandlers();
};
snowgirlApp.prototype.onMobileCategoriesBackClick = function (ev) {
    var categoryId = $(ev.target).data('id');
    var $categories = $(this.selectorCategories);
    var $category = $categories.find('li[data-id=' + categoryId + ']');
    var parentCategoryId = $category.data('parent-id');
    this.makeMobileCategoriesMenu(parentCategoryId, true);
};
snowgirlApp.prototype.onMobileCategoriesItemClick = function (ev) {
    var $this = $(ev.target).closestUp('.item');
    var $category = $this.closestUp('li');

    if ($category.children('ul').length) {
        ev.preventDefault();
        var categoryId = $category.data('id');
        this.makeMobileCategoriesMenu(categoryId, true);
    } else if (this.getCategoryId() == $category.data('id')) {
        window.location.reload();
    }
};
snowgirlApp.prototype.makeMobileCategoriesMenu = function (categoryId, scroll) {
    var $categories = $(this.selectorCategories);
    var $tree = $(this.selectorCategoriesTree);
    var $back = $(this.selectorMobileCategoriesBack);
    var $showAll = $(this.selectorMobileCategoriesShowAll);

    var className = this.classMobileHidden;

    //@todo optimize classes remove & add...

    $([])
        .add($back)
        .add($showAll)
        .add($tree.find('li.' + className))
        .add($tree.find('ul.' + className))
        .add($tree.find('.item.' + className))
        .removeClass(className);

    if (categoryId) {
        var $category = $categories.find('li[data-id=' + categoryId + ']');

        $back.text($category.find('> .item-wrap > .item').text())
            .data('id', categoryId);

        if (this.getCategoryId() == categoryId) {
            $showAll.addClass(className);
        } else {
            $showAll.attr('href', $category.find('> .item-wrap a').attr('href'));
        }

        $tree.find('ul,li').addClass(className);

        $category.children('ul').find('li').removeClass(className).end()
            .removeClass(className)
            .parentsUntil(this.selectorCategoriesTree).each(function (index, liOrUl) {
            var $liOrUl = $(liOrUl);
            $liOrUl.removeClass(className);
            $liOrUl.find('> .item-wrap > .item').addClass(className);
        });
    } else {
        $([])
            .add($back)
            .add($showAll)
            .add($tree.find('ul'))
            .addClass(className);
    }

    if (scroll) {
        window.scrollTo(0, 0);
    }
};
snowgirlApp.prototype.onMobileFiltersSectionClick = function (ev) {
    var $section = $(ev.target).closestUp('.section');

    $(this.selectorOtherFilters + ' .section').not($section)
        .find('.section-body').removeClass('toggle-mobile-visible').end()
        .find('.nav-item-caret').removeClass('nav-item-caret-top').addClass('nav-item-caret-bottom');

    var $caret = $section.find('.nav-item-caret');

    if ($caret.hasClass('nav-item-caret-bottom')) {
        $section.find('.section-body').addClass('toggle-mobile-visible');
        $caret.removeClass('nav-item-caret-bottom').addClass('nav-item-caret-top');
    } else {
        $section.find('.section-body').removeClass('toggle-mobile-visible');
        $caret.removeClass('nav-item-caret-top').addClass('nav-item-caret-bottom');
    }
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
                $(this.selectorAjaxLoader).remove();
            } else {
                var $window = $(window);
                var tmp = $window.scrollTop();
                $('.widget-grid-items .row').append(body.view);
//                $window.scrollTop($ajaxLoader.offset().top - window.innerHeight + $ajaxLoader.outerHeight() + 10);
                $window.scrollTop(tmp);

                if (('isLastPage' in body) && body['isLastPage']) {
                    $(this.selectorAjaxLoader).remove();
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

    this.core.gtag.sendEvent('catalog_load_more_items', {
        _category: 'load more button',
        _action: 'click',
        _label: 'catalog',
        params: params,
        page: request.page
    });
};
snowgirlApp.prototype.getViewsFormAction = function () {
    var $form = $(this.selectorViews + ' form');
    var action = $form.attr('action') || '';
    return action + (-1 === action.indexOf('?') ? '?' : '&') + decodeURI($form.serialize());
};

new snowgirlApp(snowgirlCore);