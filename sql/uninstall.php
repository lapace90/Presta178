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

$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pfi_import_feed_catfields_csv`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pfi_import_feed_fields_csv`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pfi_import_feedprice_fields_csv`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pfi_import_log`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pfi_import_tempdata_csv`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pfi_import_update`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pfi_import_priceupdate`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pfi_product_apisync`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pfi_customer_apisync`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pfi_order_apisync`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pfi_import_pricetempdata_csv`;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
