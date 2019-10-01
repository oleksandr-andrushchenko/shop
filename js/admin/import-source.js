var snowgirlApp = function (snowgirlCore) {
    this.core = snowgirlCore;

    this.initArgs();
    this.initDOM();
    this.initCallbacks();
};
snowgirlApp.prototype.initArgs = function () {
    this.$page = $('.content');
    this.importSourceId = this.core.getConfig('importSourceId');
    this.svaValues = this.core.getConfig('svaValues');
    this.isShowModifiersItems = this.core.getConfig('isShowModifiersItems');
    this.tagsWidgetsLoaded = 0;
};
snowgirlApp.prototype.initDOM = function () {
    var imageDataKey = $('[name="map[image][column]"]').val();

    if (imageDataKey.length) {
        var $dataTable = $('#data').find('table');
        var imageDataKeyIndex = $dataTable.find('th:contains(' + imageDataKey + ')').index();

        $dataTable.find('tr').each(function () {
            var $this = $(this).find('td').eq(imageDataKeyIndex);
            var $images = $('<div/>', {class: 'easyzoom easyzoom--adjacent'});
            $this.empty().append($images);

            var images = $this.attr('title');

            if (images) {
                $(images.split(',')).each(function (i, image) {
                    $images.append($('<a/>', {href: image, style: 'display:block;height:100px'})
                        .append($('<img/>', {src: image, style: 'height:100%'})));
                });
            }
        });

        $dataTable.find('.easyzoom').easyZoom();
    }

    $('.map-item-modify').each($.proxy(function (i, el) {
        this.addEasyZoomToMappingModifyFromItems($(el));
    }, this));
};
snowgirlApp.prototype.initCallbacks = function () {
    this.core.$document
        .on('click', '.btn-refresh', $.proxy(this.onRefreshButtonClick, this))
        .on('click', '.btn-copy', $.proxy(this.onCopyButtonClick, this))
        .on('click', '.btn-delete', $.proxy(this.onDeleteButtonClick, this))
        .on('click', '.btn-delete-items', $.proxy(this.onDeleteItemsButtonClick, this))
        .on('input', '.filter-item input', $.proxy(this.onItemsFilterInputKeyUp, this))
        .on('change', '.map-item-column', $.proxy(this.onMapItemColumnChange, this))
        .on('click', '.btn-add-map-item-modify', $.proxy(this.onAddMapItemModifyClick, this))
        .on('click', '.btn-delete-map-item-modify', $.proxy(this.onDeleteMapItemModifyClick, this))
        .on('click', '.btn-load-all-possible-modify', $.proxy(this.onLoadMapItemModifyClick, this))
        .on('change', '#data [name=page]', $.proxy(this.onDataFilePageChange, this))
        .on('submit', '.form-import', $.proxy(this.onImportFormSubmit, this))
        .on('click', '.btn-delete-duplicate-items', $.proxy(this.onDeleteDuplicateItemsClick, this))
        .on('change', '.map-item-modify .raw-sport', $.proxy(this.onMapItemModifyRawSportChange, this))
        .on('change', '.map-item-modify .raw-size-plus', $.proxy(this.onMapItemModifyRawSizePlusChange, this))
        .on('focus', '.map-item-modify .raw-tags', $.proxy(this.onMapItemModifyRawTagsFocus, this))
        .on('click', '.btn-auto-map-item-modify', $.proxy(this.onAutoMapItemModifyClick, this))
        .on('click', '.btn-clear-empty-map-item-modify', $.proxy(this.onClearEmptyMapItemModifyClick, this))
        //@todo make map-item checks...
        .on('submit', '.form-map', $.proxy(this.onMapFormSubmit, this))
        .on('keyup', 'textarea', $.proxy(this.onTextareaKeyup, this))
    ;

    $('textarea').trigger('keyup');
};
snowgirlApp.prototype.onMapItemModifyRawSportChange = function (ev) {
    var $input = $(ev.target);
    var $mapItemModify = $input.parents('.map-item-modify');
    var from = $mapItemModify.find('.from input').val();

    if (!from.length) {
        alert('Сперва нужно заполнить значение поля "Что"');
        return false;
    }

    $input.removeClass('raw-sport');

    var $mapItem = $mapItemModify.parents('.map-item');
    var column = $mapItem.data('column');
    var name = 'map[' + column + '][is_sport][' + from + ']';

    $input.attr('name', name);

    return true;
};
snowgirlApp.prototype.onMapItemModifyRawSizePlusChange = function (ev) {
    var $input = $(ev.target);
    var $mapItemModify = $input.parents('.map-item-modify');
    var from = $mapItemModify.find('.from input').val();

    if (!from.length) {
        alert('Сперва нужно заполнить значение поля "Что"');
        return false;
    }

    $input.removeClass('raw-size-plus');

    var $mapItem = $mapItemModify.parents('.map-item');
    var column = $mapItem.data('column');
    var name = 'map[' + column + '][is_size_plus][' + from + ']';

    $input.attr('name', name);

    return true;
};
snowgirlApp.prototype.onMapItemModifyRawTagsFocus = function (ev) {
    var $input = $(ev.target);
    var $mapItemModify = $input.parents('.map-item-modify');
    var from = $mapItemModify.find('.from input').val();

    if (!from.length) {
        alert('Сперва нужно заполнить значение поля "Что"');
        $input.blur();
        return false;
    }

    $input.prop('disabled', true);

    var $mapItem = $mapItemModify.parents('.map-item');
    var column = $mapItem.data('column');
    var name = 'map[' + column + '][tags][' + from + ']';

    var request = {
        action: 'tag-picker',
        table: 'tag',
        name: name,
        multiple: 1
    };

    var data = {
        params: {
            placeholder: 'tags'
        }
    };

    this.core.makeRequestByRoute('admin', request, 'post', data, 'html')
        .then($.proxy(function (body) {
            this.tagsWidgetsLoaded++;
            var nodes = $.parseHTML(body, document, true);
            var id = nodes[0].id;
            var newId = id + '-' + this.tagsWidgetsLoaded;
            nodes[0].id = newId;

            $.each(nodes, function (i, el) {
                if ('SCRIPT' == el.nodeName) {
                    el.style.display = 'none';
                    el.innerHTML = el.innerHTML.replace(id, newId);
                }
            });

//            console.log('Nodes:');
//            console.log(nodes, $(nodes).attr('id'));

            $input.replaceWith(nodes);
            this.core.try(function () {
                var tagWidget = $mapItemModify.find('.widget-tag').data('snowgirl-tag');

                if (tagWidget) {
                    tagWidget.focus();
                    return true;
                }
            });
        }, this));
};
snowgirlApp.prototype.onAutoMapItemModifyClick = function (ev) {
    var $btn = $(ev.target).getButton();
    $btn.toggleLoading();

    var objectToLower = function (obj) {
        var output = {};

        for (var k in obj) {
            if (obj.hasOwnProperty(k)) {
                output[k] = obj[k].toLowerCase();
            }
        }

        return output;
    };

    var $mapItem = $btn.closestUp('.map-item');
    var dbColumn = $mapItem.data('column');
    var possibleToes = this.svaValues[dbColumn];
    var possibleToesLower = objectToLower(possibleToes);
//    console.log('Possible Toes:');
//    console.log(possibleToes);
    var possibleToesLength = Object.keys(possibleToes).length;
    var possibleTags = this.core.getConfig('mappingModifyTags');
    var possibleTagsLower = objectToLower(possibleTags);
//    console.log('Possible Tags:');
//    console.log(possibleTags);
    var possibleTagsLength = Object.keys(possibleTags).length;

    $mapItem.find('.map-item-modify').each($.proxy(function (i, mapItemModify) {
        var changed = [];

        var $mapItemModify = $(mapItemModify);
        var from = $mapItemModify.find('input[name*=modify_from]').val();

        if (from.length) {
            from = from.toLowerCase();

            //value..
            if (possibleToesLength) {
                var $to = $mapItemModify.find('select[name*=modify_to]');

                if (!$to.val()) {
                    var isNew;

                    var toCurIndex;
                    var toCurEndsWith;
                    var toCurLength;

                    var toIndex = 0;
                    var toEndsWith = false;
                    var toLength = 0;
                    var to;

                    for (var possibleTo in possibleToesLower) {
                        if (possibleToesLower.hasOwnProperty(possibleTo)) {
                            toCurIndex = from.lastIndexOf(possibleToesLower[possibleTo]);

                            var isCheck = -1 != toCurIndex;

                            //@todo fix Полусапоги, Полупаьлто, Платья-футболки,
                            //@todo .../Брюки/Бриджи и капри, ..Одежда/Толстовки и олимпийки, .../Брюки/Леггинсы и тайтсы
                            //@todo ..Одежда/Верхняя одежда, ../Платья-толстовки, ..Обувь/Мокасины и топсайдеры
                            //@todo ..Туники/Туники-футболки, ..Верхняя одежда/Пуховики и зимние куртки, ..Юбки/Юбки-шорты
                            //@todo ..Туфли/Лодочки, ..Повседневные платья/Бандо-платья, ..Аксессуары/Кошельки и косметички
                            //@todo ..Аксессуары/Платки и шарфы, ..Туники/Туники-топы, ..Сапоги/Угги и унты
                            //@todo ..Очки/Солнцезащитные очки/Авиаторы и пилоты, ../Пиджаки и костюмы, ..Пиджаки и костюмы/Жакеты и пиджаки

                            if (isCheck) {
                                if ((toCurIndex > 1) && ('-' == possibleToesLower[possibleTo].slice(toCurIndex - 1, toCurIndex))) {
                                    isCheck = false;
                                }
                            }

                            if (isCheck) {
                                isNew = false;

                                toCurEndsWith = from.endsWith(possibleToesLower[possibleTo]);
                                toCurLength = possibleToesLower[possibleTo].length;

                                if (toCurEndsWith) {
                                    if (toEndsWith) {
                                        if (toCurLength > toLength) {
                                            isNew = true;
                                        }
                                    } else {
                                        isNew = true;
                                    }
                                } else {
                                    if (!toEndsWith) {
                                        if (toCurIndex > toIndex) {
                                            isNew = true;
                                        }
                                    }
                                }

                                if (isNew) {
                                    toIndex = toCurIndex;
                                    toEndsWith = toCurEndsWith;
                                    toLength = toCurLength;
                                    to = possibleTo;
                                }
                            }
                        }
                    }

                    if (to) {
                        $to.val(to);
                        changed.push('to');

                        console.log('From: ' + from);
                        console.log('To Name: ' + possibleToes[to]);
                        console.log('To: ' + to);
                        console.log('');
                    }
                }
            }

            //is sport..
            var $sport = $mapItemModify.find('input[name*=is_sport]');

            if (!$sport.is(':checked')) {
                if (-1 != from.indexOf('спорт')) {
                    $sport.prop('checked', true);
                    changed.push('sport');

//                    console.log('From: ' + from);
//                    console.log('Sport: checked');
//                    console.log('');
                }
            }

            //is size plus...
            var $size = $mapItemModify.find('input[name*=is_size_plus]');

            if (!$size.is(':checked')) {
                if ((-1 != from.indexOf('больш')) || (-1 != from.indexOf('размер'))) {
                    $size.prop('checked', true);
                    changed.push('size');

//                    console.log('From: ' + from);
//                    console.log('Size: checked');
//                    console.log('');
                }
            }

            //tags..
            if (possibleTagsLength) {
                var $tagsWidget = $mapItemModify.find('.widget-tag');

                if (!$tagsWidget.length) {
                    var tags = [];

                    for (var possibleTag in possibleTagsLower) {
                        if (possibleTagsLower.hasOwnProperty(possibleTag)) {
                            if (-1 != from.indexOf(possibleTagsLower[possibleTag])) {
                                tags.push(possibleTag);
                            }
                        }
                    }

                    if (tags.length) {
                        $mapItemModify.find('.raw-tags').trigger('focus');

                        this.core.try(function () {
                            var $tagWidget = $mapItemModify.find('.widget-tag');
                            var tagWidget = $tagWidget.data('snowgirl-tag');

                            if (tagWidget) {
                                for (var i = 0, s = tags.length; i < s; i++) {
//                                    console.log('Tag to be added: ' + tags[i] + ' ' + possibleTags[tags[i]]);
                                    $tagWidget.tag('addValue', tags[i], possibleTags[tags[i]]);
                                }

                                return true;
                            }
                        });

                        changed.push('tags');

//                        console.log('From: ' + from);
//                        console.log('Tags Names: ' + $.map(tags, function (tag) {
//                                return possibleTags[tag];
//                            }).join(', '));
//                        console.log('Tags: ' + tags.join(', '));
//                        console.log('');
                    }
                }
            }

            if (changed.length) {
                $mapItemModify.append($('<span/>').append($.map(changed, function (type) {
                    return '<span class="auto-changed">' + type + '</span>';
                }).join(' ')));
            }
        }
    }, this));

    $btn.toggleLoading();
};
snowgirlApp.prototype.onClearEmptyMapItemModifyClick = function (ev) {
    var $btn = $(ev.target).getButton();
    $btn.toggleLoading();

    var $mapItem = $btn.closestUp('.map-item');

    var del = function ($mapItemModify) {
        $mapItemModify.find('.btn-delete-map-item-modify').trigger('click');
        return true;
    };

    $mapItem.find('.map-item-modify').each(function (i, mapItemModify) {
        var $mapItemModify = $(mapItemModify);

        if (!parseInt($mapItemModify.find('.from-total').text())) {
            return del($mapItemModify);
        }
//
        if (!$mapItemModify.find('input[name*=modify_from]').val()) {
            return del($mapItemModify);
        }

        if (!$mapItemModify.find('select[name*=modify_to]').val()) {
            return del($mapItemModify);
        }

        return true;
    });

    $btn.toggleLoading();
};
snowgirlApp.prototype.onRefreshButtonClick = function (ev) {
    $(ev.target).getButton().toggleLoading();
    new this.core.getLoadingObject('Загружаю файл и обновляю данные');
};
snowgirlApp.prototype.onCopyButtonClick = function (ev) {
    var $btn = $(ev.target).getButton().toggleLoading();
    this.core.makeRequestByRoute('admin', {action: 'import-source-copy', id: this.importSourceId}, 'post')
        .then($.proxy(function (body) {
            $btn.toggleLoading();
            if (confirm('Сделано! Перейти к источнику?')) {
                window.location.href = this.core.getUriByRoute('admin', {
                    action: 'import-source',
                    id: body['id']
                });
            }
        }, this));
};
snowgirlApp.prototype.onDeleteButtonClick = function (ev) {
    var $btn = $(ev.target).getButton().toggleLoading();
    this.core.makeRequestByRoute('admin', {action: 'import-source-delete', id: this.importSourceId}, 'post')
        .then($.proxy(function (body) {
            $btn.toggleLoading();
            if (body.hasOwnProperty('count')) {
                alert('От этого поставщика ' + body['count'] + ' товаров! Обратитесь к админу!');
            } else {
                window.location.href = this.core.getUriByRoute('admin', 'offers');
            }
        }, this));
};
snowgirlApp.prototype.onDeleteItemsButtonClick = function (ev) {
    var $btn = $(ev.target).getButton().toggleLoading();
    this.core.makeRequestByRoute('admin', {action: 'import-source-delete-items', id: this.importSourceId})
        .then($.proxy(function (body) {
            $btn.toggleLoading();
            if (body.hasOwnProperty('count')) {
                if (confirm('От этого поставщика ' + body['count'] + ' товаров! Продолжить?')) {
                    var loading = new this.core.getLoadingObject('Удаляю');
                    this.core.makeRequestByRoute('admin', {
                        action: 'import-source-delete-items',
                        id: this.importSourceId,
                        confirmed: 1
                    }, 'get', {}, function () {
                        loading.remove();
                        location.reload();
                    });
                }
            } else {
//                console.log('ds');
                window.location.reload();
            }
        }, this));
};
snowgirlApp.prototype.onItemsFilterInputKeyUp = function (ev) {
    var $input = $(ev.target);
    var $item = $input.closestUp('.filter-item');
    var $otherInput = $item.find('input').not($input);

    if ($input.val().length || $otherInput.val().length) {
        $item.addClass('selected');
    } else {
        $item.removeClass('selected');
    }
};
snowgirlApp.prototype.onMapItemColumnChange = function (ev) {
    var $column = $(ev.target);
    var $mapItem = $column.closestUp('.map-item');

    if ('' === $column.val()) {
        $mapItem.removeClass('column');
    } else {
        $mapItem.addClass('column');
    }
};
snowgirlApp.prototype.onAddMapItemModifyClick = function (ev) {
    var $btn = $(ev.target).getButton();
    var $mapItem = $btn.closestUp('.map-item');
    this.addMapItemModify($mapItem);
};
snowgirlApp.prototype.onDeleteMapItemModifyClick = function (ev) {
    var $btn = $(ev.target);
    var $mapItem = $btn.parents('.map-item');
    var $modify = $btn.closestUp('.map-item-modify');
    $modify.remove();

    if (!$mapItem.find('.map-item-modify').length) {
        $mapItem.removeClass('modify');
    }
};
snowgirlApp.prototype.onLoadMapItemModifyClick = function (ev) {
    var $btn = $(ev.target).getButton();
    var $mapItem = $btn.closestUp('.map-item');
    var dbColumn = $mapItem.data('column');
    var fileColumn = $mapItem.find("[name='map[" + dbColumn + "][column]']").val();
    var notLessThan = $mapItem.find("[name='modify-not-less-than']").val();
    $btn.toggleLoading();

    this.core.makeRequestByRoute('admin', {
        action: 'import-source-get-map-from-possible-values',
        id: this.importSourceId,
        column: fileColumn,
        not_less_than: notLessThan,
        is_items: this.isShowModifiersItems
    })
        .then($.proxy(function (body) {
            $btn.toggleLoading();
            for (var v in body) {
                if (body.hasOwnProperty(v)) {
                    this.addMapItemModify($mapItem, v, null, body[v]);
                }
            }
        }, this));
};
snowgirlApp.prototype.onDataFilePageChange = function (ev) {
    var $select = $(ev.target);
    location.href = this.core.getUriByRoute('admin', {
        action: 'import-source',
        id: this.importSourceId,
        data: 1,
        page: $select.val()
    }) + '#data';
};
snowgirlApp.prototype.onImportFormSubmit = function (ev) {
    var $form = $(ev.target).closestUp('form');
    var $btn = $form.find('.btn');
    $btn.toggleLoading();
    new this.core.getLoadingObject('Выполняю импорт');
};
snowgirlApp.prototype.onMapFormSubmit = function (ev) {
    var $form = $(ev.target).closestUp('form');

    //@todo make map-item checks...

    console.log('@todo make map-item checks...');
    return true;
};
snowgirlApp.prototype.onTextareaKeyup = function (ev) {
    var el = $(ev.target)[0];
    el.style.height = "0px";
    el.style.height = (el.scrollHeight + 2) + "px";
};
snowgirlApp.prototype.onDeleteDuplicateItemsClick = function (ev) {
    $(ev.target).getButton().toggleLoading();
    this.core.makeRequestByRoute('admin', {action: 'import-source-delete-duplicate-items', id: this.importSourceId})
        .then(function (body) {
//            console.log(body);
            try {
                alert(body.text);
            } catch (e) {
            }
        });
};
snowgirlApp.prototype.addEasyZoomToMappingModifyFromItems = function ($modify) {
    $modify.find('img').each(function () {
        var $img = $(this);
        var src = $img.attr('src');

        if (0 === src.indexOf('//')) {
            src = 'http:' + src;
        }

//         console.log(src);

        var $new = $('<div/>', {class: 'easyzoom easyzoom--adjacent', title: $img.attr('title')})
            .append($('<a/>', {href: src})
                .append($('<img/>', {src: src})));

        $img.replaceWith($new);
        $new.easyZoom();
    });
};
/**
 * @todo add tags...
 *
 * @param $mapItem
 * @param fromValue
 * @param toValue
 * @param info
 * @returns {boolean}
 */
snowgirlApp.prototype.addMapItemModify = function ($mapItem, fromValue, toValue, info) {
    info = info || {};

    var dbColumn = $mapItem.data('column');
    var $from, $to;

    if (fromValue && $mapItem.find("[name='map[" + dbColumn + "][modify_from][]'][value='" + fromValue + "']").length) {
        return true;
    }

    $from = $('<input/>', {
        class: 'form-control',
        type: 'text',
        name: 'map[' + dbColumn + '][modify_from][]',
        placeholder: 'Что',
        value: fromValue || ''
    });

    if (fromValue) {
        $from.attr({
            title: fromValue,
            readonly: 'readonly',
            'aria-readonly': 'true'
        });
    } else {
        $from.blur(function () {
            if ($from.val().length) {
                $from.off('blur');
                $from.attr({
                    readonly: 'readonly',
                    'aria-readonly': 'true'
                });
            }
        });
    }

    var fromAttributes = {
        class: 'form-control',
        name: 'map[' + dbColumn + '][modify_to][]'
    };

    if (this.svaValues.hasOwnProperty(dbColumn)) {
        $to = $('<select/>', fromAttributes).append($('<option/>', {
            value: '',
            text: '-- Нет значения --'
        }));
        for (var value in this.svaValues[dbColumn]) {
            if (this.svaValues[dbColumn].hasOwnProperty(value)) {
                $to.append($('<option/>', {
                    value: value,
                    text: this.svaValues[dbColumn][value],
                    selected: toValue && value === toValue
                }));
            }
        }
    } else {
        $to = $('<input/>', $.extend(fromAttributes, {
            type: 'text',
            placeholder: 'Во что',
            value: toValue || ''
        }));
    }

    var infoItems = '';

    if (this.isShowModifiersItems && info.hasOwnProperty('items')) {
        for (var i = 0, l = info['items'].length; i < l; i++) {
            if (info['items'][i]['image']) {
                infoItems += '<img src="' + info['items'][i]['image'].split(',')[0] + '" title="' + info['items'][i]['name'] + '">';
            }
        }
    }

    var $tags = $('<input/>', {
        class: 'form-control raw-tags',
        type: 'text',
        placeholder: 'tags'
    });

    var $modify = $('<div/>', {class: 'map-item-modify', 'data-column': dbColumn})
        .append($('<label/>', {class: 'from'}).append($from))
        .append(info.hasOwnProperty('total') ? $('<span/>', {class: 'from-total', text: info['total']}) : '')
        .append(infoItems ? $('<span/>', {class: 'from-items', html: infoItems}) : '')
        .append($('<span/>', {text: '='}))
        .append($('<label/>', {class: 'to'}).append($to))
        .append(' ')
        .append('<label><input type="checkbox" name="is_sport" value="1" class="raw-sport"> Спорт</label>')
        .append(' ')
        .append('<label><input type="checkbox" name="is_size_plus" value="1" class="raw-size-plus"> Размер+</label>')
        .append(' ')
        .append($tags)
        .append(' ')
        .append($('<a/>', {class: 'btn btn-default btn-delete-map-item-modify', title: 'Удалить модификатор'})
            .append($('<i/>', {class: 'glyphicon glyphicon-minus'})));

    $mapItem.append($modify);
    $mapItem.addClass('modify');
    this.addEasyZoomToMappingModifyFromItems($modify);

    $from.focus();
};

new snowgirlApp(snowgirlCore);