insert into rbac(`role_id`, `permission_id`) values(2, 101), (4, 102), (4, 103), (4, 104), (4, 115), (2, 116), (4, 123), (3, 124), (3, 117), (3, 118), (3, 119);


alter table `item` change `upc` `partner_item_id` varchar(32) NOT NULL;
alter table `item` drop index `uk_vendor_upc`;
alter table `item` add UNIQUE KEY `uk_vendor_partner_item` (`vendor_id`,`partner_item_id`);

alter table `item` add `partner_updated_at` int(5) unsigned NOT NULL after `order_desc_price`;
update `item` set `partner_updated_at` = unix_timestamp(ifnull(`updated_at`, `created_at`));
alter table `item` drop column `updated_at`;


alter table `item_archive` add `partner_updated_at` int(5) unsigned NOT NULL after `season_id`;
update `item_archive` set `partner_updated_at` = unix_timestamp(ifnull(`updated_at`, `created_at`));
alter table `item_archive` drop column `updated_at`;



alter table item drop KEY `uk_vendor_partner_item`;
alter table item add UNIQUE KEY `uk_source_partner_item` (`import_source_id`,`partner_item_id`);
alter table item KEY `ix_category_source_updated` (`category_id`,`import_source_id`,`partner_updated_at`);