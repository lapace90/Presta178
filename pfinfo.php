<?php
/**
 * 2021 - TGMultimedia
 *
 * DISCLAIMER
 *
 * @author    TGMultimedia
 * @copyright 2021 TGMultimedia
 * @license   https://www.tgm-commerce.fr/
 */
include dirname(__FILE__) . '/../../config/config.inc.php';
include dirname(__FILE__) . '/../../init.php';
if (!defined('_PS_VERSION_')) {
    exit;
}
if (Tools::getIsset('tgm')) {
    phpinfo();
}
