var snowgirlApp = function (snowgirlCore) {
    this.core = snowgirlCore;

    this.initArgs();
    this.initDOM();
    this.initCallbacks();
};
snowgirlApp.prototype.initArgs = function () {
    this.$page = $('.content');
};
snowgirlApp.prototype.initDOM = function () {
    this.$page.find('.easyzoom').easyZoom();
};
snowgirlApp.prototype.initCallbacks = function () {
    this.core.$document
        .on('change', '.form-search [name]', $.proxy(this.onSearchFormInputChange, this))
        .on('change', 'table table [data-name]', $.proxy(this.onItemColumnChange, this))
        .on('click', '.btn-transfer', $.proxy(this.onTransferCategoryClick, this));

    $('.transfer .widget-FormTag').each($.proxy(function (index, form) {
        this.core.try($.proxy(function () {
            /** @type {FormInputTag} */
            var tagWidget = this.core.findTagWidget(form);

            if (!tagWidget) {
                return false;
            }

            tagWidget.onItemAdded($.proxy(this.onTransferCategoryTagAdded, this));
            tagWidget.onItemRemoved($.proxy(this.onTransferCategoryTagRemoved, this));
            return true;
        }, this));
    }, this));
};
snowgirlApp.prototype.onTransferCategoryTagAdded = function (ev) {
    var $target = $(ev.target);
    var name = $target.attr('name');

    if ('category_id' == name) {
        var $td = $target.closestUp('td');
        $td.find('.btn-transfer').prop('disabled', false).removeClass('disabled');
    }
};
snowgirlApp.prototype.onTransferCategoryTagRemoved = function (ev) {
    var $target = $(ev.target);
    var name = $target.attr('name');

    if ('category_id' == name) {
        var $td = $target.closestUp('td');
        $td.find('.btn-transfer').prop('disabled', true).addClass('disabled');
    }
};
snowgirlApp.prototype.onTransferCategoryClick = function (ev) {
    var $btn = $(ev.target);
    var $td = $btn.closestUp('td');
    var categoryId = $td.closestUp('tr[data-id]').data('id');
    var targetCategoryId = this.core.findTagWidget($td, 'category_id').getItems()[0]['id'];
    var targetTagId = $.map(this.core.findTagWidget($td, 'tag_id').getItems(), function (tag) {
        return tag['id'];
    });

    var data = {source: {category_id: categoryId}, target: {category_id: targetCategoryId, tag_id: targetTagId}};

    this.core.makeRequestByRoute('admin', {action: 'transfer-items-by-attrs'}, 'post', data)
        .then($.proxy(function (body, status) {
            console.log(body, status);

            //@todo if ok - delete category
            if (1 == 2)
                this.core.deleteRow('category', categoryId)
                    .then(function () {
                        location.reload();
                    });
        }, this));
};
snowgirlApp.prototype.search = function () {
    $('.form-search').submit();
};
snowgirlApp.prototype.onSearchFormInputChange = function (ev) {
    var $input = $(ev.target);
    var option = $input.attr('name');

    if (-1 !== ['search_leafs', 'search_entities', 'search_non_active_entities'].indexOf(option)) {
        this.search();
    }

    var isSearchValue = $('[name=search_value]').val().length > 0;

    if (isSearchValue && ('search_by' == option)) {
        this.search();
    }

    if (isSearchValue && ('search_use_fulltext' == option)) {
        this.search();
    }
};
snowgirlApp.prototype.onItemColumnChange = function (ev) {
    var $input = $(ev.target);
    this.core.updateRow($input.data('table') ? $input.data('table') : 'category', $input.data('id'), $input.data('name'), $input.val())
        .then(function () {
            var $msg = $('<span/>', {class: 'message-ok', text: 'Обновлено'});
            $input.closestUp('td').append($msg);
            setTimeout(function () {
                $msg.remove();
            }, 3000);
        });
};

new snowgirlApp(snowgirlCore);