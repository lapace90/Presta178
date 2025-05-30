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
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/pfproductimporter.php';

if (Tools::getIsset('secure_key')) {
    $softwareid = Configuration::get('PI_SOFTWAREID');
    if (!empty($softwareid) && $softwareid === Tools::getValue('secure_key')) {
        $module = new PfProductImporter();
        if (Tools::getValue('action') && Tools::getValue('action') == 'count') {
            echo $module->countimport();
        } elseif (Tools::getValue('action') && Tools::getValue('action') == 'import') {
            echo $module->finalimport();
        }
    }
}
