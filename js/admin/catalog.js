var snowgirlApp = function (snowgirlCore) {
    this.core = snowgirlCore;

    this.initArgs();
    this.initCallbacks();
};
snowgirlApp.prototype.initArgs = function () {
    this.$page = $('.content');
};
snowgirlApp.prototype.initCallbacks = function () {
    this.core.$document
        .on('click', '.btn-add-seo-text', $.proxy(this.onAddSeoTextButtonClick, this))
        .on('click', '.btn-edit-seo-text', $.proxy(this.onEditSeoTextButtonClick, this))
        .on('click', '.btn-delete-seo-text', $.proxy(this.onDeleteSeoTextButtonClick, this))
        .on('click', '.btn-active-seo-text', $.proxy(this.onActiveSeoTextClick, this))
        .on('click', '.btn-modify-attr', $.proxy(this.onModifyAttrClick, this));
};
snowgirlApp.prototype.onAddSeoTextButtonClick = function (ev) {
    var $btn = $(ev.target).closestUp('.btn');
    var $control = $btn.closestUp('.seo-text-control');
    var $form = $control.find('.seo-text-form');
    $control.find('.btn').not($btn).removeClass('active');
    $control.find('.seo-text-form').not($form).hide();
    $btn.toggleClass('active');

    if ($form.length) {
        $form.toggle();
    } else {
        $form = this.$page.find('.seo-text-form.template').clone()
            .removeClass('template')
            .attr('action', this.core.getUriByRoute('admin', {action: 'page-catalog-custom-add-seo-text'}))
            .find('[name=id]').val($control.parents('tr').data('id')).end()
            .show();
        $form.find('[name=num]').remove();
        $control.append($form);
        this.prepareContentForm($form);
    }
};
snowgirlApp.prototype.onEditSeoTextButtonClick = function (ev) {
    var $btn = $(ev.target).closestUp('.btn');
    var $item = $btn.closestUp('.seo-text');
    var $form = $item.find('.seo-text-form');
    $btn.toggleClass('active');

    if ($form.length) {
        $form.toggle();
    } else {
        $form = this.$page.find('.seo-text-form.template').clone()
            .removeClass('template')
            .attr('action', this.core.getUriByRoute('admin', {action: 'page-catalog-custom-update-seo-text'}))
            .find('[name=id]').val($item.parents('tr').data('id')).end()
            .find('[name=num]').val($item.data('num')).end()
            .find('[name=h1]').val($item.find('.h1').text()).end()
            .find('[name=body]').val(this.decodeHtml($item.find('.body').html())).end()
            .find('[name=active]').prop('checked', 1 === $item.data('active')).end()
            .show();
        $item.append($form);
        this.prepareContentForm($form);
    }
};
snowgirlApp.prototype.onDeleteSeoTextButtonClick = function (ev) {
    if (confirm('Вы уверены?')) {
        var $btn = $(ev.target).closestUp('.btn');
        var $item = $btn.closestUp('.seo-text');

        this.core.makeRequestByRoute('admin', {
            action: 'page-catalog-custom-delete-seo-text',
            id: $item.parents('tr').data('id'),
            num: $item.data('num')
        }, 'delete')
            .then(function () {
                window.location.reload();
            });
    }
};
snowgirlApp.prototype.onActiveSeoTextClick = function (ev) {
    var $btn = $(ev.target).closestUp('.btn');
    var $item = $btn.closestUp('.seo-text');
    var pageCatalogId = $item.parents('tr').data('id');
    var params = {
        action: 'page-catalog-custom-toggle-seo-text-activation',
        id: pageCatalogId,
        num: $item.data('num')
    };

    this.core.makeRequestByRoute('admin', params, 'post')
        .then(function (body) {
            $item.data('active', body['active']);

            if (true === body['active']) {
                $btn.removeClass('btn-default').addClass('btn-success');
            } else {
                $btn.removeClass('btn-success').addClass('btn-default');
            }
        });
};
snowgirlApp.prototype.onModifyAttrClick = function (ev) {
    var $btn = $(ev.target).closestUp('.btn');
    var $tr = $btn.parents('tr');
    var pageCatalogCustomId = $tr.attr('data-custom-id');

    var name = $.trim($btn.data('name'));
    var value = $.trim($btn.data('value'));

    var $input = $('<input/>', {
        type: 'text',
        class: 'form-control',
        name: name,
        value: value,
        placeholder: name
    });

    var $btnCopy = $btn.clone();

    $btn.replaceWith($input);

    $input.focus();

    $input.on('blur', $.proxy(function () {
        var newValue = $.trim($input.val());

        if (newValue !== value) {
            var callback = function () {
                $input.replaceWith($btnCopy);

                if (newValue) {
                    $btnCopy.removeClass('btn-default').addClass('btn-success')
                        .attr('title', newValue).data('value', newValue);
                } else {
                    $btnCopy.removeClass('btn-success').addClass('btn-default')
                        .attr('title', 'Добавить атрибут').data('value', '');
                }
            };

            if (pageCatalogCustomId) {
                this.core.updateRow('page_catalog_custom', pageCatalogCustomId, name, newValue)
                    .then(callback);
            } else {
                var row = {
                    params_hash: $tr.data('params-hash')
                };

                row[name] = newValue;

                this.core.insertRow('page_catalog_custom', row)
                    .then(function (body) {
                        $tr.attr('data-custom-id', body['id']);
                        pageCatalogCustomId = body['id'];
                        callback();
                    });
            }
        } else {
            $input.replaceWith($btnCopy);
        }
    }, this));
};

snowgirlApp.prototype.tinymceOptions = function ($form) {
    return {
        selector: '#' + $form.find('[name=body]').attr('id'),
        language: 'ru',
        language_url: '/js/core/tinymce/langs/ru.js',
        content_css: [
            '//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css',
            'https://fonts.googleapis.com/css?family=Montserrat',
//             '/css/core/core.fonts.css',
//             '/css/shop/catalog.css',
            '/css/core/tinymce.css'
        ],
        invalid_elements: 'script',
        relative_urls: false,
        remove_script_host: false,
        document_base_url: this.core.getConfig('domains')['master'],
        browser_spellcheck: true,
        forced_root_block: false,
        force_br_newlines: true,
        force_p_newlines: false,
        plugins: ['autoresize', 'lists', 'link', 'autolink', 'anchor', 'charmap', 'preview', 'searchreplace', 'code', 'fullscreen', 'wordcount'].join(' '),
        toolbar: ['undo', 'redo', 'customHeading', 'customParagraph', 'bullist', 'link', 'searchreplace', 'removeformat', 'code', 'spellchecker', 'fullscreen', 'preview'].join(' '),
        menubar: false,
        toolbar_items_size: 'small',
        autoresize_bottom_margin: 15,
        contextmenu_never_use_native: true,
        automatic_uploads: true,
        image_title: true,
        image_caption: true,
        image_prepend_url: "/img/",
        images_upload_url: this.core.getUriByRoute('image'),
        images_upload_base_path: false,
        images_upload_credentials: true,
        setup: function (editor) {
            editor.ui.registry.addToggleButton('customHeading', {
                text: 'Заголовок',
                onAction: function (_) {
                    editor.execCommand('mceToggleFormat', false, 'h3');
                },
                onSetup: function (api) {
                    editor.formatter.formatChanged('h3', function (state) {
                        api.setActive(state);
                    });
                }
            });
            editor.ui.registry.addToggleButton('customParagraph', {
                text: 'Параграф',
                onAction: function (_) {
                    editor.execCommand('mceToggleFormat', false, 'p');
                },
                onSetup: function (api) {
                    editor.formatter.formatChanged('p', function (state) {
                        api.setActive(state);
                    });
                }
            });
            editor.on('init keyup', function () {
                $form.find('#body-length').val(editor.getContent({format: 'text'}).replace(/\s/g, "").length);
            });
            editor.on('change', function () {
                editor.save();
            });
        }
    };
};
snowgirlApp.prototype.validatorOptions = function ($form) {
    var $body = $form.find('[name=body]');
    var bodyId = $body.attr('id');
    var bodyMinLength = parseInt($body.attr('minlength'));
    return {
        excluded: [':disabled'],
        feedbackIcons: {
            valid: 'glyphicon glyphicon-ok',
            invalid: 'glyphicon glyphicon-remove',
            validating: 'glyphicon glyphicon-refresh'
        },
        fields: {
            title: {
                validators: {
                    notEmpty: {
                        message: 'Название статьи не может быть пустым. Пожалуйста укажите название'
                    }
                }
            },
            body: {
                validators: {
                    callback: {
                        message: 'Длинна статьи должна быть больше ' + bodyMinLength + ' символов. Пожалуйста добавьте текста',
                        callback: function () {
                            return tinymce.get(bodyId).getContent({format: 'text'}).length >= bodyMinLength;
                        }
                    }
                }
            }
        }
    };
};
snowgirlApp.prototype.prepareContentForm = function ($form) {
    this.core.getScriptLoader().get([
        '/js/core/tinymce/tinymce.min.js',
        '/js/core/bootstrap-validator.min.js'
    ], $.proxy(function () {
        $form.find('textarea').attr('id', (new Date).getTime());

        tinymce.init(this.tinymceOptions($form));
        $form.bootstrapValidator(this.validatorOptions($form));
    }, this));
};
snowgirlApp.prototype.decodeHtml = function (text) {
    return $('<textarea/>').html(text).text();
};
snowgirlApp.prototype.getFormData = function ($form) {
    var tmp = $form.serializeArray();
    var data = {};
    for (var i = 0, s = tmp.length; i < s; i++) {
        data[tmp[i]['name']] = tmp[i]['value'];
    }
    return data;
};

new snowgirlApp(snowgirlCore);