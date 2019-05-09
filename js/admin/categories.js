var snowgirlApp = function (snowgirlCore) {
    this.core = snowgirlCore;

    this.initArgs();
    this.initDOM();
    this.initCallbacks();
};
snowgirlApp.prototype.initArgs = function () {
    this.$tree = $('.content').find('#category-tree');
};
snowgirlApp.prototype.initDOM = function () {
    this.$tree.nestable();
};
snowgirlApp.prototype.initCallbacks = function () {
    this.core.$document
        .on('change', '#category-tree', $.proxy(this.onCategoryParentChange, this))
        .on('click', '.btn-toggle-collapse-all', $.proxy(this.onCategoryTreeCollapseToggleClick, this))
        .on('click', '.btn-build-tree', $.proxy(this.onCategoryBuildTreeClick, this));
};
snowgirlApp.prototype.onCategoryParentChange = function (ev, data) {
    this.core.updateRow('category', data.nodeId, 'parent_category_id', data.targetNodeId);
};
snowgirlApp.prototype.onCategoryTreeCollapseToggleClick = function (ev) {
    var $btn = $(ev.target);
    var action = $btn.data('collapsed');

    if (action === 'yes') {
        this.$tree.nestable('expandAll');
        $btn.data('collapsed', 'no');
        $btn.empty().html('<span class="glyphicon glyphicon-chevron-up"></span> Свернуть');
    } else {
        this.$tree.nestable('collapseAll');
        $btn.data('collapsed', 'yes');
        $btn.empty().html('<span class="glyphicon glyphicon-chevron-down"></span> Развернуть');
    }
};
snowgirlApp.prototype.onCategoryBuildTreeClick = function (ev) {
    $(ev.target).getButton().toggleLoading();
};

new snowgirlApp(snowgirlCore);