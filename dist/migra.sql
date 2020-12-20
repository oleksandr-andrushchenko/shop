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
  change `count` `count` int(7) unsigned NOT NULL DEFAULT 0,
  drop key category_id,
  add UNIQUE KEY `uk_category_entity` (`category_id`, `entity_hash`);

alter table item_archive
  change created_at `created_at` timestamp NOT NULL;


truncate table `import_history`;

alter table `import_history`
  change `file_unique_hash` `hash` varchar(32) not null,
  add `count_total` int(5) unsigned null default null after `is_ok`,
  add `count_filtered_filter` int(5) unsigned null default null after `count_total`,
  add `count_filtered_modifier` int(5) unsigned null default null after `count_filtered_filter`,
  add `count_skipped_unique` int(5) unsigned null default null after `count_filtered_modifier`,
  add `count_skipped_updated` int(5) unsigned null default null after `count_skipped_unique`,
  add `count_skipped_other` int(5) unsigned null default null after `count_skipped_updated`,
  add `count_passed` int(5) unsigned null default null after `count_skipped_other`,
  add `count_affected` int(5) unsigned null default null after `count_passed`,
  add `error` varchar(2048) null default null after `count_affected`,
  drop `is_ok`;


alter table `item` drop `order_desc_rating`,
  drop `order_asc_price`,
  drop `order_desc_price`,
  drop `order_desc_relevance`;


alter table `item` add `order_desc_relevance` int(11) NOT NULL DEFAULT 0 after `import_source_id`,
  add `order_desc_rating` int(11) NOT NULL DEFAULT 0 after `order_desc_relevance`,
  add `order_asc_price` int(11) NOT NULL DEFAULT 0 after `order_desc_rating`,
  add `order_desc_price` int(11) NOT NULL DEFAULT 0 after `order_asc_price`;


alter table `item` drop `image_count`,
  add `partner_link_hash` varchar(32) not null after `partner_link`,
--   add `is_404` tinyint(1) unsigned not null default 0,
  drop key `uk_image`;

update `item` set `partner_link_hash` = MD5(`partner_link`);

alter table `item_archive` drop `image_count`,
  add `partner_link_hash` varchar(32) not null after `partner_link`,
  add `image_id` varchar(164) default null after `season_id`;

update `item_archive` set `partner_link_hash` = MD5(`partner_link`);

CREATE TABLE `item_image` (
  `item_id` int(10) unsigned NOT NULL,
  `image_id` varchar(32) NOT NULL,
  PRIMARY KEY (`item_id`,`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




CREATE TABLE `attribute` (
  `attribute_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `category_id` smallint(5) unsigned DEFAULT NULL,
  `is_mva` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `attribute_value` (
  `attribute_value_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_id` tinyint(3) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`attribute_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `item_attribute_value` (
  `item_id` int(10) unsigned NOT NULL,
  `attribute_id` tinyint(3) unsigned NOT NULL,
  `attribute_value_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`item_id`,`attribute_id`,`attribute_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


alter table vendor add `is_in_stock_check` tinyint(1) NOT NULL DEFAULT 0 after class_name;

alter table `item` drop `order_desc_rating`,
  drop `order_asc_price`,
  drop `order_desc_price`,
  drop `order_desc_relevance`;


alter table `import_history` add `count_out_of_stock` int(5) unsigned DEFAULT NULL after `count_affected`;


alter table country_term drop CONSTRAINT country_term_ibfk_1;
alter table country_term change country_id `country_id` smallint unsigned NOT NULL;
alter table country change country_id `country_id` smallint unsigned NOT NULL AUTO_INCREMENT;

alter table item change country_id country_id smallint(5) unsigned DEFAULT NULL;
alter table item_archive change country_id country_id smallint(5) unsigned DEFAULT NULL;


alter table vendor drop column is_active, add column target_vendor_id tinyint(3) unsigned null after is_in_stock_check;
drop table item_archive;
alter table import_source drop column `type`;