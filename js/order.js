var snowgirlApp = function (snowgirlCore) {
    this.core = snowgirlCore;

//    this.initDOM();
    this.initArgs();
    this.initDOM();
    this.initCallbacks();
};
snowgirlApp.prototype.initArgs = function () {
    this.cartItemSelector = '.cart-items .item';
    this.visitedItemSelector = '.visited-items .item';
    this.formSelector = 'form.widget-form-order';
    this.formTextareaSelector = this.formSelector + ' textarea';
};
snowgirlApp.prototype.initDOM = function () {
    this.updateForm();
};
snowgirlApp.prototype.initCallbacks = function () {
    this.core.$document
        .on('click', '.btn-load-more', $.proxy(this.onAjaxLoaderClick, this));
};
snowgirlApp.prototype.updateForm = function () {
    var messages = [];

    messages.push('');

    $(this.cartItemSelector).each(function (i, item) {
        messages.push('');
    });

    messages = messages.join("\r\n");

    $(this.formTextareaSelector).val(messages);
};
snowgirlApp.prototype.onAjaxLoaderClick = function (ev) {
    var $btn = $(ev.target);
    var $charItems = $btn.parents('.char-items');
    var $items = $charItems.find('.items');
    var page = $charItems.data('page') ? $charItems.data('page') : 1;
    var perPage = this.core.getConfig('groupPerPageSize');

    this.core.makeRequestByRoute('default', {
        action: 'get-attr-suggestions',
        name: 'brand',
        q: $charItems.data('char'),
        prefix: 1,
        page: ++page,
        per_page: perPage
    })
        .then(function (body) {
            $charItems.data('page', page);
            $(body).each(function () {
                $items.append($('<div/>', {class: 'col-lg-4 col-md-4 col-sm-4 col-mb-6 col-xs-12 item'})
                    .append($('<a/>', {href: this.uri}).text(this.name)));
            });

            if (body.length < perPage) {
                $btn.remove();
            }
        });
};

new snowgirlApp(snowgirlCore);