<?php
/**
 * 2018 - Definima
 *
 * DISCLAIMER
 *
 * @author    Definima <remi@definima.com>
 * @copyright 2018 Definima
 * @license   https://www.tgm-commerce.fr/
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_product_apisync` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `system_productid` int(7) NOT NULL,
  `api_productid` int(7) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_customer_apisync` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `system_customerid` int(7) NOT NULL,
  `api_customerid` int(7) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_order_apisync` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `system_orderid` int(7) NOT NULL,
  `api_orderid` int(7) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_import_feed_catfields_csv` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `system_catid` int(6) NOT NULL,
  `xml_catid` varchar(255) NOT NULL,
  `create_new` varchar(5) NOT NULL,
  `feed_id` int(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_import_feed_fields_csv` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `system_field` varchar(255) NOT NULL,
  `xml_field` varchar(255) NOT NULL,
  `feed_id` int(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'INSERT INTO `' . _DB_PREFIX_ . 'pfi_import_feed_fields_csv` (system_field, xml_field, feed_id) VALUES 
  ("reference", "codeArt", 1),
  ("name", "des", 1),
  ("id_category_default", "fam", 1),
  ("id_tax_rules_group", "tTVA", 1),
  ("price", "pvTTC", 1),
  ("weight", "poids", 1),
  ("ecotax", "deee", 1),
  ("wholesale_price", "pRachat", 1),
  ("condition", "neuf", 1),
  ("manufacturer", "four", 1),
  ("quantity", "stock", 1),
  ("available_date", "dArr", 1),
  ("image_url", "images", 1),
  ("combination_reference", "codeDeclinaison", 1);';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_import_log` (
  `vdate` datetime NOT NULL,
  `reference` varchar(255) NOT NULL,
  `product_error` varchar(255) NOT NULL,
  `table_id` int(9) NOT NULL
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_import_tempdata_csv` (
  `feed_id` int(6) NOT NULL,
  `col1` varchar(800) NOT NULL,
  `col2` varchar(800) NOT NULL,
  `col3` varchar(800) NOT NULL,
  `col4` varchar(800) NOT NULL,
  `col5` varchar(800) NOT NULL,
  `col6` varchar(800) NOT NULL,
  `col7` varchar(800) NOT NULL,
  `col8` varchar(800) NOT NULL,
  `col9` varchar(800) NOT NULL,
  `col10` varchar(800) NOT NULL,
  `col11` varchar(800) NOT NULL,
  `col12` varchar(800) NOT NULL,
  `col13` varchar(800) NOT NULL,
  `col14` varchar(800) NOT NULL,
  `col15` varchar(800) NOT NULL,
  `col16` varchar(800) NOT NULL,
  `col17` varchar(800) NOT NULL,
  `col18` varchar(800) NOT NULL,
  `col19` varchar(800) NOT NULL
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_import_pricetempdata_csv` (
  `feed_id` int(6) NOT NULL,
  `col1` varchar(300) NOT NULL,
  `col2` varchar(300) NOT NULL,
  `col3` varchar(300) NOT NULL,
  `col4` varchar(300) NOT NULL,
  `col5` varchar(300) NOT NULL,
  `col6` varchar(300) NOT NULL,
  `col7` varchar(300) NOT NULL
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_import_update` (
  `table_id` int(9) NOT NULL,
  `vdate` datetime NOT NULL,
  `log_id` int(9) NOT NULL,
  `feedid` int(6) NOT NULL,
  `total_processed_products` int(9) NOT NULL,
  `total_active_products` int(9) NOT NULL,
  `total_inactive_products` int(9) NOT NULL,
  `added` int(9) NOT NULL,
  `total_local_products` int(9) NOT NULL,
  `reference` varchar(300) default NULL,
  `sync_reference` varchar(100) NOT NULL,
  PRIMARY KEY  (`table_id`)
) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_import_feedprice_fields_csv` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `system_field` varchar(255) NOT NULL,
  `xml_field` varchar(255) NOT NULL,
  `feed_id` int(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_import_pricetempdata_csv` (
  `feed_id` int(6) NOT NULL,
  `col1` varchar(300) NOT NULL,
  `col2` varchar(300) NOT NULL,
  `col3` varchar(300) NOT NULL,
  `col4` varchar(300) NOT NULL,
  `col5` varchar(300) NOT NULL,
  `col6` varchar(300) NOT NULL,
  `col7` varchar(300) NOT NULL
) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_import_priceupdate` (
  `table_id` int(9) NOT NULL,
  `vdate` datetime NOT NULL,
  `log_id` int(9) NOT NULL,
  `feedid` int(6) NOT NULL,
  `total_processed_products` int(9) NOT NULL,
  `total_active_products` int(9) NOT NULL,
  `total_inactive_products` int(9) NOT NULL,
  `added` int(9) NOT NULL,
  `total_local_products` int(9) NOT NULL,
  `reference` varchar(300) default NULL,
  `sync_reference` varchar(100) NOT NULL,
  PRIMARY KEY  (`table_id`)
) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pfi_images_apisync` (
  `system_productid` int(11) DEFAULT NULL,
  `system_combinationid` int(11) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `system_imageid` int(11) DEFAULT NULL
) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
