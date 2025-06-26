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
require_once dirname(__FILE__) . '/../../init.php';
@ini_set('max_execution_time', 0);
ini_set('memory_limit', '2048M');
/**
 * Vccsv class.
 */
class Vccsv
{
    public $errors = [];

    /**
     * __construct function.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * getxiProductFields function.
     *
     * @static
     *
     * @return void
     */
    public static function getxiProductFields()
    {
        $id_lang = Context::getContext()->cookie->id_lang;
        $ordBy = Tools::getProductsOrder('by', 'name');
        $ordWay = Tools::getProductsOrder('way', 'asc');
        $products = Product::getProducts((int) $id_lang, 0, 1, $ordBy, $ordWay);
        if (!$products) {
            $fields = Product::$definition['fields'];
            $sysproductfields = array_keys($fields);
            array_push($sysproductfields, 'quantity');

            return $sysproductfields;
        } else {
            $sysproductfields = array_keys($products[0]);

            return $sysproductfields;
        }
    }

    /**
     * buildMappingCategoryForm function.
     *
     * @static
     *
     * @return void
     *              mappingcategoryform
     */
    public static function buildMappingCategoryForm($_this)
    {
        $feedurl = Tools::getValue('vcfeedurl');
        if ($feedurl == '') {
            // Récupérer depuis la config si pas en POST
            $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
            if (empty($feedurl)) {
                exit('feed not found');
            }
        }
        $feedid = 1;

        // Cette partie devrait être déplacée dans saveFieldMappings() :
        if (Tools::getValue('fld_map')) {
            // empty and store the product field mappings data  in import_feed_fields_csv;
            $qry = 'delete from  `' . _DB_PREFIX_ . 'pfi_import_feed_fields_csv` ';
            Db::getInstance()->execute($qry);
            $i = 0;
            $fld_map = Tools::getValue('fld_map');
            foreach ($fld_map as $val) {
                ++$i;
                $system_field = Tools::getValue('sel_' . $i);
                $xml_field = $val;
                if ($system_field != 'Ignore Field') {
                    $sql = 'insert into `' . _DB_PREFIX_ .
                        'pfi_import_feed_fields_csv` (`system_field`, `xml_field`, `feed_id`) values ("' .
                        pSQL($system_field) . '", "' . pSQL($xml_field) . '", ' . (int) $feedid . ' )  ';
                    Db::getInstance()->execute($sql);
                    // if ($system_field == 'id_category_default')
                    // $catfield = $xml_field;
                }
            }
        }

        // Build category mapping form
        $id_lang = Context::getContext()->cookie->id_lang;
        $categories = Category::getCategories((int) $id_lang, true);

        $catdata = '';
        $cats = self::recurseCategory2($categories, $categories[1][2], 2, 2, $catdata, $_this);

        // Get xml categories into array
        $fam = Vccsv::getXmlfield('id_category_default');
        if (empty($fam)) {
            $fam = 'fam';
        }
        $final_products_arr = self::getCategoriesFromFeed($feedurl, $fam, false);

        $existing_mappings = array();
        $sql = 'SELECT `xml_catid`, `system_catid` FROM `' . _DB_PREFIX_ . 'pfi_import_feed_catfields_csv` WHERE `feed_id` = ' . (int)$feedid;
        $mappings = Db::getInstance()->executeS($sql);

        foreach ($mappings as $mapping) {
            $existing_mappings[$mapping['xml_catid']] = $mapping['system_catid'];
        }

        $options = $cats;
        // $formatted_url = strstr($_SERVER['REQUEST_URI'], '&vc_mode= ', true);
        // $vc_redirect = ($formatted_url != '') ? $formatted_url : $_SERVER['REQUEST_URI'];

        $_this->smarty->assign([
            'final_products_arr' => $final_products_arr,
            'feedurl' => $feedurl,
            'feedid' => $feedid,
            'options' => $options,
            'systemctid' => 'CATEGORY',
            // 'vc_redirect' => $vc_redirect,
            'base_url' => __PS_BASE_URI__,
            'cats' => $cats,
            'existing_mappings' => $existing_mappings,
        ]);

        return $_this->display(_PS_MODULE_DIR_ . 'pfproductimporter/pfproductimporter.php', 'mappingcategoryform.tpl');
    }

    public static function getFeedByVal($xml_catid, $feed_id)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pfi_import_feed_catfields_csv` 
            WHERE `xml_catid` = "' . pSQL($xml_catid) . '" 
            AND `feed_id` = ' . (int)$feed_id;

        return Db::getInstance()->getRow($sql);
    }

    /**
     * openCsvFile function.
     *
     * @static
     *
     * @param mixed $file
     *
     * @return void
     */
    public static function openCsvFile($file)
    {
        $handle = fopen($file, 'r');
        if (!$handle) {
            Tools::displayError('Cannot read the .CSV file');
        }

        self::rewindBomAware($handle);
        $skip = 0;
        $separator = '\t';
        for ($i = 0; $i < $skip; ++$i) {
            fgetcsv($handle, 0, $separator);
        }

        return $handle;
    }

    /**
     * rewindBomAware function.
     *
     * @static
     *
     * @param mixed $handle
     *
     * @return void
     */
    protected static function rewindBomAware($handle)
    {
        // A rewind wrapper that skip BOM signature wrongly
        rewind($handle);
        // if (($bom = fread($handle, 3)) != '\xEF\xBB\xBF')
        // rewind($handle);
    }

    /**
     * closeCsvFile function.
     *
     * @param mixed $handle
     *
     * @return void
     */
    protected function closeCsvFile($handle)
    {
        fclose($handle);
    }

    /*
    *  Store the category  field mappings data and  display confirmation message;
    */
    /**
     * saveCategoryMappings function.
     *
     * @static
     *
     * @return void
     *              savecategorymappings
     */
    public static function saveCategoryMappings($_this, $isAjax = false)
    {
        // Récupérer les données depuis le formulaire
        $feed_id = Tools::getValue('feedid');
        $feedurl = Tools::getValue('feedurl');
        $fixcategory = Tools::getValue('selfixcategory');

        // Vider la table avant de sauvegarder
        $qry = 'DELETE FROM `' . _DB_PREFIX_ . 'pfi_import_feed_catfields_csv`';
        Db::getInstance()->execute($qry);

        // Récupérer les tableaux depuis le formulaire
        $cat_map = Tools::getValue('cat_map'); // Catégories du flux
        $system_cat = Tools::getValue('system_cat'); // Catégories PrestaShop sélectionnées
        $is_new = Tools::getValue('is_new'); // Indicateur de nouvelle catégorie

        // Compteur pour le nombre de mappings sauvegardés
        $saved_count = 0;

        if (is_array($cat_map) && is_array($system_cat)) {
            foreach ($cat_map as $index => $flux_category) {
                // Récupérer la catégorie PrestaShop correspondante
                $prestashop_category_id = isset($system_cat[$index]) ? (int)$system_cat[$index] : 0;

                // Ne sauvegarder que si une catégorie PrestaShop est sélectionnée
                if ($prestashop_category_id > 0) {
                    // Déterminer si c'est une nouvelle catégorie (pour createCategoryMappings)
                    $create_new = (isset($is_new[$index]) && $is_new[$index] == 1) ? 1 : 0;

                    $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'pfi_import_feed_catfields_csv` 
                        (`system_catid`, `xml_catid`, `feed_id`, `create_new`) 
                        VALUES (' . (int)$prestashop_category_id . ', 
                                "' . pSQL($flux_category) . '", 
                                ' . (int)$feed_id . ', 
                                ' . (int)$create_new . ')';

                    if (Db::getInstance()->execute($sql)) {
                        $saved_count++;
                    }
                }
            }
        }

        // Message de confirmation
        $message = sprintf($_this->l('%d category mappings saved successfully'), $saved_count);

        // Retour direct pour AJAX
        if ($isAjax) {
            return $message;
        }

        // Traitement normal pour non-AJAX
        // $formatted_url = strstr($_SERVER['REQUEST_URI'], '&vc_mode= ', true);
        // $vc_redirect = ($formatted_url != '') ? $formatted_url : $_SERVER['REQUEST_URI'];

        $_this->smarty->assign([
            'feed_id' => $feed_id,
            'vcfeedurl' => $feedurl,
            'fixcategory' => $fixcategory,
            // 'vc_redirect' => $vc_redirect,
            'base_url' => __PS_BASE_URI__,
            'message' => $message,
            'saved_count' => $saved_count
        ]);

        // return $_this->display(_PS_MODULE_DIR_ . 'pfproductimporter/pfproductimporter.php', 'savecategorymappings.tpl');
        return $message;
    }

    /**
     * createcategory function.
     *
     * @return void
     */
    public static function createCategoryMappings()
    {
        $default_language_id = (int) Configuration::get('PS_LANG_DEFAULT');
        $languages = Language::getLanguages();

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT system_catid, xml_catid, create_new FROM `' .
            _DB_PREFIX_ . 'pfi_import_feed_catfields_csv` WHERE create_new = 1');
        foreach ($result as $val) {
            $value = $val['xml_catid'];
            // if (is_numeric(Tools::substr($value, 0, 1))) {
            //     $value = 'Marque '.$value;
            // }
            $category = Category::searchByName($default_language_id, trim($value), true);

            if (!$category['id_category'] && !is_numeric($value)) {
                // echo $value.'\n';

                $value = str_replace('>', '-', $value);
                $value = str_replace('<', '-', $value);
                $value = str_replace('#', '-', $value);
                $value = str_replace('=', '-', $value);
                $value = str_replace(';', '-', $value);
                $value = str_replace('{', '-', $value);
                $value = str_replace('}', '-', $value);

                $catnamearray = [];
                $catlinkarray = [];
                $category_to_create = new Category();
                foreach ($languages as $lang) {
                    $catnamearray[$lang['id_lang']] = $value;
                }

                $category_to_create->name = $catnamearray;
                $category_to_create->active = 1;
                $category_to_create->id_parent = $val['system_catid'];
                $category_link_rewrite = Tools::link_rewrite($category_to_create->name[$default_language_id]);
                foreach ($languages as $lang) {
                    $catlinkarray[$lang['id_lang']] = $category_link_rewrite;
                }

                $category_to_create->link_rewrite = $catlinkarray;

                if (!empty($category_to_create->name)) {
                    $category_to_create->add();
                }
            }
        }
    }

    public static function getSystemField($key)
    {
        $row = Db::getInstance()->getRow('SELECT system_field  FROM `' .
            _DB_PREFIX_ . 'pfi_import_feedprice_fields_csv` p WHERE p.xml_field = "' . pSQL($key) . '"');

        return $row;
    }

    public static function saveFieldMappings($_this)
    {
        // Vider la table d'abord
        $qry = 'DELETE FROM `' . _DB_PREFIX_ . 'pfi_import_feed_fields_csv` WHERE feed_id = 1';
        Db::getInstance()->execute($qry);

        $fld_map = Tools::getValue('fld_map');
        $i = 0;

        if (!$fld_map || !is_array($fld_map)) {
            return 'Aucun mapping fourni';
        }

        foreach ($fld_map as $xml_field) {
            ++$i;
            if (!Tools::getIsset('sel_' . $i)) {
                continue;
            }

            $system_field = Tools::getValue('sel_' . $i);

            // Ajouter les mappings fixes
            self::insertFixedFieldMappingsIfMissing();

            if ($system_field !== 'Ignore Field' && !empty($xml_field)) {
                $qry = 'INSERT INTO `' . _DB_PREFIX_ . 'pfi_import_feed_fields_csv` 
                    (`xml_field`, `system_field`, `feed_id`) 
                    VALUES ("' . pSQL($xml_field) . '", "' . pSQL($system_field) . '", 1)
                    ON DUPLICATE KEY UPDATE xml_field = VALUES(xml_field)';
                Db::getInstance()->execute($qry);
            }
        }

        return 'Mappings sauvegardés';
    }

    public static function insertFixedFieldMappingsIfMissing()
    {
        $requiredMappings = [
            'reference' => 'codeArt',
            'name' => 'des',
            'id_category_default' => 'fam',
            'wholesale_price' => 'paHT',
            'id_tax_rules_group' => 'tTVA',
            'price' => 'pvTTC',
            'weight' => 'poids',
            'taille_1' => 'taille',
            'couleur_2' => 'couleur',
            'quantity' => 'stock',
            'ecotax' => 'deee',
            'available_date' => 'dArr',
            'condition' => 'neuf',
            'product_url' => 'link',
            'manufacturer' => 'four',
            'image_url' => 'images',
            'combination_reference' => 'codeDeclinaison',
            'description' => 'description',
        ];

        // Récupérer toutes les lignes existantes pour le feed_id = 1
        $existingRows = Db::getInstance()->executeS(
            'SELECT system_field, xml_field FROM `' . _DB_PREFIX_ . 'pfi_import_feed_fields_csv` WHERE feed_id = 1'
        );

        // Créer un tableau des paires existantes
        $existingPairs = [];
        foreach ($existingRows as $row) {
            $existingPairs[$row['system_field'] . '|' . $row['xml_field']] = true;
        }

        // Insérer uniquement les paires manquantes
        foreach ($requiredMappings as $system_field => $xml_field) {
            $key = $system_field . '|' . $xml_field;
            if (!isset($existingPairs[$key])) {
                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'pfi_import_feed_fields_csv`
                (`xml_field`, `system_field`, `feed_id`) VALUES ("' . pSQL($xml_field) . '", "' . pSQL($system_field) . '", 1)');
            }
        }
    }

    /**
     * buildMappingFieldsForm function.
     *
     * @static
     *
     * @return void
     *              buildmappingfieldsform
     * 
     * On s'en sert pas pour l'instant
     */
    public static function buildMappingFieldsForm($_this)
    {
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $timestamp = date('Y-m-d H:i:s', mktime(0, 0, 0, date('n'), date('j'), date('Y')));

        // Champs produits de base
        $productfields = self::getxiProductFields();
        if (!is_array($productfields)) {
            $productfields = [];
        }
        $productfields[] = 'image_url';
        $productfields[] = 'product_url';
        $productfields[] = 'manufacturer';
        $productfields[] = 'available_date';
        $productfields[] = 'combination_reference';

        $mylist = [
            'name',
            'id_category_default',
            'price',
            'wholesale_price',
            'reference',
            'ean13',
            'upc',
            'active',
            'description',
            'description_short',
            'image_url',
            'quantity',
            'available',
            'product_url',
            'manufacturer',
            'retail_price_new',
            'ecotax',
            'weight',
            'condition',
            'id_tax_rules_group',
            'available_date',
            'combination_reference',
        ];

        $newproductfields = [];
        $newproductfields[] = 'Ignore Field';
        foreach ($productfields as $pr) {
            if (in_array($pr, $mylist)) {
                $newproductfields[] = $pr;
            }
        }

        $raw_products_arr = [];
        if (Tools::substr($feedurl, -5) == '.wsdl' || Tools::substr($feedurl, -4) == '.csv') {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);

            $art = $sc->getNewArticles($softwareid, $timestamp, 0);

            if (!empty($art->article)) {
                $raw_products_arr = [];
                if (is_array($art->article)) {
                    $articles = $art->article;
                } else {
                    $articles = [$art->article];
                }
                foreach ($articles as $col) {
                    $raw_products_arr = (array) $col;
                    break;
                }
                $tmp_arr = [];
                foreach ($raw_products_arr as $K => $t) {
                    if ($t == 0) {
                        $tmp_arr[$K] = $K;
                    } else {
                        $tmp_arr[$K] = $K;
                    }
                }

                $raw_products_arr = $tmp_arr;
            } else {
                return $_this->display(
                    _PS_MODULE_DIR_ . 'pfproductimporter/pfproductimporter.php',
                    'buildmappingfieldsform_error.tpl'
                );
            }
        }
        // $formatted_url = strstr($_SERVER['REQUEST_URI'], '&vc_mode= ', true);
        // $vc_redirect = ($formatted_url != '') ? $formatted_url : $_SERVER['REQUEST_URI'];

        /**
         * @edit Definima
         * Liste des groupes d'attributs pour permettre de mapper les champs "taille" et "couleur"
         */
        $attrgrp = ['Ignore Field'];
        if (Combination::isFeatureActive()) {
            $liste_attrgrp = AttributeGroup::getAttributesGroups(Context::getContext()->cookie->id_lang);
            foreach ($liste_attrgrp as $attr) {
                $attrgrp[$attr['id_attribute_group']] = $attr['name'];
            }
        }

        self::insertFixedFieldMappingsIfMissing();

        $_this->smarty->assign([
            // 'vc_redirect' => $vc_redirect,
            'newproductfields' => $newproductfields,
            'raw_products_arr' => $raw_products_arr,
            'feedurl' => $feedurl,
            'base_url' => __PS_BASE_URI__,
            'attrgrp' => $attrgrp,
        ]);

        return $_this->display(
            _PS_MODULE_DIR_ . 'pfproductimporter/pfproductimporter.php',
            'buildmappingfieldsform.tpl'
        );
    }

    public static function getfields($key)
    {
        $row = Db::getInstance()->getRow('SELECT system_field  FROM `' . _DB_PREFIX_ .
            'pfi_import_feed_fields_csv` p WHERE p.xml_field = "' . pSQL($key) . '"');

        return $row;
    }

    public static function getXmlfield($key)
    {
        $row = Db::getInstance()->getValue('SELECT xml_field  FROM `' . _DB_PREFIX_ .
            'pfi_import_feed_fields_csv` p WHERE p.system_field = "' . pSQL($key) . '"');

        return $row;
    }

    /**
     * utf8EncodeArray function.
     *
     * @static
     *
     * @param mixed $array
     *
     * @return void
     */
    public static function utf8EncodeArray($array)
    {
        return $array;
    }

    /**
     * getFieldsFromFeed function.
     *
     * @static
     *
     * @param mixed $feedurl
     *
     * @return void
     */
    public static function getFieldsFromFeed($feedurl)
    {
        $raw_products_arr = array();

        if (Tools::substr($feedurl, -5) == '.wsdl' || Tools::substr($feedurl, -4) == '.csv') {
            try {
                $softwareid = Configuration::get('PI_SOFTWAREID');
                $timestamp = date('Y-m-d H:i:s', mktime(0, 0, 0, date('n'), date('j'), date('Y')));
                $sc = new SoapClient($feedurl, array('keep_alive' => false));
                $art = $sc->getNewArticles($softwareid, $timestamp, 0);

                if (!empty($art->article)) {
                    if (is_array($art->article)) {
                        $articles = $art->article;
                    } else {
                        $articles = array($art->article);
                    }

                    foreach ($articles as $col) {
                        $raw_products_arr = (array) $col;
                        break;
                    }

                    $tmp_arr = array();
                    foreach ($raw_products_arr as $K => $t) {
                        $tmp_arr[$K] = $K;
                    }
                    $raw_products_arr = $tmp_arr;
                }
            } catch (Exception $e) {
                // En cas d'erreur, utiliser des valeurs par défaut
                $raw_products_arr = array(
                    'codeArt' => 'codeArt',
                    'taille' => 'taille',
                    'couleur' => 'couleur',
                    'dArr' => 'dArr',
                    'description' => 'description',
                );
            }
        }

        return $raw_products_arr;
    }

    /**
     * getCategoriesFromFeed function.
     *
     * @static
     *
     * @param mixed $feedurl
     * @param mixed $field
     * @param mixed $fieldproduct
     *
     * @return void
     */
    public static function getCategoriesFromFeed($feedurl, $field, $fieldproduct)
    {
        $cat_array = [];
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $sc = new SoapClient($feedurl, ['keep_alive' => false]);
        switch ($field) {
            case 'rayon':
                $method = 'getAllRayons';
                break;

            case 'sFam':
                $method = 'getAllSousFamilles';
                break;

            case 'type':
                $method = 'getAllTypes';
                break;

            default:
                $method = 'getAllFamilles';
        }
        $res = $sc->$method($softwareid);
        if (!empty($res->poste)) {
            if (is_array($res->poste)) {
                $res = $res->poste;
            } else {
                $res = [$res->poste];
            }
            foreach ($res as $col) {
                $cat_array[] = $col;
            }
        }

        return $cat_array;
    }

    /**
     * arraySearchKeyCategory function.
     *
     * @static
     *
     * @param mixed $needle_key
     * @param mixed $array
     *
     * @return void
     */
    public static function arraySearchKeyCategory($needle_key, $array)
    {
        $t = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array2 = $value;
                foreach ($array2 as $key2 => $value2) {
                    if ($key2 == $needle_key) {
                        if (!in_array($value2, $t)) {
                            $t[] = $value2;
                        }
                        break;
                    }
                }
            } else {
                if ($key == $needle_key) {
                    $t[] = $value;
                } else {
                    continue;
                }
            }
        }

        return $t;
    }

    /**
     * arraySearchKey function.
     *
     * @static
     *
     * @param mixed $needle_key
     * @param mixed $array
     *
     * @return void
     */
    public static function arraySearchKey($needle_key, $array)
    {
        foreach ($array as $key => $value) {
            if ($key == $needle_key) {
                return $value;
            }
            if (is_array($value)) {
                if (($result = self::arraySearchKey($needle_key, $value)) !== false) {
                    return $result;
                }
            }
        }

        return false;
    }

    /**
     * recurseCategory2 function.
     *
     * @static
     *
     * @param mixed $categories
     * @param mixed $current
     * @param int $id_category (default: 1)
     * @param int $id_selected (default: 1)
     * @param mixed &$data
     *
     * @return void
     */
    public static function recurseCategory2($categories, $current, $id_category, $id_selected, &$data, $_this)
    {
        $output = array();

        // Ajouter la catégorie courante
        if (isset($current['infos'])) {
            $output[] = array(
                'infos' => array(
                    'id_category' => $id_category,
                    'name' => $current['infos']['name'],
                    'level_depth' => $current['infos']['level_depth']
                )
            );
        }

        // Parcourir les sous-catégories
        if (isset($categories[$id_category])) {
            foreach ($categories[$id_category] as $key => $category) {
                if ($key != 'infos') {
                    $children = self::recurseCategory2(
                        $categories,
                        $category,
                        $key,
                        $id_selected,
                        $data,
                        $_this
                    );
                    $output = array_merge($output, $children);
                }
            }
        }

        return $output;
    }

    public static function recurseCategory2option($id_category, $option, $_this)
    {
        $_this->smarty->assign(['id_category' => $id_category]);
        $_this->smarty->assign(['option' => $option]);

        return $_this->display(_PS_MODULE_DIR_ . 'pfproductimporter/pfproductimporter.php', 'selects.tpl');
    }

    /**
     * l function.
     *
     * @static
     *
     * @param mixed $string
     *
     * @return void
     */
    public static function l($string)
    {
        return Translate::getModuleTranslation('pfproductimporter', $string, 'vccsv');
    }

    /**
     * Log Errors
     *
     * @static
     *
     * @param mixed $exception
     *
     * @return void
     */
    public static function logError($exception)
    {
        return 'Error : <span style="color: red">' . $exception->getMessage() . '</span>\n' .
            'File : ' . $exception->getFile() . '\n' .
            'Line : ' . $exception->getLine() . '\n\n';
    }
}
