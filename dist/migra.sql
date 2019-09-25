insert into rbac(`role_id`, `permission_id`) values(2, 101), (4, 102), (4, 103), (4, 104), (4, 115), (2, 116), (4, 123), (3, 124), (3, 117), (3, 118), (3, 119);


alter table `item` change `upc` `partner_item_id` varchar(32) NOT NULL;
alter table `item` drop index `uk_vendor_upc`;
alter table `item` add UNIQUE KEY `uk_vendor_partner_item` (`vendor_id`,`partner_item_id`);

alter table `item` add `partner_updated_at` int(5) unsigned NOT NULL after `order_desc_price`;
update `item` set `partner_updated_at` = unix_timestamp(ifnull(`updated_at`, `created_at`));
alter table `item` drop column `updated_at`;

alter table `item_archive` change `upc` `partner_item_id` varchar(32) NOT NULL;

alter table `item_archive` add `partner_updated_at` int(5) unsigned NOT NULL after `season_id`;
update `item_archive` set `partner_updated_at` = unix_timestamp(ifnull(`updated_at`, `created_at`));
alter table `item_archive` drop column `updated_at`;

alter table item drop KEY `uk_vendor_partner_item`;
alter table item add UNIQUE KEY `uk_source_partner_item` (`import_source_id`,`partner_item_id`);
alter table item add KEY `ix_category_source_updated` (`category_id`,`import_source_id`,`partner_updated_at`);


alter table item
  drop key `ix_order_desc_rating`,
  drop key `ix_order_asc_price`,
  drop key `ix_order_desc_price`,
  add column `updated_at` timestamp NULL DEFAULT NULL after created_at;

alter table item_archive add column `updated_at` timestamp NULL DEFAULT NULL after created_at;

alter table category_entity
  change `value` `entity` tinytext DEFAULT NULL,
  change `value_hash` `entity_hash` char(32) DEFAULT NULL;


alter table category_entity
  change `entity` `entity` tinytext NOT NULL,
  change `entity_hash` `entity_hash` char(32) NOT NULL,
  add column `stop_words` tinytext default NULL after entity_hash,
  change is_active `is_active` tinyint(1) unsigned NOT NULL DEFAULT 0,
  change `count` `count` smallint(5) unsigned NOT NULL DEFAULT 0,
  drop key category_id,
  add UNIQUE KEY `uk_entity` (`entity_hash`);

alter table item_archive
  change created_at `created_at` timestamp NOT NULL;