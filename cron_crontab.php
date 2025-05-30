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
include dirname(__FILE__) . '/../../config/config.inc.php';
include dirname(__FILE__) . '/../../init.php';
require_once _PS_MODULE_DIR_ . 'pfproductimporter/vccsv.php';
require_once _PS_MODULE_DIR_ . 'pfproductimporter/class/customer.php';
require_once _PS_MODULE_DIR_ . 'pfproductimporter/class/product.php';
require_once _PS_MODULE_DIR_ . 'pfproductimporter/class/order.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

if (Tools::getIsset('secure_key')) {
    $softwareid = Configuration::get('PI_SOFTWAREID');
    if (!empty($softwareid) && $softwareid === Tools::getValue('secure_key')) {
        /* SI LA TACHE CRON EST AUTORISEE */
        if (Configuration::get('PI_CRON_TASK') == 1) {
            date_default_timezone_set('Europe/Paris');
            $debut = time();
            /* RECUPERATION DE L'AUTORISATION DE L'IMPORTATION CLIENTS / PRODUITS / DECLINAISONS */
            $allow_productimport = Configuration::get('PI_ALLOW_PRODUCTIMPORT');
            $allow_combinationimport = Configuration::get('PI_ALLOW_COMBINATIONIMPORT');
            $allow_customerimport = Configuration::get('PI_ALLOW_CUSTOMERIMPORT');
            $lastcron = date('Y-m-d H:i:s');
            $pfproductimporter = Module::getInstanceByName('pfproductimporter');
            $output = '<u>EXECUTION CRON v' . $pfproductimporter->version
                    . ' sur Prestashop v' . _PS_VERSION_
                    . ' depuis ' . $_SERVER['REMOTE_ADDR']
                    . ' (Last: ' . Configuration::get('PI_LAST_CRON') . ')</u>\n';
            if ($allow_productimport == 1) {
                // DELETE ARTICLES
                $output .= $pfproductimporter->deleteArticle();
                // UPDATE ARTICLES
                $pfproductimporter->cronjobimportsavetempdata();
                $output .= $pfproductimporter->cronjobfinalimport();
            }
            // UPDATE STOCKS
            $output .= $pfproductimporter->stockSyncCron();
            // UPDATE LOTS
            if ($allow_productimport == 1) {
                $output .= $pfproductimporter->cronjobimportlot();
            }
            // UPDATE SOLDES
            if ((Configuration::get('PI_ALLOW_PRODUCTSALESIMPORT') == '1')
                && in_array(date('H'), ['08', '00'])) {
                $output .= $pfproductimporter->salesSyncCron();
            }
            if ($allow_customerimport == 1) {
                // DELETE CLIENTS
                $output .= $pfproductimporter->deleteCustomer();
                // UPDATE CLIENTS
                $output .= CustomerVccsv::importCustomer();
            }
            // UPDATE COMMANDES
            if (Configuration::get('PI_UPDATE_ORDER_STATUS') == '1') {
                $output .= $pfproductimporter->orderStatusSyncCron();
            }
            // UPDATE LAST CRON
            $lastcron = strtotime($lastcron);
            Configuration::updateValue('PI_LAST_CRON', date('Y-m-d H:i:s', $lastcron));
            // BENCHMARK
            $fin = time();
            $pfproductimporter->mylog($output . "\n" . ($fin - $debut) . 's.');
        }
    }
}
