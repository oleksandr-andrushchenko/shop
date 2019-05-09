var snowgirlApp = function (snowgirlCore) {
    this.core = snowgirlCore;

//    this.initArgs();
//    this.initDOM();
    this.initCallbacks();
};
snowgirlApp.prototype.initCallbacks = function () {
    this.core.$document
        .on('change', '.form-vendor [name]', $.proxy(this.onVendorActiveChange, this))
        .on('change', '.form-import-source [name]', $.proxy(this.onImportSourceCronChange, this))
        .on('click', '.import-sources .item .link', $.proxy(this.onImportSourceClick, this))
        .on('submit', '.import-sources form', $.proxy(this.onImportSourceSubmit, this));
};
snowgirlApp.prototype.onVendorActiveChange = function (ev) {
    var $this = $(ev.target);
    var $form = $this.parents('form');

    if (confirm('Также, будут обновлены предложения соответствующие этому поставщику, продолжить?')) {
        new this.core.getLoadingObject();
        $form.submit();
    }
};
snowgirlApp.prototype.onImportSourceCronChange = function (ev) {
    var $this = $(ev.target);
    var $form = $this.parents('form');

    if (confirm('В случае снятия флажка - предложения перестанут обновлятся, продолжить?')) {
        new this.core.getLoadingObject();
        $form.submit();
    }
};
snowgirlApp.prototype.onImportSourceClick = function () {
    new this.core.getLoadingObject('Скачивание и обработка файла');
};
snowgirlApp.prototype.onImportSourceSubmit = function () {
    new this.core.getLoadingObject('Сохранение, скачивание и обработка файла');
};

new snowgirlApp(snowgirlCore);