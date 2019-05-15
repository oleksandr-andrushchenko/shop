/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `brand`
--

DROP TABLE IF EXISTS `brand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brand` (
  `brand_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `uri` varchar(128) NOT NULL,
  `image` char(32) DEFAULT NULL,
  `no_index` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `rating` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`brand_id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `uri` (`uri`),
  KEY `rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Страницы всех брендов';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `brand_term`
--

DROP TABLE IF EXISTS `brand_term`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brand_term` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `brand_id` smallint(5) unsigned NOT NULL,
  `value` tinytext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `brand_term_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brand` (`brand_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `category`
--

DROP TABLE IF EXISTS `category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category` (
  `category_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `title` varchar(256) DEFAULT NULL,
  `breadcrumb` varchar(128) DEFAULT NULL,
  `uri` varchar(128) NOT NULL,
  `image` char(32) DEFAULT NULL,
  `rating` int(10) unsigned NOT NULL DEFAULT 0,
  `is_leaf` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `parent_category_id` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `uri` (`uri`),
  KEY `parent_category_id` (`parent_category_id`),
  CONSTRAINT `category_ibfk_1` FOREIGN KEY (`parent_category_id`) REFERENCES `category` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Страницы всех категорий';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `category_alias`
--

DROP TABLE IF EXISTS `category_alias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category_alias` (
  `category_alias_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` smallint(5) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `title` varchar(256) DEFAULT NULL,
  `breadcrumb` varchar(128) DEFAULT NULL,
  `uri` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_alias_id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `uri` (`uri`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `category_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `category_child`
--

DROP TABLE IF EXISTS `category_child`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category_child` (
  `category_id` int(11) NOT NULL,
  `child_category_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `category_entity`
--

DROP TABLE IF EXISTS `category_entity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category_entity` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` smallint(5) unsigned NOT NULL DEFAULT 0,
  `value` tinytext DEFAULT NULL,
  `value_hash` char(32) NOT NULL,
  `count` mediumint(8) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_id` (`category_id`,`value_hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `color`
--

DROP TABLE IF EXISTS `color`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `color` (
  `color_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` tinytext NOT NULL,
  `name_hash` char(32) NOT NULL,
  `uri` tinytext NOT NULL,
  `name_multiply` tinytext DEFAULT NULL,
  `hex` char(6) DEFAULT NULL,
  `rating` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`color_id`),
  UNIQUE KEY `name_hash` (`name_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Страницы всех цветов';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `color_term`
--

DROP TABLE IF EXISTS `color_term`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `color_term` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `color_id` smallint(5) unsigned NOT NULL,
  `lang` char(2) NOT NULL DEFAULT 'ru',
  `value` tinytext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `color_id` (`color_id`),
  CONSTRAINT `color_term_ibfk_1` FOREIGN KEY (`color_id`) REFERENCES `color` (`color_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `country`
--

DROP TABLE IF EXISTS `country`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `country` (
  `country_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `uri` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`country_id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `uri` (`uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Страна производитель';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `country_term`
--

DROP TABLE IF EXISTS `country_term`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `country_term` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` tinyint(3) unsigned NOT NULL,
  `lang` char(2) NOT NULL DEFAULT 'ru',
  `value` tinytext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `country_id` (`country_id`),
  CONSTRAINT `country_term_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `country` (`country_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `import_history`
--

DROP TABLE IF EXISTS `import_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_history` (
  `import_history_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `import_source_id` int(3) unsigned NOT NULL,
  `file_unique_hash` varchar(32) DEFAULT NULL,
  `is_ok` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`import_history_id`),
  KEY `import_source_id` (`import_source_id`),
  CONSTRAINT `import_history_ibfk_1` FOREIGN KEY (`import_source_id`) REFERENCES `import_source` (`import_source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `import_source`
--

DROP TABLE IF EXISTS `import_source`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_source` (
  `import_source_id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `file` varchar(256) NOT NULL,
  `file_filter` longtext DEFAULT NULL,
  `file_mapping` longtext DEFAULT NULL,
  `uri` varchar(256) DEFAULT NULL,
  `is_cron` tinyint(1) NOT NULL DEFAULT 0,
  `vendor_id` tinyint(3) unsigned DEFAULT NULL,
  `class_name` varchar(128) NOT NULL,
  `delivery_notes` text DEFAULT NULL,
  `sales_notes` text DEFAULT NULL,
  `tech_notes` text DEFAULT NULL,
  `type` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`import_source_id`),
  UNIQUE KEY `name` (`name`),
  KEY `vendor_id` (`vendor_id`),
  CONSTRAINT `import_source_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`vendor_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item`
--

DROP TABLE IF EXISTS `item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item` (
  `item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(512) NOT NULL,
  `upc` varchar(32) NOT NULL,
  `partner_link` varchar(1024) DEFAULT NULL,
  `image` char(32) NOT NULL,
  `image_count` tinyint(1) unsigned DEFAULT NULL,
  `price` decimal(8,2) DEFAULT NULL,
  `old_price` decimal(8,2) DEFAULT NULL,
  `entity` tinytext NOT NULL,
  `description` text DEFAULT NULL,
  `rating` smallint(5) unsigned NOT NULL DEFAULT 0,
  `category_id` smallint(5) unsigned DEFAULT NULL,
  `brand_id` smallint(5) unsigned DEFAULT NULL,
  `country_id` tinyint(3) unsigned DEFAULT NULL,
  `vendor_id` tinyint(3) unsigned DEFAULT NULL,
  `is_sport` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `is_size_plus` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `is_in_stock` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `import_source_id` tinyint(3) unsigned NOT NULL,
  `order_desc_rating` int(11) NOT NULL DEFAULT 0,
  `order_asc_price` int(11) NOT NULL DEFAULT 0,
  `order_desc_price` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `uk_image` (`image`),
  UNIQUE KEY `uk_vendor_upc` (`vendor_id`,`upc`),
  KEY `ix_fix_category_vendor` (`category_id`,`vendor_id`,`created_at`,`updated_at`),
  KEY `ix_fix_vendor` (`vendor_id`,`created_at`,`updated_at`),
  KEY `ix_order_desc_rating` (`order_desc_rating`),
  KEY `ix_order_asc_price` (`order_asc_price`),
  KEY `ix_order_desc_price` (`order_desc_price`),
  KEY `ix_catalog_category_brand` (`is_sport`,`is_size_plus`,`category_id`,`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Страницы всех предложений';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_archive`
--

DROP TABLE IF EXISTS `item_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_archive` (
  `item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(512) NOT NULL,
  `upc` varchar(32) NOT NULL,
  `partner_link` varchar(1024) DEFAULT NULL,
  `image` char(32) NOT NULL,
  `image_count` tinyint(1) unsigned DEFAULT NULL,
  `price` decimal(8,2) DEFAULT NULL,
  `old_price` decimal(8,2) DEFAULT NULL,
  `entity` tinytext NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` smallint(5) unsigned DEFAULT NULL,
  `brand_id` smallint(5) unsigned DEFAULT NULL,
  `country_id` tinyint(3) unsigned DEFAULT NULL,
  `vendor_id` tinyint(3) unsigned DEFAULT NULL,
  `is_sport` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `is_size_plus` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `is_in_stock` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `import_source_id` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `tag_id` varchar(128) DEFAULT NULL,
  `color_id` varchar(128) DEFAULT NULL,
  `material_id` varchar(128) DEFAULT NULL,
  `size_id` varchar(128) DEFAULT NULL,
  `season_id` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_color`
--

DROP TABLE IF EXISTS `item_color`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_color` (
  `item_id` int(10) unsigned NOT NULL,
  `color_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`item_id`,`color_id`),
  KEY `ix_color` (`color_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_material`
--

DROP TABLE IF EXISTS `item_material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_material` (
  `item_id` int(10) unsigned NOT NULL,
  `material_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`item_id`,`material_id`),
  KEY `ix_material` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_redirect`
--

DROP TABLE IF EXISTS `item_redirect`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_redirect` (
  `item_redirect_id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `id_from` int(10) unsigned NOT NULL,
  `id_to` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`item_redirect_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_season`
--

DROP TABLE IF EXISTS `item_season`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_season` (
  `item_id` int(10) unsigned NOT NULL,
  `season_id` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`item_id`,`season_id`),
  KEY `ix_season` (`season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_size`
--

DROP TABLE IF EXISTS `item_size`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_size` (
  `item_id` int(10) unsigned NOT NULL,
  `size_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`item_id`,`size_id`),
  KEY `ix_size` (`size_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_tag`
--

DROP TABLE IF EXISTS `item_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_tag` (
  `item_id` int(10) unsigned NOT NULL,
  `tag_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`item_id`,`tag_id`),
  KEY `ix_tag` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `material`
--

DROP TABLE IF EXISTS `material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `material` (
  `material_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` tinytext NOT NULL,
  `name_hash` char(32) NOT NULL,
  `uri` tinytext NOT NULL,
  `rating` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`material_id`),
  UNIQUE KEY `name_hash` (`name_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `material_term`
--

DROP TABLE IF EXISTS `material_term`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `material_term` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `material_id` smallint(5) unsigned NOT NULL,
  `lang` char(2) NOT NULL DEFAULT 'ru',
  `value` tinytext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `material_id` (`material_id`),
  CONSTRAINT `material_term_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `material` (`material_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `page_catalog`
--

DROP TABLE IF EXISTS `page_catalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_catalog` (
  `page_catalog_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(512) NOT NULL,
  `uri` varchar(512) NOT NULL,
  `uri_hash` char(32) NOT NULL,
  `params` varchar(2048) DEFAULT NULL,
  `params_hash` char(32) NOT NULL,
  `meta` varchar(2048) DEFAULT NULL,
  PRIMARY KEY (`page_catalog_id`),
  UNIQUE KEY `uk_uri` (`uri_hash`),
  KEY `ix_params` (`params_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `page_catalog_custom`
--

DROP TABLE IF EXISTS `page_catalog_custom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_catalog_custom` (
  `page_catalog_custom_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `params_hash` char(32) NOT NULL,
  `meta_title` varchar(2048) DEFAULT NULL,
  `meta_description` varchar(4096) DEFAULT NULL,
  `meta_keywords` varchar(2048) DEFAULT NULL,
  `h1` varchar(2048) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `seo_texts` mediumtext DEFAULT NULL,
  `is_active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`page_catalog_custom_id`),
  UNIQUE KEY `uk_params` (`params_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `season`
--

DROP TABLE IF EXISTS `season`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `season` (
  `season_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` tinytext NOT NULL,
  `name_hash` char(32) NOT NULL,
  `uri` tinytext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`season_id`),
  UNIQUE KEY `name_hash` (`name_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Сезонность';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `season_term`
--

DROP TABLE IF EXISTS `season_term`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `season_term` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `season_id` tinyint(3) unsigned NOT NULL,
  `lang` char(2) NOT NULL DEFAULT 'ru',
  `value` tinytext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `season_id` (`season_id`),
  CONSTRAINT `season_term_ibfk_1` FOREIGN KEY (`season_id`) REFERENCES `season` (`season_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `size`
--

DROP TABLE IF EXISTS `size`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `size` (
  `size_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` tinytext NOT NULL,
  `name_hash` char(32) NOT NULL,
  `uri` tinytext NOT NULL,
  `rating` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`size_id`),
  UNIQUE KEY `name_hash` (`name_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `size_term`
--

DROP TABLE IF EXISTS `size_term`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `size_term` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `size_id` smallint(5) unsigned NOT NULL,
  `lang` char(2) NOT NULL DEFAULT 'ru',
  `value` tinytext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `size_id` (`size_id`),
  CONSTRAINT `size_term_ibfk_1` FOREIGN KEY (`size_id`) REFERENCES `size` (`size_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock`
--

DROP TABLE IF EXISTS `stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock` (
  `stock_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(512) NOT NULL,
  `images` text DEFAULT NULL,
  `link` varchar(512) NOT NULL,
  `is_active` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`stock_id`),
  KEY `is_active` (`is_active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tag`
--

DROP TABLE IF EXISTS `tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tag` (
  `tag_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` tinytext NOT NULL,
  `name_hash` char(32) NOT NULL,
  `uri` tinytext NOT NULL,
  `rating` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY `name_hash` (`name_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tag_term`
--

DROP TABLE IF EXISTS `tag_term`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tag_term` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `tag_id` smallint(5) unsigned NOT NULL,
  `lang` char(2) NOT NULL DEFAULT 'ru',
  `value` tinytext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `tag_term_ibfk_1` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`tag_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendor`
--

DROP TABLE IF EXISTS `vendor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor` (
  `vendor_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `partner_link` varchar(1024) DEFAULT NULL,
  `uri` varchar(256) NOT NULL,
  `image` char(32) DEFAULT NULL,
  `class_name` text DEFAULT NULL,
  `is_active` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`vendor_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2019-05-14 21:40:05


insert into page_regular(`key`,meta_title,meta_description,meta_keywords,menu_title,h1,description,is_menu,rating,created_at) values('catalog','Каталог мета заголовок','Каталог мета описание','{site},каталог','каталог','Каталог','Описание каталога',1,99,NOW());