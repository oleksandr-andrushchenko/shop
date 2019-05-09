db.item.createIndex({
    is_sport: 1,
    is_size_plus: 1,
    order_desc_rating: 1
}, {name: 'ix_catalog_desc_rating', background: true});
db.item.createIndex({
    is_sport: 1,
    is_size_plus: 1,
    order_asc_price: 1
}, {name: 'ix_catalog_asc_price', background: true});
db.item.createIndex({
    is_sport: 1,
    is_size_plus: 1,
    order_desc_price: 1
}, {name: 'ix_catalog_desc_price', background: true});
db.item.createIndex({
    is_sport: 1,
    is_size_plus: 1,
    category_id: 1,
    brand_id: 1,
    order_desc_rating: 1
}, {name: 'ix_catalog_category_brand_desc_rating', background: true});
db.item.createIndex({
    is_sport: 1,
    is_size_plus: 1,
    category_id: 1,
    brand_id: 1,
    order_asc_price: 1
}, {name: 'ix_catalog_category_brand_asc_price', background: true});
db.item.createIndex({
    is_sport: 1,
    is_size_plus: 1,
    category_id: 1,
    brand_id: 1,
    order_desc_price: 1
}, {name: 'ix_catalog_category_brand_desc_price', background: true});
db.item.createIndex({vendor_id: 1, upc: 1}, {name: 'uk_vendor_upc', unique: true, background: true});
db.item.createIndex({order_desc_rating: 1}, {name: 'ix_order_desc_rating', background: true});
db.item.createIndex({order_asc_price: 1}, {name: 'ix_order_asc_price', background: true});
db.item.createIndex({order_desc_price: 1}, {name: 'ix_order_desc_price', background: true});
