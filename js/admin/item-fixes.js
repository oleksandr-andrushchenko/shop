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
        .on('change', 'table table [data-name]', $.proxy(this.onItemColumnChange, this));

    $('.widget-FormTag').each($.proxy(function (index, form) {
        this.core.try($.proxy(function () {
            /** @type {FormInputTag} */
            var tagWidget = $(form).find('.widget-FormInputTag').data('snowgirl-FormInputTag');

            if (!tagWidget) {
                return false;
            }

            tagWidget.onItemAdded($.proxy(this.onTagAdded, this));
            tagWidget.onItemRemoved($.proxy(this.onTagRemoved, this));
            return true;
        }, this));
    }, this));
};
snowgirlApp.prototype.onItemColumnChange = function (ev) {
    var $input = $(ev.target);
    this.core.updateRow('item', $input.data('id'), $input.data('name'), $input.val())
        .then(function () {
            var $msg = $('<span/>', {class: 'message-ok', text: 'Обновлено'});
            $input.closestUp('td').append($msg);
            setTimeout(function () {
                $msg.remove();
            }, 3000);
        });
};
snowgirlApp.prototype.onTagAdded = function (ev) {
    var $target = $(ev.target);
    var itemId = $target.closestUp('tr[data-id]').data('id');
    var attrId = ev.item.id;
    var $tr = $target.closestUp('tr');
    var table = $tr.data('attr-table');
    var pk = $tr.data('attr-pk');

    var data = {};
    data['item_id'] = itemId;
    data[pk] = attrId;

    this.core.insertRow(table, data)
        .then(function (data) {
            console.log(data);
        });
};
snowgirlApp.prototype.onTagRemoved = function (ev) {
    var $target = $(ev.target);
    var itemId = $target.closestUp('tr[data-id]').data('id');
    var attrId = ev.item.id;
    var $tr = $target.closestUp('tr');
    var table = $tr.data('attr-table');

    this.core.deleteRow(table, [itemId, attrId].join('-'))
        .then(function (body) {
            console.log(body);
        });
};

new snowgirlApp(snowgirlCore);