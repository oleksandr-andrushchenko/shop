var snowgirlAdminShop = function (snowgirlCore) {
    this.core = snowgirlCore;

    return this.export();
};

snowgirlAdminShop.prototype.export = function () {
    return $.extend({},this.core, {
        //export
    });
};

snowgirlCore = new snowgirlAdminShop(snowgirlCore);