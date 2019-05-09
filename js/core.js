var snowgirlShop = function (snowgirlCore) {
    this.core = snowgirlCore;

//    console.log(this.core);

    this.core.setErrorHandler(function (text, url, line) {

    });

    this.initQuery();
    this.initArgs();
    this.initCallbacks();

    return this.export();
};
snowgirlShop.prototype.initArgs = function () {
//     this.cartSelector = '.header .header-cart';
//     this.cartSelector = '.header';
//     this.btnCartSelector = this.cartSelector + ' .header-cart-btn';
//     this.syncCartButtonCount();
    //@todo sync if new session only...
//     this.syncCartData();
//     this.getVisitedData();
};
snowgirlShop.prototype.initCallbacks = function () {
//     this.core.$document
//         .on('click', this.btnCartSelector, $.proxy(this.onCartButtonClick, this));
};
snowgirlShop.prototype.onCartButtonClick = function (ev) {
//     var $btn = $(ev.target).getButton();
};

snowgirlShop.prototype.setCartData = function (data) {
    return this.core.storage.set('cart', JSON.stringify(data));
};

snowgirlShop.prototype.getCartData = function () {
    return JSON.parse(this.core.storage.get('cart', '{}'));
};
snowgirlShop.prototype.getCartItemsCount = function () {
    var count = 0;
    var data = this.getCartData();

    for (var key in data) {
        if (data.hasOwnProperty(key)) {
            count += data[key].quantity;
        }
    }

    return count;
};
snowgirlShop.prototype.syncCartButtonCount = function () {
    var $btn = $(this.btnCartSelector);
    var $badge = $btn.find('.badge');
    var count = this.getCartItemsCount();

    if (count > 0) {
        if ($badge.length) {
            $badge.text(count);
        } else {
            $btn.append($('<span/>', {class: 'badge', text: count}));
        }
    } else {
        $badge.remove();
    }

    return true;
};
snowgirlShop.prototype.syncCartData = function (fn) {
    var data = {cart: this.getCartData()};

    return this.core.syncSessionData(data, $.proxy(function () {
        this.syncCartButtonCount();
        fn && fn();
    }, this));
};
snowgirlShop.prototype.addCartItem = function (itemId, sizeId, quantity, fn) {
    console.log('item_id: ' + itemId, 'size_id: ' + sizeId, 'quantity: ' + quantity);

    var data = this.getCartData();
    var key = [itemId, sizeId].join('-');

    if (data.hasOwnProperty(key)) {
        data[key].quantity += quantity;
    } else {
        data[key] = {
            item_id: itemId,
            size_id: sizeId,
            quantity: quantity
        };
    }

    this.setCartData(data);
    this.syncCartData(fn);

    return true;
};

snowgirlShop.prototype.setVisitedData = function (data) {
    return this.core.storage.set('visited', JSON.stringify(data));
};

snowgirlShop.prototype.getVisitedData = function () {
    return JSON.parse(this.core.storage.get('visited', '[]'));
};

snowgirlShop.prototype.addVisitedItem = function (itemId, fn) {
    console.log('item_id: ' + itemId);

    var data = this.getVisitedData();

    if (-1 === data.indexOf(itemId)) {
        data.push(itemId);
        this.setVisitedData(data);
        this.core.syncSessionData({visited: data});
    }

    return true;
};

snowgirlShop.prototype.showCartItems = function () {
    //@todo
};

snowgirlShop.prototype.getCartItems = function () {
    //@todo
};
snowgirlShop.prototype.export = function () {
    return $.extend({}, this.core, {
        addVisitedItem: $.proxy(this.addVisitedItem, this),
        addCartItem: $.proxy(this.addCartItem, this)
    });
};
snowgirlShop.prototype.initQuery = function () {
//     $.ajaxSetup({cache: true});
    $.ajaxSetup({cache: false});
};

snowgirlCore = new snowgirlShop(snowgirlCore);