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

// if (Tools::getIsset('secure_key')) {
//     $softwareid = Configuration::get('PI_SOFTWAREID');
//     if (Tools::getValue('action') && Tools::getValue('action') == 'count') {
//         // Faire la préparation ET le comptage d'un coup
//         $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
//         $softwareid = Configuration::get('PI_SOFTWAREID');
//         $sc = new SoapClient($feedurl, ['keep_alive' => false]);
//         $timestamp_old = '2020-01-01 00:00:00';
//         $art = $sc->getNewArticles($softwareid, $timestamp_old, 0);
//         if (!empty($art->article)) {
//             $articles = is_array($art->article) ? $art->article : [$art->article];
//             $module->saveTestTmpData(0, 0, $articles);
//             $module_>countimport($articles);
//             echo count($articles);
//         } else {
//             echo 0;
//         }
//     } elseif ($_POST['action'] == 'import') {
//         $offset = (int)$_POST['Submitoffset'];
//         $limit = (int)$_POST['Submitlimit'];
//         echo $module->finalimport($limit, $offset, 1);
//         exit;
//     }
// }
