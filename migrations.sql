ALTER TABLE `item` CHANGE `image` `image` CHAR(32) NOT NULL;
ALTER TABLE `item` CHANGE `rating` `rating` SMALLINT UNSIGNED NOT NULL DEFAULT 0
AFTER `code`;
ALTER TABLE `item` CHANGE `description` `description` TEXT NOT NULL DEFAULT '';
# ALTER TABLE `item` CHANGE `hidden_description` `hidden_description` TEXT NOT NULL DEFAULT '';
ALTER TABLE `item` CHANGE `entity` `entity` TINYTEXT NOT NULL DEFAULT '';
ALTER TABLE `item` ADD UNIQUE INDEX `ix_image` (`image`);

ALTER TABLE `brand` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `brand` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `category` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `category` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `color` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `color` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `color_term` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `color_term` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `country` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `country` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `import_history` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `import_history` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `import_source` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `import_source` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `item` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `item` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `item_click` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `item_click` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

DROP TABLE IF EXISTS `option`;

ALTER TABLE `page_blog` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `page_blog` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `page_catalog` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `page_catalog` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `page_regular` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `page_regular` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `redirect` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `redirect` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `season` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `season` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `user` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `user` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `vendor` CHANGE `created` `created_at` TIMESTAMP DEFAULT current_timestamp;
ALTER TABLE `vendor` CHANGE `updated` `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE `size` (
  `size_id`    SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       TINYTEXT             NOT NULL,
  `name_hash`  CHAR(32)             NOT NULL DEFAULT '',
  `uri`        TINYTEXT             NOT NULL,
  `created_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`size_id`),
  UNIQUE KEY `name_hash` (`name_hash`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

CREATE TABLE `item_size` (
  `item_id` INT(10) UNSIGNED     NOT NULL DEFAULT '0',
  `size_id` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  UNIQUE KEY `item_id` (`item_id`, `size_id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

CREATE TABLE `material` (
  `material_id` TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        TINYTEXT            NOT NULL,
  `name_hash`   CHAR(32)            NOT NULL DEFAULT '',
  `uri`         TINYTEXT            NOT NULL,
  `created_at`  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`material_id`),
  UNIQUE KEY `name_hash` (`name_hash`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

CREATE TABLE `item_color` (
  `item_id`     INT(10) UNSIGNED    NOT NULL DEFAULT '0',
  `color_id` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
  UNIQUE KEY `item_id` (`item_id`, `color_id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

INSERT INTO `item_color` (`item_id`, `color_id`) SELECT `item_id`, `color_id` FROM `item`;

ALTER TABLE `item` DROP COLUMN `color_id`;

CREATE TABLE `item_material` (
  `item_id`     INT(10) UNSIGNED    NOT NULL DEFAULT '0',
  `material_id` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
  UNIQUE KEY `item_id` (`item_id`, `material_id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

CREATE TABLE `color_term2` (
  `id`         SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `color_id`   TINYINT(3) UNSIGNED  NOT NULL DEFAULT '0',
  `lang`       CHAR(2)              NOT NULL DEFAULT 'ru',
  `value`      TINYTEXT             NOT NULL,
  `created_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

INSERT INTO `color_term2` (`color_id`, `lang`, `value`) SELECT `color_id`, 'ru', `value` FROM `color_term`;
INSERT INTO `color_term2` (`color_id`, `lang`, `value`) SELECT `color_id`, 'de', `value_de` FROM `color_term` WHERE `value_de` IS NOT NULL;
DROP TABLE `color_term`;
RENAME TABLE `color_term2` TO `color_term`;

DROP TABLE `country_term`;

CREATE TABLE `country_term` (
  `id`         SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_id` TINYINT(3) UNSIGNED  NOT NULL DEFAULT '0',
  `lang`       CHAR(2)              NOT NULL DEFAULT 'ru',
  `value`      TINYTEXT             NOT NULL,
  `created_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

INSERT INTO `country_term` (`country_id`, `lang`, `value`) SELECT `country_id`, 'ru', `name` FROM `country`;

CREATE TABLE `season_term` (
  `id`         SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `season_id`  TINYINT(1) UNSIGNED  NOT NULL DEFAULT '0',
  `lang`       CHAR(2)              NOT NULL DEFAULT 'ru',
  `value`      TINYTEXT             NOT NULL,
  `created_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

INSERT INTO `season_term` (`season_id`, `lang`, `value`) SELECT `season_id`, 'ru', `name` FROM `season`;

ALTER TABLE `import_source` CHANGE `color_langs` `langs` VARCHAR(32) NOT NULL DEFAULT 'ru';
UPDATE `import_source`
SET `langs` = 'ru'
WHERE `langs` IS NULL OR `langs` = '';

CREATE TABLE `material_term` (
  `id`          SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `material_id` TINYINT(3) UNSIGNED  NOT NULL DEFAULT '0',
  `lang`        CHAR(2)              NOT NULL DEFAULT 'ru',
  `value`       TINYTEXT             NOT NULL,
  `created_at`  TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

CREATE TABLE `size_term` (
  `id`         SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `size_id`    TINYINT(3) UNSIGNED  NOT NULL DEFAULT '0',
  `lang`       CHAR(2)              NOT NULL DEFAULT 'ru',
  `value`      TINYTEXT             NOT NULL,
  `created_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

ALTER TABLE `item` DROP COLUMN `size`;
ALTER TABLE `item` DROP COLUMN `code`;
ALTER TABLE `import_source` DROP COLUMN `fix_items_classes`;

ALTER TABLE `import_history` CHANGE `status` `is_ok` TINYINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `import_history` DROP COLUMN `is_finished`;
ALTER TABLE `import_source` CHANGE `cron_import` `is_cron` TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE `item_season` (
  `item_id`   INT(10) UNSIGNED    NOT NULL DEFAULT '0',
  `season_id` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  UNIQUE KEY `item_id` (`item_id`, `season_id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

INSERT INTO `item_season` (`item_id`, `season_id`) SELECT `item_id`, `season_id` FROM `item`;
ALTER TABLE `item` DROP COLUMN `season_id`;

ALTER TABLE `item` DROP COLUMN `notes`;

ALTER TABLE `import_source` ADD COLUMN `source_column` TINYTEXT NOT NULL
AFTER `class`;
ALTER TABLE `item` DROP COLUMN `hidden_description`;

DELETE FROM `item_season`
WHERE `season_id` = 0;

ALTER TABLE `page_catalog` CHANGE COLUMN `season_id` `season_id` TINYINT UNSIGNED NOT NULL DEFAULT 0
AFTER `vendor_id`;
ALTER TABLE `page_catalog` ADD COLUMN `size_id` SMALLINT UNSIGNED NOT NULL DEFAULT 0
AFTER `vendor_id`;
ALTER TABLE `page_catalog` ADD COLUMN `material_id` SMALLINT UNSIGNED NOT NULL DEFAULT 0
AFTER `vendor_id`;

ALTER TABLE `page_catalog` DROP KEY `uk_search`;
ALTER TABLE `page_catalog` DROP KEY `category_id`;
ALTER TABLE `page_catalog` DROP KEY `brand_id`;
ALTER TABLE `page_catalog` DROP KEY `vendor_id`;
ALTER TABLE `page_catalog` DROP KEY `season_id`;
ALTER TABLE `page_catalog` DROP KEY `color_id`;
ALTER TABLE `page_catalog` DROP KEY `country_id`;


ALTER TABLE `page_catalog` ADD COLUMN `tag_id` SMALLINT UNSIGNED NOT NULL DEFAULT 0
AFTER `season_id`;
ALTER TABLE `page_catalog` ADD UNIQUE KEY `uk_search` (`category_id`, `tag_id`, `brand_id`, `color_id`, `material_id`, `size_id`, `country_id`, `season_id`, `vendor_id`);

CREATE TABLE `tag` (
  `tag_id`     SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       TINYTEXT             NOT NULL,
  `name_hash`  CHAR(32)             NOT NULL DEFAULT '',
  `uri`        TINYTEXT             NOT NULL,
  `created_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY `name_hash` (`name_hash`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

CREATE TABLE `item_tag` (
  `item_id` INT(10) UNSIGNED     NOT NULL DEFAULT '0',
  `tag_id`  SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  UNIQUE KEY `item_id` (`item_id`, `tag_id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

ALTER TABLE `color` CHANGE `declining_name` `title_name` VARCHAR(256) NOT NULL;

CREATE TABLE `tag_term` (
  `id`         SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag_id`     SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  `lang`       CHAR(2)              NOT NULL DEFAULT 'ru',
  `value`      TINYTEXT             NOT NULL,
  `created_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

# TRUNCATE TABLE item_size;
# SET  @num := 0;
# UPDATE size SET size_id = @num := (@num+1);
# ALTER TABLE size AUTO_INCREMENT =1;


alter table `item_color` drop key `item_id`;
alter table `item_color` add primary key (`item_id`, `color_id`);

alter table `item_material` drop key `item_id`;
alter table `item_material` add primary key (`item_id`, `material_id`);

alter table `item_season` drop key `item_id`;
alter table `item_season` add primary key (`item_id`, `season_id`);

alter table `item_size` drop key `item_id`;
alter table `item_size` add primary key (`item_id`, `size_id`);

alter table `item_tag` drop key `item_id`;
alter table `item_tag` add primary key (`item_id`, `tag_id`);

alter table `tag` add column `rating` smallint unsigned not null default 0 after `uri`;
alter table `material` add column `rating` smallint unsigned not null default 0 after `uri`;
alter table `brand` add column `rating` smallint unsigned not null default 0 after `image`;

alter table `page_catalog` add column `meta_title` varchar(2048) after `name`;
alter table `page_catalog` add column `meta_description` varchar(4096) after `meta_title`;
alter table `page_catalog` add column `meta_keywords` varchar(1024) after `meta_description`;
alter table `page_catalog` add column `title` varchar(1024) after `meta_keywords`;
alter table `page_catalog` add column `articles` mediumtext after `title`;

alter table `color`  add column `hex` char(6) not null after `title_name`;
alter table `item` drop key `ix_catalog_order_updated`, add key `ix_catalog_order_updated_at` (`is_in_stock`,`updated_at`,`rating`,`item_id`);

CREATE TABLE `category_entity` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  `value` tinytext NOT NULL,
  `value_hash` char(32) NOT NULL DEFAULT '',
  `count` smallint(5) unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_id` (`category_id`,`value_hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*    AFTER example.com DEPLOYMENT>..  */

ALTER TABLE `item` DROP INDEX `ix_catalog_order_rating`;
ALTER TABLE `item` ADD INDEX `ix_catalog_order_rating` (`is_in_stock`, `updated_at`, `rating`, `item_id`);
ALTER TABLE `item` DROP INDEX `ix_catalog_order_price`;
ALTER TABLE `item` ADD INDEX `ix_catalog_order_price` (`is_in_stock`, `price`, `updated_at`, `rating`, `item_id`);
ALTER TABLE `item` DROP INDEX `ix_catalog_order_updated_at`;
ALTER TABLE `item` ADD INDEX `ix_catalog_order_updated_at` (`is_in_stock`, `updated_at`, `rating`, `item_id`);

/*    29.06  */

alter table item engine=innodb;
# alter table item drop key category_id;
alter table item change category_id category_id smallint unsigned null;
alter table category drop foreign key category_ibfk_1;
alter table category drop key parent_category_id;
alter table category change category_id category_id smallint unsigned not null auto_increment;
alter table category change parent_category_id parent_category_id smallint unsigned null;
alter table category add foreign key(parent_category_id) references category(category_id) on delete set null on update cascade;
update item set category_id = null where category_id = 0;
alter table item add foreign key (category_id) references category(category_id) on delete set null on update cascade;
alter table brand change brand_id brand_id smallint unsigned not null auto_increment;
# alter table item drop key brand_id;
alter table item change brand_id brand_id smallint unsigned null;
update item set brand_id = null where brand_id = 0;
alter table item add foreign key (brand_id) references brand(brand_id) on delete set null on update cascade;
alter table import_source drop foreign key import_source_ibfk_1;
alter table vendor change vendor_id vendor_id tinyint unsigned not null auto_increment;
alter table import_source change vendor_id vendor_id tinyint unsigned null;
alter table import_source add foreign key (vendor_id) references vendor(vendor_id) on delete set null on update cascade;
alter table item change vendor_id vendor_id tinyint unsigned null;
update item set vendor_id = null where vendor_id = 0;
alter table item add foreign key (vendor_id) references vendor(vendor_id) on delete set null on update cascade;
alter table country change country_id country_id tinyint unsigned not null auto_increment;
alter table item change country_id country_id tinyint unsigned null;
update item set country_id = null where country_id = 0;
alter table item add foreign key (country_id) references country(country_id) on delete set null on update cascade;


alter table page_catalog drop key `uk_search`;

alter table page_catalog engine=innodb;

alter table page_catalog change category_id category_id smallint unsigned null;
update page_catalog set category_id = null where category_id = 0;
delete from page_catalog where category_id is not null and category_id not in (select category_id from category);
alter table page_catalog add foreign key(category_id) references category(category_id) on delete cascade on update cascade;

alter table page_catalog change brand_id brand_id smallint unsigned null;
update page_catalog set brand_id = null where brand_id = 0;
delete from page_catalog where brand_id is not null and brand_id not in (select brand_id from brand);
alter table page_catalog add foreign key(brand_id) references brand(brand_id) on delete cascade on update cascade;

alter table color change color_id color_id smallint unsigned not null auto_increment;

alter table page_catalog change color_id color_id smallint unsigned null;
update page_catalog set color_id = null where color_id = 0;
delete from page_catalog where color_id is not null and color_id not in (select color_id from color);
alter table page_catalog add foreign key(color_id) references color(color_id) on delete cascade on update cascade;

alter table page_catalog change vendor_id vendor_id tinyint unsigned null;
update page_catalog set vendor_id = null where vendor_id = 0;
delete from page_catalog where vendor_id is not null and vendor_id not in (select vendor_id from vendor);
alter table page_catalog add foreign key(vendor_id) references vendor(vendor_id) on delete cascade on update cascade;

alter table season change season_id season_id tinyint unsigned not null auto_increment;

alter table page_catalog change season_id season_id tinyint unsigned null;
update page_catalog set season_id = null where season_id = 0;
delete from page_catalog where season_id is not null and season_id not in (select season_id from season);
alter table page_catalog add foreign key(season_id) references season(season_id) on delete cascade on update cascade;

alter table material engine=innodb;
alter table material change material_id material_id smallint unsigned not null auto_increment;

alter table page_catalog change material_id material_id smallint unsigned null;
update page_catalog set material_id = null where material_id = 0;
delete from page_catalog where material_id is not null and material_id not in (select material_id from material);
alter table page_catalog add foreign key(material_id) references material(material_id) on delete cascade on update cascade;

alter table size engine=innodb;
alter table size change size_id size_id smallint unsigned not null auto_increment;

alter table page_catalog change size_id size_id smallint unsigned null;
update page_catalog set size_id = null where size_id = 0;
delete from page_catalog where size_id is not null and size_id not in (select size_id from size);
alter table page_catalog add foreign key(size_id) references size(size_id) on delete cascade on update cascade;

alter table tag engine=innodb;
alter table tag change tag_id tag_id smallint unsigned not null auto_increment;

alter table page_catalog change tag_id tag_id smallint unsigned null;
update page_catalog set tag_id = null where tag_id = 0;
delete from page_catalog where tag_id is not null and tag_id not in (select tag_id from tag);
alter table page_catalog add foreign key(tag_id) references tag(tag_id) on delete cascade on update cascade;

alter table page_catalog change country_id country_id tinyint unsigned null;
update page_catalog set country_id = null where country_id = 0;
delete from page_catalog where country_id is not null and country_id not in (select country_id from country);
alter table page_catalog add foreign key(country_id) references country(country_id) on delete cascade on update cascade;

alter table page_catalog add UNIQUE KEY `uk_search` (`category_id`,`tag_id`,`brand_id`,`color_id`,`material_id`,`size_id`,`country_id`,`season_id`,`vendor_id`);



alter table item change item_id item_id int unsigned not null auto_increment;

alter table item_color engine=innodb;
alter table item_color change item_id item_id int unsigned not null;
delete from item_color where item_id not in (select item_id from item);
alter table item_color add foreign key(item_id) references item(item_id) on delete cascade on update cascade;
alter table item_color change color_id color_id smallint unsigned not null;
delete from item_color where color_id not in (select color_id from color);
alter table item_color add foreign key(color_id) references color(color_id) on delete cascade on update cascade;

alter table item_material engine=innodb;
alter table item_material change item_id item_id int unsigned not null;
delete from item_material where item_id not in (select item_id from item);
alter table item_material add foreign key(item_id) references item(item_id) on delete cascade on update cascade;
alter table item_material change material_id material_id smallint unsigned not null;
delete from item_material where material_id not in (select material_id from material);
alter table item_material add foreign key(material_id) references material(material_id) on delete cascade on update cascade;

alter table item_season engine=innodb;
alter table item_season change item_id item_id int unsigned not null;
delete from item_season where item_id not in (select item_id from item);
alter table item_season add foreign key(item_id) references item(item_id) on delete cascade on update cascade;
alter table item_season change season_id season_id tinyint unsigned not null;
delete from item_season where season_id not in (select season_id from season);
alter table item_season add foreign key(season_id) references season(season_id) on delete cascade on update cascade;

alter table item_size engine=innodb;
alter table item_size change item_id item_id int unsigned not null;
delete from item_size where item_id not in (select item_id from item);
alter table item_size add foreign key(item_id) references item(item_id) on delete cascade on update cascade;
alter table item_size change size_id size_id smallint unsigned not null;
delete from item_size where size_id not in (select size_id from size);
alter table item_size add foreign key(size_id) references size(size_id) on delete cascade on update cascade;

alter table item_tag engine=innodb;
alter table item_tag change item_id item_id int unsigned not null;
delete from item_tag where item_id not in (select item_id from item);
alter table item_tag add foreign key(item_id) references item(item_id) on delete cascade on update cascade;
alter table item_tag change tag_id tag_id smallint unsigned not null;
delete from item_tag where tag_id not in (select tag_id from tag);
alter table item_tag add foreign key(tag_id) references tag(tag_id) on delete cascade on update cascade;

alter table item_click engine=innodb;
alter table item_click change item_id item_id int unsigned not null;
delete from item_click where item_id not in (select item_id from item);
alter table item_click add foreign key(item_id) references item(item_id) on delete cascade on update cascade;


alter table material_term engine=innodb;
alter table material_term change material_id material_id smallint unsigned not null;
delete from material_term where material_id not in (select material_id from material);
alter table material_term add foreign key(material_id) references material(material_id) on delete cascade on update cascade;

alter table season_term engine=innodb;
alter table season_term change season_id season_id tinyint unsigned not null;
delete from season_term where season_id not in (select season_id from season);
alter table season_term add foreign key(season_id) references season(season_id) on delete cascade on update cascade;

alter table size_term engine=innodb;
alter table size_term change size_id size_id smallint unsigned not null;
delete from size_term where size_id not in (select size_id from size);
alter table size_term add foreign key(size_id) references size(size_id) on delete cascade on update cascade;

alter table tag_term engine=innodb;
alter table tag_term change tag_id tag_id smallint unsigned not null;
delete from tag_term where tag_id not in (select tag_id from tag);
alter table tag_term add foreign key(tag_id) references tag(tag_id) on delete cascade on update cascade;

alter table country_term engine=innodb;
alter table country_term change country_id country_id tinyint unsigned not null;
delete from country_term where country_id not in (select country_id from country);
alter table country_term add foreign key(country_id) references country(country_id) on delete cascade on update cascade;

alter table color_term engine=innodb;
alter table color_term change color_id color_id smallint unsigned not null;
delete from color_term where color_id not in (select color_id from color);
alter table color_term add foreign key(color_id) references color(color_id) on delete cascade on update cascade;

drop table if exists category_children;

alter table category_entity engine=innodb;
alter table category_entity change category_id category_id smallint unsigned not null;
delete from category_entity where category_id not in (select category_id from category);
alter table category_entity add foreign key (category_id) references category(category_id) on delete cascade on update cascade;

# Restore index uk_search
# @todo replace uk_search with PK (multi)

/*    02.07  */
alter table `color` add column `rating` smallint(5) unsigned NOT NULL DEFAULT '0' after `hex`;

/*    03.07  */
alter table size add column `rating` smallint(5) unsigned NOT NULL DEFAULT '0' after `uri`;

/*    03.07  */
alter table `page_regular` drop column `uri`;
alter table `page_regular` drop column `uri_history`;

/*    19.07  */
alter table size drop column name_hash;
alter table material drop column name_hash;
alter table tag drop column name_hash;

/*    26.09  */
alter table `import_source` change column `class` `class_name` varchar(128) NOT NULL;

/** 05.10.2017 */
update `page_regular` set `menu_title` = 'Каталог', `is_menu` = 1, `rating` = 4 where `key` = 'catalog';
update `page_regular` set `menu_title` = 'Бренды', `is_menu` = 1, `rating` = 3 where `key` = 'brands';
update `page_regular` set `menu_title` = 'Акции', `is_menu` = 1, `rating` = 2 where `key` = 'stock';
update `page_regular` set `menu_title` = 'Магазины', `is_menu` = 1, `rating` = 1 where `key` = 'vendors';
update `page_regular` set `menu_title` = 'Контакты', `is_menu` = 1, `rating` = 0 where `key` = 'contacts';


/** 24.10.2017 */
alter table `tag` add column `name_hash` char(32) not null after `name`;
update `tag` set `name_hash` = md5(`name`);
alter table `tag` add unique key (`name_hash`);

/** 25.10.2017 */
alter table `category` add column `rating` smallint unsigned not null default 0 after `uri`;

/** 28.10.2017 */
alter table `category` add column `image` char(32) null default null after `uri`;
alter table `brand` change `image` `image` char(32) null default null;

/** 03.11.2017 */
/** alter table item add  foreign key (category_id) references category(category_id) on delete cascade on update cascade; */

/** 04.11.2017 */
alter table item_tag drop foreign key item_color_ibfk_1, drop foreign key item_color_ibfk_2;
alter table item_color drop foreign key item_color_ibfk_1, drop foreign key item_color_ibfk_2;
alter table item_material drop foreign key item_material_ibfk_1, drop foreign key item_material_ibfk_2;
alter table item_size drop foreign key item_size_ibfk_1, drop foreign key item_size_ibfk_2;
alter table item_season drop foreign key item_season_ibfk_1, drop foreign key item_season_ibfk_2;

drop table if exists item_click;

alter table page_catalog drop foreign key page_catalog_ibfk_1,
drop foreign key page_catalog_ibfk_2,
drop foreign key page_catalog_ibfk_3,
drop foreign key page_catalog_ibfk_4,
drop foreign key page_catalog_ibfk_5,
drop foreign key page_catalog_ibfk_6,
drop foreign key page_catalog_ibfk_7,
drop foreign key page_catalog_ibfk_8,
drop foreign key page_catalog_ibfk_9;

update page_catalog set category_id = 0 where category_id is null;
alter table page_catalog change column category_id category_id smallint(5) unsigned not null default 0;

update page_catalog set tag_id = 0 where tag_id is null;
alter table page_catalog change column tag_id tag_id smallint(5) unsigned not null default 0 after category_id;

update page_catalog set brand_id = 0 where brand_id is null;
alter table page_catalog change column brand_id brand_id smallint(5) unsigned not null default 0 after tag_id;

update page_catalog set color_id = 0 where color_id is null;
alter table page_catalog change column color_id color_id smallint(5) unsigned not null default 0 after brand_id;

update page_catalog set material_id = 0 where material_id is null;
alter table page_catalog change column material_id material_id smallint(5) unsigned not null default 0 after color_id;

update page_catalog set size_id = 0 where size_id is null;
alter table page_catalog change column size_id size_id smallint(5) unsigned not null default 0 after material_id;

update page_catalog set country_id = 0 where country_id is null;
alter table page_catalog change column country_id country_id tinyint(3) unsigned not null default 0 after size_id;

update page_catalog set season_id = 0 where season_id is null;
alter table page_catalog change column season_id season_id tinyint(3) unsigned not null default 0 after country_id;

update page_catalog set vendor_id = 0 where vendor_id is null;
alter table page_catalog change column vendor_id vendor_id tinyint(3) unsigned not null default 0 after season_id;

alter table page_catalog change column `count` `count` smallint(5) unsigned not null default 0;
alter table page_catalog add key (`count`);




alter table page_catalog add column `tmp` int after page_catalog_id;
alter table page_catalog drop column page_catalog_id;
alter table page_catalog add column `page_catalog_id` int(11) NOT NULL AUTO_INCREMENT after tmp, add primary key (page_catalog_id);
alter table page_catalog drop column `tmp`;

alter table page_catalog add column is_articles tinyint(1) not null default 0 after articles, add key (`is_articles`);
update page_catalog set is_articles = 1 where articles is not null;

/** 07.11.2017 */
alter table page_catalog add key (created_at),add key (updated_at);

/** 15.11.2017 */
alter table page_catalog drop key created_at,drop key updated_at;
alter table item add key `ix_fix_category_vendor`(category_id, vendor_id, created_at, updated_at), add key `ix_fix_vendor`(vendor_id, created_at, updated_at);

/** 01.12.2017 */

CREATE TABLE `brand_term` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `brand_id` smallint(5) unsigned NOT NULL,
  `value` tinytext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `brand_term_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brand` (`brand_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


# select GROUP_CONCAT(c) from (select b.brand_id as `c`, count(*) as cnt from brand b inner join item i using(brand_id) group by b.brand_id having cnt =2) t;
# delete from item where brand_id in (%);
# delete from page_catalog where brand_id in (%);
# delete from brand_term where brand_id in (%);
# delete from brand where brand_id in (%);
# select true;

# select GROUP_CONCAT(brand_id) from (select b.brand_id, b.name,b.uri,i.item_id from brand b left join item i using(brand_id) where item_id is null group by b.brand_id) t;

/** 06.12.2017 */
alter table category_entity change `count` `count` mediumint unsigned not null default 0;

/** 07.12.2017 */
alter table import_source change source_column source_column tinytext null default null;


/** 14.12.2017 */
# SELECT @max := MAX(`id`)+ 1 FROM `category_entity`;
# alter table category_entity AUTO_INCREMENT = @max;

alter table category add column is_leaf tinyint unsigned not null default 1 after rating;

/** 16.12.2017 */
CREATE TABLE `stock` (
  `stock_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(512) NOT NULL,
  `images` text,
  `href` varchar(512) NOT NULL,
  `is_active` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`stock_id`),
  KEY `is_active` (`is_active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/** 18.12.2017 */
alter table `vendor` add column `class_name` text null default null after `image`;


/** 24.12.2017 */
alter table item drop key ix_catalog_order_price, drop key ix_catalog_order_updated_at, drop key ix_catalog_order_rating;

/** 14.01.2018 */

alter table item add column `is_sport` tinyint(1) unsigned not null default 0 after `vendor_id`;
alter table item add column `is_size_plus` tinyint(1) unsigned not null default 0 after `is_sport`;

/** 17.01.2018 */

alter table page_catalog add column `is_sport` tinyint(1) unsigned not null default 0 after vendor_id;
alter table page_catalog add column `is_size_plus` tinyint(1) unsigned not null default 0 after is_sport;
alter table page_catalog add column `is_sales` tinyint(1) unsigned not null default 0 after is_size_plus;
alter table page_catalog drop key uk_search;
alter table page_catalog add unique key `uk_search` (`category_id`,`tag_id`,`brand_id`,`color_id`,`material_id`,`size_id`,`country_id`,`season_id`,`vendor_id`,is_sport,is_size_plus,is_sales);

/** 23.01.2018 */

# alter table `item` add column `order_desc_rating` int not null default 0 after `is_in_stock`;
# set @num=0;
# update `item` as `i` inner join (select `item_id`, @num:=@num+1 as `num` FROM `item` order by `is_in_stock` desc, `rating` desc, IFNULL(`updated_at`, `created_at`) desc, `item_id` desc) as `i2` using(`item_id`) set `i`.`order_desc_rating` = `i2`.`num`;
# alter table `item` add index `ix_order_desc_rating`(`order_desc_rating`);
#
# alter table `item` add column `order_asc_price` int not null default 0 after `order_desc_rating`;
# set @num=0;
# update `item` as `i` inner join (select `item_id`, @num:=@num+1 as `num` FROM `item` order by `is_in_stock` desc, `price` asc, IFNULL(`updated_at`, `created_at`) desc, `rating` desc, `item_id` desc) as `i2` using(`item_id`) set `i`.`order_asc_price` = `i2`.`num`;
# alter table `item` add index `ix_order_asc_price`(`order_asc_price`);
#
# alter table `item` add column `order_desc_price` int not null default 0 after `order_asc_price`;
# set @num=0;
# update `item` as `i` inner join (select `item_id`, @num:=@num+1 as `num` FROM `item` order by `is_in_stock` desc, `price` desc, IFNULL(`updated_at`, `created_at`) desc, `rating` desc, `item_id` desc) as `i2` using(`item_id`) set `i`.`order_desc_price` = `i2`.`num`;
# alter table `item` add index `ix_order_desc_price`(`order_desc_price`);

# order_desc_rating - `is_in_stock` DESC, `rating` DESC, IFNULL(`updated_at`, `created_at`) DESC, `item_id` DESC
# order_asc_price - `is_in_stock` DESC, `price` ASC, IFNULL(`updated_at`, `created_at`) DESC, `rating` DESC, `item_id` DESC
# order_desc_price - `is_in_stock` DESC, `price` ASC, IFNULL(`updated_at`, `created_at`) DESC, `rating` DESC, `item_id` DESC


/** 16.02.2018 */

alter table `stock` change `href` `link` varchar(512) NOT NULL;

/** 06.03.2018 */

alter table `page_catalog` drop column `uri_history`;

/** 18.03.2018 */

alter table page_catalog add key `sitemap_no_content` (`is_articles`, `count`);

/** 20.03.2018 */

alter table page_catalog drop key `sitemap_no_content`;
alter table page_catalog add key sitemap_no_content(page_catalog_id,is_articles);

alter table item add key sitemap(item_id,is_active);

/** 22.03.2018 */

alter table page_catalog change title h1  varchar(1024) DEFAULT NULL;
alter table item change source_item_id upc varchar(32) NOT NULL;
alter table import_source change source_column source_column tinytext;

/** 25.03.2018 */

alter table item drop key `uk_image`;
alter table item drop key `uk_vendor_source`;
alter table item add unique key `uk_vendor_upc` (vendor_id, upc);

/** 26.03.2018 */

alter table page_catalog drop key `sitemap_no_content`;
alter table page_catalog add key `sitemap`(page_catalog_id,is_articles);

alter table brand add column `no_index` tinyint(1) unsigned not null default 0 after `image`;

/** 01.04.2018 */

alter table item drop key `ix_catalog`;
alter table vendor drop column image;
alter table vendor add column image char(32) null after `uri`;
alter table vendor add column `partner_link` varchar(1024) after `name`;
alter table item change `uri` `partner_link` varchar(1024);

/** 08.04.2018 */

CREATE TABLE `category_alias` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` smallint(5) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `title` varchar(256) DEFAULT NULL,
  `breadcrumb` varchar(128) DEFAULT NULL,
  `uri` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `uri` (`uri`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `category_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/** 12.04.2018 */

CREATE TABLE `page_catalog2` (
  `page_catalog2_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(512) NOT NULL,
  `uri` varchar(512) NOT NULL,
  `uri_hash` char(32) NOT NULL,
  `meta_title` varchar(2048) DEFAULT NULL,
  `meta_description` varchar(4096) DEFAULT NULL,
  `meta_keywords` varchar(2048) DEFAULT NULL,
  `h1` varchar(2048) DEFAULT NULL,
  `body` mediumtext,
  `params` varchar(2048) DEFAULT NULL,
  `params_hash` char(32) DEFAULT NULL,
  `seo_texts` mediumtext,
  `meta` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`page_catalog2_id`),
  UNIQUE KEY `uk_uri` (`uri_hash`),
  KEY `ix_params` (`params_hash`),
  KEY `ix_updated_at` (`updated_at`),
  KEY `ix_sitemap` (`page_catalog2_id`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

alter table category_alias change id category_alias_id smallint(5) unsigned NOT NULL AUTO_INCREMENT;

/** 21.04.2018 */

alter table item_tag drop key tag_id;
alter table item_tag add index ix_tag (tag_id);
alter table item_material drop key material_id;
alter table item_material add index ix_material (material_id);
alter table item_color drop key color_id;
alter table item_color add index ix_color (color_id);
alter table item_size drop key size_id;
alter table item_size add index ix_size (size_id);
alter table item_season drop key season_id;
alter table item_season add index ix_season (season_id);

/** 22.04.2018 */

# run after transfer
alter table item drop is_active;
alter table item drop key sitemap;

alter table item add column `import_source_id` tinyint(3) unsigned NOT NULL after is_in_stock;
alter table import_source add column delivery_notes tinytext after `source_column`;
alter table import_source add column sales_notes tinytext after `delivery_notes`;
alter table import_source add column `type` tinyint(1) unsigned NOT NULL default '0' after sales_notes;

/** 05.05.2018 */

alter table import_source change `comment` tech_notes tinytext after sales_notes;
alter table import_source drop column `source_column`;
alter table import_source drop column `langs`;


alter table import_source change sales_notes sales_notes text;
alter table import_source change delivery_notes delivery_notes text;
alter table import_source change tech_notes tech_notes text;
alter table item add unique key uk_image(image);

alter table item add column image_count tinyint(1) unsigned null after `image`;

/** 02.05.2018 */

alter table category change rating rating int unsigned not null default 0;

/** 22.09.2018 */

CREATE TABLE `item_redirect` (
  `item_redirect_id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `id_from` int(10) unsigned NOT NULL,
  `id_to` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_redirect_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/** 25.09.2018 */
drop table `page_catalog`;
rename table `page_catalog2` to `page_catalog`;
alter table `page_catalog` change `page_catalog2_id` `page_catalog_id` int(11) unsigned NOT NULL AUTO_INCREMENT;

alter table `page_catalog` change `params_hash` `params_hash` char(32) not null;

CREATE TABLE `page_catalog_custom` (
  `page_catalog_custom_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uri_hash` char(32) not null,
  `meta_title` varchar(2048) DEFAULT NULL,
  `meta_description` varchar(4096) DEFAULT NULL,
  `meta_keywords` varchar(2048) DEFAULT NULL,
  `h1` varchar(2048) DEFAULT NULL,
  `body` mediumtext,
  `seo_texts` mediumtext,
  `is_active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`page_catalog_custom_id`),
  UNIQUE KEY `uk_uri` (`uri_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# execute after Catalog::doMigrateCatalogToCustom

alter table `page_catalog` drop key `ix_updated_at`;
alter table `page_catalog` drop key `ix_sitemap`;
alter table `page_catalog` drop column `meta_title`;
alter table `page_catalog` drop column `meta_description`;
alter table `page_catalog` drop column `meta_keywords`;
alter table `page_catalog` drop column `h1`;
alter table `page_catalog` drop column `body`;
alter table `page_catalog` drop column `seo_texts`;
alter table `page_catalog` drop column `created_at`;
alter table `page_catalog` drop column `updated_at`;

/** 06.10.2018 */

alter table `season` drop key `name`;
alter table `season` drop key `uri`;
alter table `season` change `name` `name` tinytext not null;
alter table `season` change `uri` `uri` tinytext not null;
alter table `season` add column `name_hash` char(32) NOT NULL after `name`;
update `season` set `name_hash` = md5(`name`);
alter table `season` add UNIQUE KEY `name_hash` (`name_hash`);

alter table `color` drop key `name`;
alter table `color` drop key `uri`;
alter table `color` change `name` `name` tinytext not null;
alter table `color` change `uri` `uri` tinytext not null;
alter table `color` change `title_name` `name_multiply` tinytext null;
alter table `color` add column `name_hash` char(32) NOT NULL after `name`;
update `color` set `name_hash` = md5(`name`);
alter table `color` add UNIQUE KEY `name_hash` (`name_hash`);
alter table `color` change `hex` `hex` char(6) null;

alter table `material` drop key `name`;
alter table `material` drop key `uri`;
alter table `material` add column `name_hash` char(32) NOT NULL after `name`;
update `material` set `name_hash` = md5(`name`);
alter table `material` add UNIQUE KEY `name_hash` (`name_hash`);

alter table `size` drop key `name`;
alter table `size` drop key `uri`;
alter table `size` add column `name_hash` char(32) NOT NULL after `name`;
update `size` set `name_hash` = md5(`name`);
alter table `size` add UNIQUE KEY `name_hash` (`name_hash`);

/** 16.10.2018 */

alter table `item_archive` add column `import_source_id` tinyint(3) unsigned not null after `is_in_stock`;
alter table `item_archive` add column `image_count` tinyint(1) unsigned null after `image`;

/** 30.12.2018 */

alter table `page_catalog_custom` add column `params_hash` char(32) not null after `uri_hash`;
update `page_catalog_custom` inner join `page_catalog` using(`uri_hash`) set `page_catalog_custom`.`params_hash`=`page_catalog`.`params_hash`;
alter table `page_catalog_custom` drop column `uri_hash`;
alter table `page_catalog_custom` add unique key `uk_params` (`params_hash`);