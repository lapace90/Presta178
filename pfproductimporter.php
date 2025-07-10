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
require_once dirname(__FILE__) . '/vccsv.php';
require_once dirname(__FILE__) . '/class/customer.php';
require_once dirname(__FILE__) . '/class/order.php';
require_once dirname(__FILE__) . '/class/product.php';
require_once dirname(__FILE__) . '/class/combination.php';

if (!defined('_CAN_LOAD_FILES_')) {
    exit('_CANNOT_LOAD_FILES_');
}
if (!defined('_PS_VERSION_')) {
    exit;
}
/**
 * pfProductImporter class.
 */
class PfProductImporter extends Module
{
    private $errors = [];
    public $path;
    public $arrcat = [];
    public $produt_def;
    public $smarty;

    /**
     * __construct function.
     */
    public function __construct()
    {
        $this->name = 'pfproductimporter';
        $this->tab = 'migration_tools';
        $this->version = '2.6.2';
        $this->author = 'Definima/TGM';
        $this->ps_versions_compliancy = [
            'min' => '1.6.0.4',
            'max' => '8.99.99',
        ];
        parent::__construct();
        $this->displayName = $this->l('Rezomatic Synchronization');
        $this->description = $this->l('Rezomatic Synchronization management');
        $this->path = $this->_path;
        $this->module_key = 'd471e4caa738fe49f0d11c5f1f514145';
        $this->bootstrap = true;

        // Ensure context and smarty are initialized
        if (!isset($this->context) || !$this->context) {
            $this->context = Context::getContext();
        }
        if (!isset($this->smarty) || !$this->smarty) {
            $this->smarty = $this->context->smarty;
        }
    }

    /**
     * install function.
     *
     * @return bool
     */
    public function install()
    {
        include dirname(__FILE__) . '/sql/install.php';

        return parent::install()
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionCustomerAccountAdd')
            && $this->registerHook('actionCustomerAccountUpdate')
            && $this->registerHook('actionObjectCustomerUpdateAfter')
            && $this->registerHook('actionAuthentication')
            && $this->registerHook('actionProductAdd')
            && $this->registerHook('displayHeader')
            && $this->registerHook('actionOrderStatusUpdate')
            && $this->registerHook('actionProductUpdate')
            && $this->registerHook('displayShoppingCart')
            // && $this->registerHook('actionProductDelete')
            // && $this->registerHook('displayBackOfficeHeader') // @edit Definima  Gestion des déclinaisons @deprecated
        ;
    }

    /**
     * uninstall function.
     *
     * @return string
     */
    public function uninstall()
    {
        include dirname(__FILE__) . '/sql/uninstall.php';

        return parent::uninstall();
    }

    /**
     * getContent function.
     *
     * @return void
     */
    public function getContent()
    {
        $output = '';

        @ini_set('max_execution_time', 0);
        define('UNFRIENDLY_ERROR', false);
        ini_set('memory_limit', '2048M');

        $this->context->smarty->assign('secure_key', Configuration::get('PI_SOFTWAREID'));
        $this->context->smarty->assign('pi_softwareid', Configuration::get('PI_SOFTWAREID'));

        if (Tools::isSubmit('direct_import_now')) {
            $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
            $softwareid = Configuration::get('PI_SOFTWAREID');

            try {
                $sc = new SoapClient($feedurl, ['keep_alive' => false]);

                // UTILISER DATE ANCIENNE pour récupérer TOUS les articles
                $timestamp_old = '2020-01-01 00:00:00';
                $art = $sc->getNewArticles($softwareid, $timestamp_old, 0);

                if (!empty($art->article)) {
                    // LANCER L'IMPORT DIRECT avec ces données
                    $articles = is_array($art->article) ? $art->article : [$art->article];

                    // $output = "<h3>IMPORT EN COURS</h3>";
                    // $output .= count($articles) . " articles à traiter...<br><br>";

                    // Sauver dans la table temporaire
                    $this->saveTestTmpData(0, 0, $articles);

                    // Lancer le comptage des articles
                    $this->countimport();

                    // Lancer l'import final
                    $result = $this->finalimport('', '', 0);

                    return $output . $result;
                } else {
                    return "Aucun article trouvé";
                }
            } catch (Exception $e) {
                return "Erreur : " . $e->getMessage();
            }
        }

        if (Tools::isSubmit('SubmitSaveMainSettings')) {
            // 1. Save Main Settings
            if ($this->saveMainSettingsForm()) {
                $output = $this->displayConfirmation($this->l('Settings updated'));
                if (Tools::getValue('PI_ALLOW_PRODUCTIMPORT')) {
                    // 2. Fields Mapping
                    // $output .= Vccsv::buildMappingFieldsForm($this);
                    $output .= $this->renderMainSettingsForm();
                } elseif (Tools::getValue('PI_ALLOW_PRODUCTEXPORT')) {
                    // 2. Export all prodcuts ?
                    // $output .= $this->renderExportCatalogForm();
                    $output .= $this->displayConfirmation(
                        'L\'exportation des produits est activée. Vous pouvez maintenant exporter votre catalogue.'
                    );
                    $output .= $this->renderMainSettingsForm();
                } else {
                    $output .= $this->renderMainSettingsForm();
                }
            } else {
                $output = $this->displayError(
                    $this->l('No valid connection to TGM Web Service. Please verify your settings.')
                );
                $output .= $this->renderMainSettingsForm();
            }
            return $output;
        } elseif (Tools::isSubmit('SubmitSaveFields')) {
            $result = Vccsv::saveFieldMappings($this);
            if ($result) {
                $output = $this->displayConfirmation($result);
            } else {
                $output = $this->displayError('Erreur lors de la sauvegarde');
            }
            $output .= $this->renderMainSettingsForm();
        } elseif (Tools::isSubmit('Submitmapcategory')) {
            // 3. Category Mapping
            $output = Vccsv::buildMappingCategoryForm($this);
        } elseif (Tools::isSubmit('Submitimportpreview')) {
            // 4. Save Category Mapping
            Vccsv::createCategoryMappings();
            $output = Vccsv::saveCategoryMappings($this);
            $output .= $this->renderMainSettingsForm();
            return $output;
        } elseif (Tools::isSubmit('Submitimportprocess')) {
            // 5. Import CSV catalog
            $Submitlimit = Tools::getValue('Submitlimit');
            $id = 0;
            $output .= $this->saveTestTmpData($id, $Submitlimit);
            return $output;
        } elseif (Tools::isSubmit('exportallproduct')) {
            // Export Catalog
            $output = ProductVccsv::exportAll();
            if ($output) {
                $output = $this->displayConfirmation('Exportation du catalogue terminée.');
            } else {
                $output = $this->displayError('Erreur lors de l\'exportation du catalogue : ');
            }
            $output .= $this->renderMainSettingsForm();

            return $output;
        } elseif (Tools::isSubmit('SubmitExportorder')) {
            if ($this->saveMainSettingsForm()) {
                $output = $this->displayConfirmation('Paramètres des commandes enregistrés avec succès.');
            }
            $output .= $this->renderMainSettingsForm();
            // TODO : Export d'une commande, à supprimer ?
            // $order_id = Tools::getValue('txtorderid');
            // $output .= OrderVccsv::orderSync($order_id);
            return $output;
        } elseif (Tools::isSubmit('SubmitImportcustomer')) {
            // TODO : Bloc à supprimer ?
            // $output = CustomerVccsv::importCustomer();
            if (!empty($output)) {
                $output = $this->displayError($output);
            } else {
                $output = $this->displayConfirmation($this->l('Paramètres des clients enregistrés avec succès.'));
            }
            return $output;
        } elseif (Tools::isSubmit('Submitdirectimport')) {
            // TODO : Bloc à supprimer ?
            $Submitlimit = 2000;
            $id = 2;
            $this->saveTestTmpData($id, $Submitlimit);
            $this->finalimport($Submitlimit, '');
        } elseif (Tools::isSubmit('Submitimportfromlastupdated')) {
            // TODO : Bloc à supprimer ?
            $Submitlimit = 100;
            $sync_reference = Db::getInstance()->getValue('select sync_reference from `' . _DB_PREFIX_ .
                'pfi_import_update`  where feedid = 1 ');
            if (!$sync_reference || $sync_reference == '00000') {
                $sync_reference = '';
            } else {
                $Submitoffset = $sync_reference;
            }
            $this->finalimport($Submitlimit, $Submitoffset);
        } elseif (Tools::isSubmit('SubmitExportcustomer')) {
            // TODO : Bloc à supprimer ?
            $customerid = Tools::getValue('txtcustomerid');
            $output = CustomerVccsv::customerSync($customerid);

            return $output;
        } elseif (Tools::isSubmit('SubmitExportproduct')) {
            // TODO : Bloc à supprimer ?
            $productid = Tools::getValue('txtproductid');
            $output = ProductVccsv::productSync($productid);
            $output = $this->renderMainSettingsForm();

            return $output;
        } elseif (Tools::isSubmit('importallproduct')) {
            // TODO : Bloc à supprimer ?
            $Submitlimit = '';
            $id = 2;
            $this->saveTestTmpData($id, $Submitlimit);
            $this->finalimport($Submitlimit, '');
            $output = 'Import complete. ' . $this->importationlink();

            return $output;
        } elseif (Tools::getValue('simple_import') || Tools::isSubmit('submitfromlast')) {
            // TODO : Import tache cron ?
            $Submitlimit = Tools::getValue('Submitlimit');
            $Submitoffset = Tools::getValue('Submitoffset');
            if (Tools::isSubmit('submitfromlast')) {
                $Submitlimit = (int) Tools::getValue('productlimit');
                $Submitoffset = Tools::getValue('lastref');
            }
            $output = $this->finalimport($Submitlimit, $Submitoffset);

            return $output;
        } elseif (Tools::isSubmit('submitgotomain')) {
            // TODO : Import tache cron ?
            $url = AdminController::$currentIndex . '&modulename=' . $this->name . '&configure=' . $this->name .
                '&token=' . Tools::getAdminTokenLite('AdminModules') . '&tab_module=payments_gateways';
            Tools::redirectAdmin($url);
        } else {
            // 0. Main settings
            $output = $this->renderMainSettingsForm();
        }
        if (Tools::isSubmit('clear_filter')) {
            // Vider les variables et recharger la page logs
            $url = AdminController::$currentIndex . '&configure=' . $this->name .
                '&active_tab=logs&token=' . Tools::getAdminTokenLite('AdminModules');
            Tools::redirectAdmin($url);
        }
        return $output;
    }

    /**
     * hookDisplayHeader function.
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookDisplayHeader($params)
    {
        $output = ProductVccsv::stockSync();
        $this->mylog($output);
    }

    /**
     * hookActionProductAdd function.
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionProductAdd($params)
    {
        $id_product = $params['id_product'];
        $output = ProductVccsv::productSync($id_product);
        $this->mylog($output);
    }

    /**
     * hookActionProductUpdate function.
     *
     * @edit Definima
     * La création ou la mise à jour se fait sur un produit déjà existant
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionProductUpdate($params)
    {
        $id_product = $params['id_product'];
        $output = ProductVccsv::productSync($id_product);
        // Export des declinaisons
        $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
        $allow_productexport = Configuration::get('PI_ALLOW_PRODUCTEXPORT');

        if ($allow_productexport == 1) {
            if ($this->isPrestashop16()) {
                $output .= CombinationVccsv::syncCombination(
                    $id_product,
                    Tools::getValue('attribute_' . $reference_field), // vérifier ici
                    Tools::getValue('attribute_ean13'),
                    Tools::getValue('attribute_wholesale_price'),
                    Tools::getValue('attribute_price_impact'),
                    Tools::getValue('attribute_priceTI'),
                    Tools::getValue('attribute_weight'),
                    Tools::getValue('attribute_combination_list')
                );
            }
        }
        $this->mylog($output);
    }

    /**
     * @edit Definima
     *
     * @see $this->hookActionProductUpdate()
     *
     * @param $params
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        // Nothing happen here
    }

    /**
     * hookActionProductDelete function.
     *
     * @param mixed $params
     *
     * @return void
     */
    /*
        public function hookActionProductDelete($params)
        {
            $data = '';
            $id_product = $params['product']->reference;
            $output = ProductVccsv::productDelete($id_product);
            $this->mylog($output);
        }
    */

    /**
     * hookActionCustomerAccountAdd function.
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionCustomerAccountAdd($params)
    {
        $id_customer = $this->context->cookie->id_customer;
        $output = CustomerVccsv::customerSync($id_customer);
        $this->mylog($output);
    }

    /**
     * hookActionCustomerAccountUpdate function.
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionCustomerAccountUpdate($params)
    {
        $id_customer = $this->context->cookie->id_customer;
        $output = CustomerVccsv::customerSync($id_customer);
        $this->mylog($output);
    }

    /**
     * hookActionObjectCustomerUpdateAfter (v1.6)
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionObjectCustomerUpdateAfter($params)
    {
        $output = CustomerVccsv::customerSync($params['object']->id);
        $this->mylog($output);
    }

    /**
     * hookActionAuthentication function.
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionAuthentication($params)
    {
        $id_customer = $this->context->cookie->id_customer;
        $email_customer = $this->context->cookie->email;
        $output = CustomerVccsv::loyaltySync($id_customer, $email_customer);
        $this->mylog($output);
    }

    /**
     * hookActionValidateOrder function.
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionValidateOrder($params)
    {
        $order = $params['order'];
        $output = OrderVccsv::orderSync($order->id);
        $this->mylog($output);
    }

    /**
     * hookActionOrderStatusUpdate function.
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionOrderStatusUpdate($params)
    {
        $order_id = $params['id_order'];
        $order_newstatus = $params['newOrderStatus']->id;
        if ($order_newstatus == 6) {
            $output = OrderVccsv::orderCancel($order_id);
        } else {
            $output = OrderVccsv::orderSync($order_id);
        }
        $this->mylog($output);
    }

    /**
     * productImportCron function.
     *
     * @return void
     */
    public function productImportCron()
    {
        $allow_productimport = Configuration::get('PI_ALLOW_PRODUCTIMPORT');
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $languages = Language::getLanguages();
        $output = '';
        // $timestamp  = date('Y-m-d H:i:s', mktime(0, 0, 0, date("n"), (date('j')-1), date("Y")));
        $timestamp = Configuration::get('PI_LAST_CRON');
        if ($allow_productimport == 1) {
            $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
            $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
            try {
                $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                $products = $sc->getUpdatedArticles($softwareid, $timestamp);
                if ($products) {
                    $languages = Language::getLanguages();
                    $columns = $this->getxmlfields();
                    if (empty($columns)) {
                        $output .= 'Please configure the field mappings in the module backend\n';
                        exit($output);
                    }
                    foreach ($products->article as $col) {
                        // get fields mappings
                        $raw_products_arr = (array) $col;
                        $reference = $raw_products_arr[$columns[Configuration::get('PI_PRODUCT_REFERENCE')]];
                        $name = $raw_products_arr[$columns['name']];
                        // quantity
                        $pdv = Configuration::get('SYNC_STOCK_PDV');
                        if (!empty($pdv)) {
                            // Prise en compte uniquement des stocks du PDV renseigné
                            $pdv = explode(',', $pdv);
                            $pdv = array_map('strtolower', $pdv);
                            $pdv = array_map('trim', $pdv);
                            $quantity = 0;
                            $stock_pdvs = $sc->getStocksFromCode($softwareid, $reference);
                            if (is_array($stock_pdvs->stockPdv)) {
                                $stocks = $stock_pdvs->stockPdv;
                            } else {
                                $stocks = [$stock_pdvs->stockPdv];
                            }
                            foreach ($stocks as $st) {
                                if (in_array($st->idPdv, $pdv)) {
                                    $quantity += $st->stock;
                                }
                            }
                        } else {
                            $quantity = $raw_products_arr[$columns['quantity']];
                        }
                        $category = $raw_products_arr[$columns['id_category_default']];
                        $description = null;
                        $description_short = null;
                        $row = Db::getInstance()->getRow('SELECT p.id_product FROM `' . _DB_PREFIX_ .
                            'product` p WHERE p.' .
                            Configuration::get('PI_PRODUCT_REFERENCE') . ' = "' . pSQL($reference) . '"');
                        if (!$row) {
                            $output .= 'product : ' . $reference . ' does not exist\n';
                            continue;
                        } else {
                            $output .= 'product : ' . $reference . ' exists\n';
                        }

                        $product_id = $row['id_product'];
                        $product = new Product($product_id);
                        $output .= Configuration::get('PI_PRODUCT_REFERENCE') . ': ' . $reference . ' --- name: ' . $name .
                            ' ---  category:' . $category .
                            ' --- quantity:' . $quantity . ' --- product_id:' . $product_id . '\n';
                        $system_formula_field = Configuration::get('SYNC_CSV_FIELD');
                        $wholesale_price = $raw_products_arr[$columns['wholesale_price']];
                        $price = $raw_products_arr[$columns['price']];
                        $formulaprice = $raw_products_arr[$system_formula_field];
                        $formula_op = Configuration::get('SYNC_CSV_OP');
                        $formula_val = Configuration::get('SYNC_CSV_VAL');
                        $this->savenameanddescription($name, $description, $description_short, $languages, $product);
                        $output .= 'system_formula_field:' . $system_formula_field .
                            ' --- wholesale_price: ' . $wholesale_price . ' --- price: ' . $price .
                            ' ---   quantity:' . $quantity . ' --- formulaprice:' . $formulaprice . '\n';
                        $this->saveprices($wholesale_price, $price, $formulaprice, $formula_op, $formula_val, $product);
                        $product->category = [$category];
                        if (!empty($product->category)) {
                            $outp = $this->setproductcategory($product, $id_lang, $languages);
                            $output .= $outp;
                        }
                        StockAvailable::setQuantity((int) $product->id, 0, $quantity, $id_lang);
                        $output .= 'Product ' . $reference . ' Updated\n';
                        exit($output);
                    }
                }
            } catch (SoapFault $exception) {
                $output = Vccsv::logError($exception);
            }
        } else {
            $output = 'Product import not allowed\n';
        }
        $output = $this->importcron($output);

        return $output;
    }

    public function importcron($output)
    {
        $this->smarty->assign([
            'output' => $output,
        ]);

        return $this->display(__FILE__, 'importcron.tpl');
    }

    /**
     * stockSyncCron function.
     *
     * @static
     *
     * @return void
     */
    public static function stockSyncCron()
    {
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $multistock = Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT');
        $timestamp = Configuration::get('PI_LAST_CRON');
        $pdv = Configuration::get('SYNC_STOCK_PDV');
        $isStockGlobal = empty(trim($pdv));
        $pdv = explode(',', $pdv);
        $pdv = array_map('strtolower', $pdv);
        $pdv = array_map('trim', $pdv);
        $output = '';
        if ($multistock != 1) {
            try {
                $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                $res = $sc->getUpdatedStocksFromTimeStamp($softwareid, $timestamp);
                if (!empty($res->stocks)) {
                    if (is_array($res->stocks)) {
                        $arr = $res->stocks;
                    } else {
                        $arr = [$res->stocks];
                    }

                    // Pour chaque article retourné
                    foreach ($arr as $art) {
                        if (is_array($art->stockPdv)) {
                            $stocks = $art->stockPdv;
                        } else {
                            $stocks = [$art->stockPdv];
                        }
                        // Ajout du stock de chaque pdv pris en compte
                        $stock = 0;
                        foreach ($stocks as $st) {
                            if ($isStockGlobal || in_array($st->idPdv, $pdv)) {
                                $stock += $st->stock;
                            }
                        }
                        // Sauvegarde du nouveau stock
                        $reference = $art->codeArt;
                        $id_product = ProductVccsv::getProductIdByRefRezomatic($reference);
                        if ($id_product && is_numeric($id_product)) {
                            $stock_available = StockAvailable::getQuantityAvailableByProduct($id_product);
                            $commandecours = $sc->getCommandeEnCours($softwareid, $reference);
                            $new_stock = $stock - $commandecours;
                            // $new_stock = ($new_stock < 0) ? 0 : $new_stock;
                            if (StockAvailable::setQuantity($id_product, 0, $new_stock) === false) {
                                $output .= 'Quantities update error for ' . $id_product . ' ' . $reference . ' : ' .
                                    $stock_available . ' -> ' . $new_stock . '\n';
                            } else {
                                $output .= 'Quantities update for ' . $reference . ' : ' .
                                    $stock_available . ' -> ' . $new_stock . '\n';
                            }
                        }

                        /**
                         * @edit Definima
                         * Récupère les déclinaisons qui pourraient avoir cette référence
                         */
                        $combinations = CombinationVccsv::getCombinationsByReference(
                            $reference,
                            Configuration::get('PI_PRODUCT_REFERENCE')
                        );

                        if ($combinations) {
                            foreach ($combinations as $c) {
                                $stock_available = StockAvailable::getQuantityAvailableByProduct(
                                    $c['id_product'],
                                    $c['id_product_attribute']
                                );
                                $commandecours = $sc->getCommandeEnCours($softwareid, $reference);
                                $new_stock = $stock - $commandecours;
                                // $new_stock = ($new_stock < 0) ? 0 : $new_stock;
                                if (
                                    StockAvailable::setQuantity(
                                        $c['id_product'],
                                        $c['id_product_attribute'],
                                        $new_stock
                                    ) === false
                                ) {
                                    $output .= 'Quantities update error for combination ' . $c['id_product'] . ' ' .
                                        $reference . ' : ' . $stock_available . ' -> ' . $new_stock . '\n';
                                } else {
                                    $output .= 'Quantities update for combination ' . $reference . ' : ' .
                                        $stock_available . ' -> ' . $new_stock . '\n';
                                }
                            }
                        }
                    }
                }
            } catch (SoapFault $exception) {
                $output .= Vccsv::logError($exception);
            }

            return $output;
        }
    }

    /**
     * deleteArticle function.
     *
     * @return void
     */
    public function deleteArticle()
    {
        // Si l'import des produits n'est pas activé, pas de suppression
        $allow_productimport = Configuration::get('PI_ALLOW_PRODUCTIMPORT');
        if ($allow_productimport != 1) {
            return '';
        }

        $softwareid = Configuration::get('PI_SOFTWAREID');
        $output = '';
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        // $timestamp  = date('Y-m-d H:i:s', mktime(0, 0, 0, date("n"), (date('j')-1), date("Y")));
        $timestamp = Configuration::get('PI_LAST_CRON');
        try {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            $codeArts = $sc->getDeletedArticles($softwareid, $timestamp);
            if (isset($codeArts->codeArt)) {
                if (is_array($codeArts->codeArt)) {
                    $codeArts = $codeArts->codeArt;
                } else {
                    $codeArts = [$codeArts->codeArt];
                }
                foreach ($codeArts as $codeArt) {
                    $id_product = ProductVccsv::getProductIdByRefRezomatic($codeArt);
                    if ($id_product && is_numeric($id_product)) {
                        $product = new Product($id_product);
                        if ($product && $product->delete()) {
                            $output .= 'Article deleted:' . $this->openb() . $codeArt . $this->clouseb() . '\n';
                        }
                    }

                    /**
                     * @edit Definima
                     * Récupère les déclinaisons qui pourraient avoir cette référence
                     */
                    $combinations = CombinationVccsv::getCombinationsByReference(
                        $codeArt,
                        Configuration::get('PI_PRODUCT_REFERENCE')
                    );

                    if ($combinations) {
                        foreach ($combinations as $c) {
                            $combination = new Combination($c['id_product_attribute']);
                            if ($combination && $combination->delete()) {
                                $output .= 'Declinaison deleted:' . $this->openb() . $codeArt . $this->clouseb() . '\n';
                            }
                        }
                    }
                }
            }
        } catch (SoapFault $exception) {
            $output = Vccsv::logError($exception);
        }
        // $output = $this->deletearticleForm($output);
        return $output;
    }

    public function deletearticleForm($output)
    {
        $this->smarty->assign(['output' => $output]);

        return $this->display(__FILE__, 'deletearticle_form.tpl');
    }

    /**
     * deleteCustomer function.
     *
     * @return void
     */
    public function deleteCustomer()
    {
        /*
         * @edit Definima
         * Ne fait rien car on ne veut pas supprimer les clients de PS s'ils sont supprimés de Rezomatic.
         */
        return '';
    }

    /**
     * mylog function.
     *
     * @param mixed $messagedata
     * @param bool $isHeader
     *
     * @return void
     */
    public static function mylog($messagedata, $isHeader = true)
    {
        $messagedata = trim($messagedata);
        if (empty($messagedata)) {
            return '';
        }
        if ($isHeader) {
            $message = '\n<b>===================================================================================\n';
            $message .= date('Y-m-d H:i:s') . '</b>\n';
        } else {
            $message = '';
        }
        $message .= '<pre>' . $messagedata . '</pre>';
        try {
            $logDir = dirname(__FILE__);
            $logFile = 'logs_rezomatic' . date('Y-m-d') . '.html';
            file_put_contents($logDir . '/' . $logFile, str_replace('\n', '<br />', $message), FILE_APPEND);
        } catch (Exception $e) {
            return 'Error writing logs';
        }
    }

    /**
     * renderMainSettingsForm function.
     *
     * @return HelperForm
     */
    public function renderMainSettingsForm()
    {
        // Récupérer toutes les configurations
        $config_values = array(
            'SYNC_CSV_FEEDURL' => Configuration::get('SYNC_CSV_FEEDURL'),
            'PI_SOFTWAREID' => Configuration::get('PI_SOFTWAREID'),
            'PI_CRON_TASK' => Configuration::get('PI_CRON_TASK'),
            'SYNC_STOCK_PDV' => Configuration::get('SYNC_STOCK_PDV'),
            'PI_ALLOW_PRODUCTIMPORT' => Configuration::get('PI_ALLOW_PRODUCTIMPORT'),
            'PI_ALLOW_PRODUCTIMAGEIMPORT' => Configuration::get('PI_ALLOW_PRODUCTIMAGEIMPORT'),
            'PI_UPDATE_DESIGNATION' => Configuration::get('PI_UPDATE_DESIGNATION'),
            'PI_ALLOW_PRODUCTSALESIMPORT' => Configuration::get('PI_ALLOW_PRODUCTSALESIMPORT'),
            'PI_SYNC_SALES_PDV' => Configuration::get('PI_SYNC_SALES_PDV'),
            'PI_ACTIVE' => Configuration::get('PI_ACTIVE'),
            'PI_ALLOW_PRODUCTEXPORT' => Configuration::get('PI_ALLOW_PRODUCTEXPORT'),
            'PI_ALLOW_CATEGORYEXPORT' => Configuration::get('PI_ALLOW_CATEGORYEXPORT'),
            'PI_PRODUCT_REFERENCE' => Configuration::get('PI_PRODUCT_REFERENCE'),
            'PI_ALLOW_CUSTOMERIMPORT' => Configuration::get('PI_ALLOW_CUSTOMERIMPORT'),
            'PI_ALLOW_CUSTOMEREXPORT' => Configuration::get('PI_ALLOW_CUSTOMEREXPORT'),
            'PI_ALLOW_ORDEREXPORT' => Configuration::get('PI_ALLOW_ORDEREXPORT'),
            'PI_VALID_ORDER_ONLY' => Configuration::get('PI_VALID_ORDER_ONLY'),
            'PI_UPDATE_ORDER_STATUS' => Configuration::get('PI_UPDATE_ORDER_STATUS'),
            'PI_RG1' => Configuration::get('PI_RG1'),
            'PI_RG2' => Configuration::get('PI_RG2'),
            'PI_RG3' => Configuration::get('PI_RG3'),
            'PI_RG4' => Configuration::get('PI_RG4'),
            'PI_RG5' => Configuration::get('PI_RG5'),
            'PI_RG6' => Configuration::get('PI_RG6'),
            'PI_RG7' => Configuration::get('PI_RG7'),
            'PI_RG8' => Configuration::get('PI_RG8'),
            'PI_RG9' => Configuration::get('PI_RG9'),
            'PI_RG10' => Configuration::get('PI_RG10'),
        );


        // // Préparer les logs
        // $today = date('Y-m-d');
        // $logs_today_url = $this->_path . 'logs_rezomatic' . $today . '.html';
        // $logs_today_file = dirname(__FILE__) . '/logs_rezomatic' . $today . '.html';
        // $logs_today_exists = file_exists($logs_today_file);
        // $logs_today_size = $logs_today_exists ? round(filesize($logs_today_file) / 1024, 2) : 0;

        // // Chercher les logs
        // $available_logs = [];
        // $log_files = glob(dirname(__FILE__) . '/logs_rezomatic*.html');

        // if ($log_files) {
        //     foreach ($log_files as $log_file) {
        //         $filename = basename($log_file);
        //         if (preg_match('/logs_rezomatic(\d{4}-\d{2}-\d{2})\.html/', $filename, $matches)) {
        //             $log_date = $matches[1];
        //             $available_logs[] = [
        //                 'date' => $log_date,
        //                 'date_formatted' => date('d/m/Y', strtotime($log_date)),
        //                 'url' => $this->_path . $filename,
        //                 'size_kb' => round(filesize($log_file) / 1024, 2)
        //             ];
        //         }
        //     }

        //     // Trier par date décroissante et limiter à 10
        //     usort($available_logs, function ($a, $b) {
        //         return strcmp($b['date'], $a['date']);
        //     });
        //     $available_logs = array_slice($available_logs, 0, 10);
        // }


        // LOGS - Variables avec pagination et recherche
        $today = date('Y-m-d');
        $logs_today_url = $this->_path . 'logs_rezomatic' . $today . '.html';
        $logs_today_file = dirname(__FILE__) . '/logs_rezomatic' . $today . '.html';
        $logs_today_exists = file_exists($logs_today_file);
        $logs_today_size = $logs_today_exists ? round(filesize($logs_today_file) / 1024, 2) : 0;

        // Paramètres de recherche et pagination
        $search_month = Tools::getValue('search_month', '');
        $search_year = Tools::getValue('search_year', '');
        $current_page = max(1, (int)Tools::getValue('page', 1));
        $logs_per_page = 10;

        // Chercher tous les logs
        $all_logs = [];
        $log_files = glob(dirname(__FILE__) . '/logs_rezomatic*.html');

        if ($log_files) {
            foreach ($log_files as $log_file) {
                $filename = basename($log_file);
                if (preg_match('/logs_rezomatic(\d{4}-\d{2}-\d{2})\.html/', $filename, $matches)) {
                    $log_date = $matches[1];

                    // Filtrage par date
                    $include_log = true;
                    if ($search_year && substr($log_date, 0, 4) !== $search_year) {
                        $include_log = false;
                    }
                    if ($search_month && substr($log_date, 5, 2) !== $search_month) {
                        $include_log = false;
                    }

                    if ($include_log) {
                        $all_logs[] = [
                            'date' => $log_date,
                            'date_formatted' => date('d/m/Y', strtotime($log_date)),
                            'url' => $this->_path . $filename,
                            'size_kb' => round(filesize($log_file) / 1024, 2)
                        ];
                    }
                }
            }

            // Trier par date décroissante
            usort($all_logs, function ($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
        }

        // Calculs de pagination
        $total_logs_found = count($all_logs);
        $total_pages = ceil($total_logs_found / $logs_per_page);
        $current_page = min($current_page, max(1, $total_pages));

        // Logs pour la page actuelle
        $offset = ($current_page - 1) * $logs_per_page;
        $available_logs = array_slice($all_logs, $offset, $logs_per_page);
        $logs_displayed = count($available_logs);

        // Noms des mois
        $months_names = [
            '01' => 'Janvier',
            '02' => 'Février',
            '03' => 'Mars',
            '04' => 'Avril',
            '05' => 'Mai',
            '06' => 'Juin',
            '07' => 'Juillet',
            '08' => 'Août',
            '09' => 'Septembre',
            '10' => 'Octobre',
            '11' => 'Novembre',
            '12' => 'Décembre'
        ];

        // Variables pour le mapping
        $feedid = 1;
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $fixcategory = Tools::getValue('selfixcategory', '');

        // Créer newproductfields (logique de buildMappingFieldsForm)
        $productfields = Vccsv::getxiProductFields();
        if (!is_array($productfields)) {
            $productfields = [];
        }
        $productfields[] = 'image_url';
        $productfields[] = 'product_url';
        $productfields[] = 'manufacturer';
        $productfields[] = 'available_date';
        $productfields[] = 'combination_reference';

        // Préparer les variables pour les logs
        $today = date('Y-m-d');
        $logs_today_url = $this->_path . 'logs_rezomatic' . $today . '.html';
        $logs_today_file = dirname(__FILE__) . '/logs_rezomatic' . $today . '.html';
        $logs_today_exists = file_exists($logs_today_file);

        $mylist = array(
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
            'logs_today_url' => $logs_today_url,
            'logs_today_exists' => $logs_today_exists,
            'logs_today_date' => date('d/m/Y'),
            'module_path' => $this->_path
        );

        $newproductfields = array();
        $newproductfields[] = 'Ignore Field';
        foreach ($productfields as $pr) {
            if (in_array($pr, $mylist)) {
                $newproductfields[] = $pr;
            }
        }

        // Préparer les données pour le mapping si nécessaire
        // Si on a une URL de feed, récupérer les données pour le mapping
        $raw_products_arr = array();
        $final_products_arr = array();

        if ($feedurl && Tools::strlen($feedurl) > 0) {
            // Récupérer les données pour le mapping des champs
            $raw_products_arr = $this->getFieldsFromFeed($feedurl);

            // Récupérer les catégories du feed
            $fam = Vccsv::getXmlfield('id_category_default');
            if (empty($fam)) {

                $fam = 'fam';
            }
            $final_products_arr = Vccsv::getCategoriesFromFeed($feedurl, $fam, false);
        }

        // Groupes d'attributs pour le mapping
        $attrgrp = array('Ignore Field');
        if (Combination::isFeatureActive()) {
            $liste_attrgrp = AttributeGroup::getAttributesGroups(Context::getContext()->cookie->id_lang);
            foreach ($liste_attrgrp as $attr) {
                $attrgrp[$attr['id_attribute_group']] = $attr['name'];
            }
        }

        // Déterminer l'onglet actif
        $active_tab = 'general';
        if (Tools::getValue('active_tab')) {
            $active_tab = Tools::getValue('active_tab');
        }
        // Récupérer les catégories Prestashop
        $cats = Category::getNestedCategories(null, 1, true);

        $categoryOptionsArray = $this->buildCategoryOptionsArray($cats, 0);

        // Précalcul des catégories mappées
        $mappedCategories = [];
        if (!empty($final_products_arr)) {
            foreach ($final_products_arr as $category_name) {
                $row = Vccsv::getFeedByVal($category_name, $feedid);
                $mappedCategories[$category_name] = $row ? $row : null;
            }
        }

        // Préparer toutes les variables pour le template
        $this->context->smarty->assign(array(
            'token' => Tools::getAdminTokenLite('AdminModules'),
            'configure' => $this->name,
            'tab_module' => 'payments_gateways',
            'current_index' => AdminController::$currentIndex,
            'module_dir' => $this->_path,
            'cats' => $cats,
            'categoryOptionsArray' => $categoryOptionsArray,
            'mappedCategories' => $mappedCategories,
            'module_name' => $this->name,
            'ps_version' => _PS_VERSION_,
            'fields_value' => $config_values,
            'languages' => Language::getLanguages(false),
            'default_language' => (int)Configuration::get('PS_LANG_DEFAULT'),
            'last_cron' => Tools::displayDate(Configuration::get('PI_LAST_CRON'), null, true),
            'submit_action' => 'SubmitSaveMainSettings',
            'is_ps15' => $this->isPrestashop15(),
            'active_tab' => $active_tab,
            'form_action' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            'raw_products_arr' => $raw_products_arr,
            'final_products_arr' => $final_products_arr,
            'logs_today_date' => $today,
            'logs_today_date_formatted' => date('d/m/Y'),
            'logs_today_url' => $logs_today_url,
            'logs_today_exists' => $logs_today_exists,
            'logs_today_size' => $logs_today_size,
            'available_logs' => $available_logs,
            'module_path' => $this->_path,
            'logs_today_date' => $today,
            'total_logs_found' => $total_logs_found,
            'logs_displayed' => $logs_displayed,
            'logs_per_page' => $logs_per_page,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'search_month' => $search_month,
            'search_year' => $search_year,
            'months_names' => $months_names,
            'feedid' => $feedid,
            'feedurl' => $feedurl,
            'fixcategory' => $fixcategory,
            'base_url' => __PS_BASE_URI__,
            'secure_key' => Configuration::get('PI_SOFTWAREID'),
            'pi_softwareid' => Configuration::get('PI_SOFTWAREID'),            // Variables pour le mapping des champs
            'newproductfields' => $newproductfields,
            'attrgrp' => $attrgrp,
        ));

        return $this->display(__FILE__, 'views/templates/admin/main_settings.tpl');
    }

    // Construire le tableau d'options de catégories
    public function buildCategoryOptionsArray($categories, $depth = 0)
    {
        $options = [];
        foreach ($categories as $category) {
            $options[] = [
                'id_category' => $category['id_category'],
                'name' => $category['name'],
                'depth' => $depth
            ];
            if (isset($category['children']) && !empty($category['children'])) {
                $children = $this->buildCategoryOptionsArray($category['children'], $depth + 1);
                $options = array_merge($options, $children);
            }
        }
        return $options;
    }

    private function getFieldsFromFeed($feedurl)
    {
        $raw_products_arr = array();

        if (Tools::substr($feedurl, -5) == '.wsdl' || Tools::substr($feedurl, -4) == '.csv') {
            try {
                $softwareid = Configuration::get('PI_SOFTWAREID');
                $timestamp_old = '2020-01-01 00:00:00';
                $sc = new SoapClient($feedurl, array('keep_alive' => false));
                $art = $sc->getNewArticles($softwareid, $timestamp_old, 0);
                // $art = $sc->getNewArticles($softwareid, $timestamp, 0);

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
                // En cas d'erreur, retourner un tableau vide
                $raw_products_arr = array();
            }
        }

        return $raw_products_arr;
    }

    /**
     * saveMainSettingsForm function.
     *
     * @return void
     */
    public function saveMainSettingsForm()
    {
        Configuration::updateValue('SYNC_CSV_FEEDURL', trim(Tools::getValue('SYNC_CSV_FEEDURL')));
        Configuration::updateValue('PI_SOFTWAREID', Tools::getValue('PI_SOFTWAREID'));
        Configuration::updateValue('PI_CRON_TASK', Tools::getValue('PI_CRON_TASK'));
        Configuration::updateValue('SYNC_STOCK_PDV', Tools::getValue('SYNC_STOCK_PDV'));
        Configuration::updateValue('PI_ALLOW_PRODUCTIMPORT', Tools::getValue('PI_ALLOW_PRODUCTIMPORT'));
        Configuration::updateValue('PI_ALLOW_PRODUCTIMAGEIMPORT', Tools::getValue('PI_ALLOW_PRODUCTIMAGEIMPORT'));
        Configuration::updateValue('PI_UPDATE_DESIGNATION', Tools::getValue('PI_UPDATE_DESIGNATION'));
        Configuration::updateValue('PI_ALLOW_PRODUCTSALESIMPORT', Tools::getValue('PI_ALLOW_PRODUCTSALESIMPORT'));
        Configuration::updateValue('PI_SYNC_SALES_PDV', Tools::getValue('PI_SYNC_SALES_PDV'));
        Configuration::updateValue('PI_ACTIVE', Tools::getValue('PI_ACTIVE'));
        Configuration::updateValue('PI_ALLOW_PRODUCTEXPORT', Tools::getValue('PI_ALLOW_PRODUCTEXPORT'));
        Configuration::updateValue('PI_ALLOW_CATEGORYEXPORT', Tools::getValue('PI_ALLOW_CATEGORYEXPORT'));
        Configuration::updateValue('PI_PRODUCT_REFERENCE', Tools::getValue('PI_PRODUCT_REFERENCE'));
        Configuration::updateValue('PI_ALLOW_CUSTOMERIMPORT', Tools::getValue('PI_ALLOW_CUSTOMERIMPORT'));
        Configuration::updateValue('PI_ALLOW_CUSTOMEREXPORT', Tools::getValue('PI_ALLOW_CUSTOMEREXPORT'));
        Configuration::updateValue('PI_ALLOW_ORDEREXPORT', Tools::getValue('PI_ALLOW_ORDEREXPORT'));
        Configuration::updateValue('PI_VALID_ORDER_ONLY', Tools::getValue('PI_VALID_ORDER_ONLY'));
        Configuration::updateValue('PI_UPDATE_ORDER_STATUS', Tools::getValue('PI_UPDATE_ORDER_STATUS'));
        Configuration::updateValue('PI_RG1', Tools::getValue('PI_RG1'));
        Configuration::updateValue('PI_RG2', Tools::getValue('PI_RG2'));
        Configuration::updateValue('PI_RG3', Tools::getValue('PI_RG3'));
        Configuration::updateValue('PI_RG4', Tools::getValue('PI_RG4'));
        Configuration::updateValue('PI_RG5', Tools::getValue('PI_RG5'));
        Configuration::updateValue('PI_RG6', Tools::getValue('PI_RG6'));
        Configuration::updateValue('PI_RG7', Tools::getValue('PI_RG7'));
        Configuration::updateValue('PI_RG8', Tools::getValue('PI_RG8'));
        Configuration::updateValue('PI_RG9', Tools::getValue('PI_RG9'));
        Configuration::updateValue('PI_RG10', Tools::getValue('PI_RG10'));
        Configuration::updateValue('PI_ADD_TASK', 1);
        Configuration::updateValue('PI_EDIT_TASK', 1);
        Configuration::updateValue('PI_LAST_CRON', date('Y-m-d H:i:s'));
        // Test TWS Connection
        try {
            if (Tools::file_get_contents(Configuration::get('SYNC_CSV_FEEDURL'))) {
                $sc = new SoapClient(Configuration::get('SYNC_CSV_FEEDURL'), ['keep_alive' => false]);
                $sc->getVersion(Configuration::get('PI_SOFTWAREID'));

                return true;
            }
        } catch (SoapFault $sf) {
            unset($sf);
        }

        return false;
    }

    /**
     * renderExportCatalogForm function.
     *
     * @return HelperForm
     */
    public function renderExportCatalogForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Export all products to Rezomatic'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [],
                'submit' => [
                    'title' => $this->l('Export all'),
                    'name' => 'exportallproduct',
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];
        $helper = new HelperForm();

        return $helper->generateForm([$fields_form]);
    }

    public function importationlink()
    {
        return $this->display(__FILE__, 'importationlink.tpl');
    }

    public function errorImport()
    {
        return $this->display(__FILE__, 'errorImport.tpl');
    }

    public function hr()
    {
        return $this->display(__FILE__, 'hr.tpl');
    }

    public function openb()
    {
        return $this->display(__FILE__, 'openb.tpl');
    }

    public function clouseb()
    {
        return $this->display(__FILE__, 'clouseb.tpl');
    }

    public function backButton()
    {
        return $this->display(__FILE__, 'back_button.tpl');
    }

    /**
     * cronjobimportsavetempdata function.
     *
     * @return void
     */
    public function cronjobimportsavetempdata()
    {
        // $output = '\n';
        // $output .= $this->l('Cron started');
        $id = 3;
        $this->saveTestTmpData($id, 10000);
    }

    /**
     * cronjobfinalimport function.
     *
     * @return void
     */
    public function cronjobfinalimport()
    {
        $Submitoffset = '00000';
        $Submitlimit = 0;

        return $this->finalimport($Submitlimit, $Submitoffset, 1);
    }

    /**
     * cronjobimportlot function.
     *
     * @return void
     */
    public function cronjobimportlot()
    {
        return ProductVccsv::importLot();
    }

    public function countimport($Submitlimit = '', $Submitoffset = '', $iscron = 0)
    {
        flush();
        ini_set('max_execution_time', 0);
        if (!defined('UNFRIENDLY_ERROR')) {
            define('UNFRIENDLY_ERROR', false);
        }
        ini_set('memory_limit', '2048M');
        ini_set('display_errors', 1);
        $feed_id = 1;
        if ($Submitoffset == '00000') {
            $Submitoffset = '';
        }
        if ($Submitlimit == '' && $Submitoffset == '' && $iscron == 0) {
            $i = $iscron;
        } else {
            $i = 0;
        }
        $tabledata = [];
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('select system_field  from `' . _DB_PREFIX_ .
            'pfi_import_feed_fields_csv` where feed_id=' . (int) $feed_id . ' ORDER BY id');
        foreach ($result as $val) {
            ++$i;
            $tabledata[$val['system_field']] = 'col' . $i;
        }
        $sync_reference = Db::getInstance()->getValue('select sync_reference from `' . _DB_PREFIX_ .
            'pfi_import_update`  where feedid =1 ');
        if (!$sync_reference) {
            Db::getInstance()->execute('Insert into `' . _DB_PREFIX_ .
                'pfi_import_update`(table_id, sync_reference, feedid) values (1, "00000", 1)');
        }

        $final_products_arr = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT COUNT(*) AS total FROM `' .
            _DB_PREFIX_ . 'pfi_import_tempdata_csv` WHERE feed_id=' . (int) $feed_id);

        return $final_products_arr[0]['total'];
    }

    /**
     * finalimport function.
     *
     * @param string $Submitlimit (default: "")
     * @param string $Submitoffset (default: "")
     * @param int $iscron (default: 0)
     *
     * @return void
     */
    public function finalimport($Submitlimit = '', $Submitoffset = '', $iscron = 0)
    {
        flush();
        ini_set('max_execution_time', 0);
        if (!defined('UNFRIENDLY_ERROR')) {
            define('UNFRIENDLY_ERROR', false);
        }
        ini_set('memory_limit', '2048M');
        ini_set('display_errors', 1);
        $output = '';
        $fixcategory = Tools::getValue('fixcategory');
        $default_language_id = Configuration::get('PS_LANG_DEFAULT');
        $feed_id = 1;

        if ($Submitoffset == '00000') {
            $Submitoffset = '';
        }

        $linecountedited = 0;
        $linecountadded = 0;
        $linecounterror = 0;
        $linecount = 0;

        $pr_exists = 0;
        $temp_p_array = [];
        $temp_p_array2 = [];
        $temp_p_array3 = [];
        $temp_p_array4 = [];
        $codeArtUpdated = [];
        $gotit = '0';

        // $formula = Configuration::get('SYNC_CSV_FIELD');
        // $formula_op = Configuration::get('SYNC_CSV_OP');
        // $formula_val = Configuration::get('SYNC_CSV_VAL');
        $enableaddproduct = Configuration::get('PI_ADD_TASK');
        $enableeditproduct = Configuration::get('PI_EDIT_TASK');
        $activeproduct = Configuration::get('PI_ACTIVE');
        $i = 0;
        $colid = 1; // pour éviter de chercher col0
        $tabledata = [];
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('select system_field  from `' . _DB_PREFIX_ .
            'pfi_import_feed_fields_csv` where feed_id=' . (int) $feed_id . ' ORDER BY id');
        foreach ($result as $val) {
            ++$i;
            $tabledata[$val['system_field']] = 'col' . $i;
            if ($val['system_field'] == Configuration::get('PI_PRODUCT_REFERENCE')) {
                $colid = $i;
            }
        }
        $sync_reference = Db::getInstance()->getValue('select sync_reference from `' . _DB_PREFIX_ .
            'pfi_import_update`  where feedid =1 ');
        if (!$sync_reference) {
            Db::getInstance()->execute('Insert into `' . _DB_PREFIX_ .
                'pfi_import_update`(table_id, sync_reference, feedid) values (1, "00000", 1)');
        }

        $final_products_arr = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('select *  from `' . _DB_PREFIX_ .
            'pfi_import_tempdata_csv` where feed_id=' . (int) $feed_id . ' ORDER BY col' . (int) $colid);
        $languages = Language::getLanguages();

        /**
         * @edit Definima
         * Liste des déclinaisons, à traiter après la gestion des articles
         */
        $combinations = [];
        $has_combination_base = false;

        // Récupère les infos pour les attributs
        $attributes = CombinationVccsv::getAttributes($tabledata);

        // Création du tableau des images à importer
        $import_images = [];

        // $count = $final_products_arr;
        // $iterations = 0;
        foreach ($final_products_arr as $feedproduct) {
            try {
                $codeArtUpdated[] = $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]];
                /*
                 * @edit Definima
                 * Gestion des déclinaisons à traiter après les produits
                 */
                // Si codeDeclinaison == reference, c'est le produit de base,
                // sinon c'est une déclinaison du produit "reference"
                if (
                    $feedproduct[$tabledata['combination_reference']] != ''
                    && $feedproduct[$tabledata['combination_reference']] != '0'
                    && $feedproduct[$tabledata['combination_reference']] !=
                    $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]]
                ) {
                    $combinations[] = $feedproduct;
                    continue;
                }

                // Création du tableau des attributs pour le produit en cours
                foreach ($attributes as $attr_name => $attr_infos) {
                    if (empty($attr_infos['id_attribute_group']) || ($attr_infos['id_attribute_group'] == 0)) {
                        continue;
                    }

                    $attributes[$attr_name]['value'] =
                        $feedproduct[$tabledata[$attr_name . '_' . $attr_infos['id_attribute_group']]];
                }

                // Si le produit de base a la valeur taille ou couleur renseignée,
                // il faut créer une déclinaison (la principale)
                if ((!empty($attributes['taille']['value'])) || (!empty($attributes['couleur']['value']))) {
                    $tmp_feedproduct = $feedproduct;
                    $tmp_feedproduct[$tabledata['combination_reference']] =
                        $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]];
                    $combinations[] = $tmp_feedproduct;
                    $has_combination_base = $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]];
                }

                /**
                 * @edit Definima
                 * DEV : Retirer le commentaire de tout le bloc suivant pour skip le traitement des produits
                 */

                // *
                $reference = $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]];
                $pname = $feedproduct[$tabledata['name']];

                if (trim($pname) == '') {
                    $output .= $this->l('Product name') . ' : ' . $pname . "\n";
                    continue;
                }
                if ($Submitoffset != '0' && $Submitoffset != '') {
                    if (trim($reference) != $Submitoffset && $gotit == 0) {
                        continue;
                    } else {
                        $gotit = 1;
                    }
                }
                if ($Submitlimit != '0' && $Submitlimit != '') {
                    if ((int) $linecount >= $Submitlimit) {
                        break;
                    }
                }
                if (trim($reference) == '') {
                    $output .= $this->l('Product reference not found') . ' : ' . $pname . "\n";
                    continue;
                }
                $linecount = $linecount + 1;
                $row = Db::getInstance()->getRow('SELECT p.id_product FROM `' . _DB_PREFIX_ .
                    'product` p WHERE p.' . Configuration::get('PI_PRODUCT_REFERENCE') . ' = "' . pSQL($reference) . '"');

                if ($row) {
                    $product_id = $row['id_product'];
                    $mode = 'edit';
                    if ($enableeditproduct != 1) {
                        $output .= $this->l('Product edit not allowed') . "\n";
                        continue;
                    }
                    $product = new Product($product_id);
                    $shops = [];
                    if (isset($product->shop)) {
                        $product_shop = explode(';', $product->shop);
                        foreach ($product_shop as $shop) {
                            $shop = trim($shop);
                            if (!is_numeric($shop)) {
                                $shop = ShopGroup::getIdByName($shop);
                            }
                            $shops[] = $shop;
                        }
                    }
                    if (empty($shops)) {
                        $shops = Shop::getContextListShopID();
                    }

                    $pr_exists = $pr_exists + 1;
                    $product_id = $row['id_product'];
                    $temp_p_array3[] = $product_id;

                    $product->loadStockData();
                    $this->savenameanddescription(
                        Configuration::get('PI_UPDATE_DESIGNATION') ? $pname : null,
                        isset($tabledata['description']) ? $feedproduct[$tabledata['description']] : null,
                        isset($tabledata['description_short']) ? $feedproduct[$tabledata['description_short']] : null,
                        $languages,
                        $product
                    );
                } else {
                    $mode = 'add';
                    $product = new Product();
                    $product->ean13 = '';
                    $product->upc = '';
                    $product->ecotax = 0;
                    $product->minimal_quantity = 1;
                    $product->default_on = 0;

                    $temp_p_array4[] = $reference;
                    if ($enableaddproduct != 1) {
                        $output .= $this->l('New Product addition not allowed') . "\n";
                        continue;
                    }
                    $product_id = 0;
                    $shops = [];
                    if (isset($product->shop)) {
                        $product_shop = explode(';', $product->shop);
                        foreach ($product_shop as $shop) {
                            $shop = trim($shop);
                            if (!is_numeric($shop)) {
                                $shop = ShopGroup::getIdByName($shop);
                            }

                            $shops[] = $shop;
                        }
                    }
                    if (empty($shops)) {
                        $shops = Shop::getContextListShopID();
                    }
                    $this->savenameanddescription(
                        $pname,
                        isset($tabledata['description']) ? $feedproduct[$tabledata['description']] : null,
                        isset($tabledata['description_short']) ? $feedproduct[$tabledata['description_short']] : null,
                        $languages,
                        $product
                    );
                }
                $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
                $product->$reference_field = $reference;
                // Ecotax
                $product->ecotax = (isset($tabledata['ecotax'], $feedproduct[$tabledata['ecotax']]))
                    ? ProductVccsv::formatPriceFromWS($feedproduct[$tabledata['ecotax']])
                    : 0.000000;
                // Installation de la taxe liée au produit
                if (isset($tabledata['id_tax_rules_group'])) {
                    // Default Tax
                    $product->id_tax_rules_group = 1;
                    // Try to find right id_tax_rules_group
                    $rows = Db::getInstance()->executeS('SELECT rg.`id_tax_rules_group`, t.`rate`
                        FROM `' . _DB_PREFIX_ . 'tax_rules_group` rg
                        LEFT JOIN `' . _DB_PREFIX_ . 'tax_rule` tr ON (tr.`id_tax_rules_group` = rg.`id_tax_rules_group`)
                        LEFT JOIN `' . _DB_PREFIX_ . 'tax` t ON (t.`id_tax` = tr.`id_tax`)
                        WHERE rg.`active`=1 AND rg.`deleted`=0
                        GROUP BY rate');
                    foreach ($rows as $row) {
                        if ((float) $row['rate'] == (float) $feedproduct[$tabledata['id_tax_rules_group']]) {
                            $product->id_tax_rules_group = $row['id_tax_rules_group'];
                            break;
                        }
                    }
                }
                if (isset($tabledata['wholesale_price'], $feedproduct[$tabledata['wholesale_price']])) {
                    $product->wholesale_price = str_replace(', ', '.', $feedproduct[$tabledata['wholesale_price']]);
                    $product->wholesale_price = str_replace('#', '.', $product->wholesale_price);
                    $product->wholesale_price = str_replace('R', '', $product->wholesale_price);
                    $product->wholesale_price = (float) $product->wholesale_price;
                    $product->wholesale_price = number_format($product->wholesale_price, 6, '.', '');
                } else {
                    $product->wholesale_price = 0.000000;
                }

                // VCOMK
                if (
                    isset($tabledata['weight']) && $feedproduct[$tabledata['weight']]
                    && is_numeric($feedproduct[$tabledata['weight']])
                ) {
                    $product->weight = $feedproduct[$tabledata['weight']];
                }

                if ($feedproduct[$tabledata['condition']] == 1) {
                    $product->condition = 'new';
                } else {
                    $product->condition = 'used';
                }

                if (isset($tabledata['id_tax_rules_group'])) {
                    $amount_tax = $feedproduct[$tabledata['id_tax_rules_group']];
                } else {
                    $amount_tax = 20;
                }

                if (isset($tabledata['price'], $feedproduct[$tabledata['price']])) {
                    $prix_brut = $feedproduct[$tabledata['price']];

                    // Récupérer l'ecotax pour la déduire du prix TTC
                    $ecotax_amount = 0;
                    if (isset($tabledata['ecotax'], $feedproduct[$tabledata['ecotax']])) {
                        $ecotax_amount = (float) str_replace([',', '#', 'R', ' '], ['.', '.', '', ''], $feedproduct[$tabledata['ecotax']]);
                    }

                    $prix_clean = str_replace([',', '#', 'R', ' '], ['.', '.', '', ''], $prix_brut);
                    $prix_clean = (float) $prix_clean;

                    if ($prix_clean > 0) {
                        $prix_ttc_sans_ecotax = $prix_clean - $ecotax_amount;
                        $prix_ht = $prix_ttc_sans_ecotax / 1.20;
                        $product->price = number_format($prix_ht, 6, '.', '');
                    } else {
                        $product->price = 0.000000;
                    }
                }

                if (isset($tabledata['quantity'])) {
                    if (isset($feedproduct[$tabledata['quantity']])) {
                        if (is_numeric(trim($feedproduct[$tabledata['quantity']]))) {
                            $product->quantity = $feedproduct[$tabledata['quantity']];
                        } else {
                            $product->quantity = 0;
                        }
                    }
                }

                if ($has_combination_base && isset($tabledata['available_date'])) {
                    if (isset($feedproduct[$tabledata['available_date']])) {
                        $product->available_date = $feedproduct[$tabledata['available_date']];
                    }
                }

                if (isset($tabledata['manufacturer'])) {
                    if (trim($feedproduct[$tabledata['manufacturer']]) != '') {
                        $Manufacturer = $feedproduct[$tabledata['manufacturer']];
                        $this->setmanufacturer($Manufacturer, $product);
                    }
                }

                if ($mode == 'add') {
                    if (isset($tabledata['id_category_default'])) {
                        $category = $feedproduct[$tabledata['id_category_default']];
                        $catarr = explode('|', $category);
                        if (count($catarr) > 1) {
                            $category = trim($catarr[1]);
                        } else {
                            $category = trim($catarr[0]);
                        }
                        $row = Db::getInstance()->getRow('SELECT system_catid, xml_catid, create_new FROM `' . _DB_PREFIX_ .
                            'pfi_import_feed_catfields_csv` WHERE xml_catid = "' . pSQL($category) . '"');
                        if (isset($row['system_catid']) && $row['system_catid'] > 0) {
                            if (isset($row['create_new']) && $row['create_new'] == 1) {
                                // $parentcat = $row['system_catid'];
                                if (is_numeric($row['xml_catid'])) {
                                    $product->category = [$row['system_catid']];
                                } else {
                                    $product->category = [$category];
                                }
                            } else {
                                $product->category = [$row['system_catid']];
                            }
                        } else {
                            $product->category = [$category];
                        }

                        if ((int) $fixcategory > 0) { // fixcategory
                            $product->category = [$fixcategory];
                        }

                        $output .= $this->setproductcategory($product, $default_language_id, $languages);

                        if ($product->id_category_default == '') {
                            $product->id_category_default = 2;
                            // $linecounterror = $linecounterror + 1;
                            // continue;
                        }
                    }
                    ProductVccsv::setproductlinkRewrite($product, $default_language_id, $languages);
                }

                $res = false;
                $field_error = $product->validateFields(UNFRIENDLY_ERROR, true);
                $lang_field_error = $product->validateFieldsLang(UNFRIENDLY_ERROR, true);

                if ($field_error === true && $lang_field_error === true) {
                    if ($product->id && Product::existsInDatabase((int) $product->id, 'product')) {
                        $linecountedited = $linecountedited + 1;
                        $datas = Db::getInstance()->getRow('
                        SELECT product_shop.`date_add`
                        FROM `' . _DB_PREFIX_ . 'product` p
                        ' . Shop::addSqlAssociation('product', 'p') . '
                        WHERE p.`id_product` = ' . (int) $product->id);
                        $product->date_add = pSQL($datas['date_add']);
                        try {
                            $res = $product->update();
                        } catch (Exception $e) {
                            $output .= Vccsv::logError($e);
                        }
                        $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');

                        Db::getInstance()->execute('insert into `' . _DB_PREFIX_ .
                            'pfi_import_log`(vdate, reference, product_error) value(NOW(), "' .
                            pSQL($product->$reference_field) . '", "Mis a jour")');
                        // VcomK.
                        Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ .
                            'pfi_import_tempdata_csv WHERE col1 = "' . pSQL($product->$reference_field) . '"');
                    } else {
                        // If no id_product or update failed
                        if (!$res) {
                            $product->active = $activeproduct;
                            $linecountadded = $linecountadded + 1;
                            try {
                                if (isset($product->date_add) && $product->date_add != '') {
                                    $res = $product->add(false);
                                } else {
                                    $res = $product->add();
                                }
                            } catch (Exception $e) {
                                $output .= Vccsv::logError($e);
                            }
                            $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
                            Db::getInstance()->execute('insert into `' . _DB_PREFIX_ .
                                'pfi_import_log`(vdate, reference, product_error) value(NOW(), "' .
                                pSQL($product->$reference_field) . '", "Ajoute")');
                            // VcomK.
                            Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ .
                                'pfi_import_tempdata_csv WHERE col1 = "' . pSQL($product->$reference_field) . '"');
                        } else {
                            $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
                            Db::getInstance()->execute('insert into `' . _DB_PREFIX_ .
                                'pfi_import_log`(vdate, reference, product_error) value(NOW(), "' .
                                pSQL($product->$reference_field) . '", "Erreur")');
                        }
                    }
                } else {
                    $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
                    Db::getInstance()->execute('insert into `' . _DB_PREFIX_ .
                        'pfi_import_log`(vdate, reference, product_error) value(NOW(), "' .
                        pSQL($product->$reference_field) . '", "Field error ' . $field_error . ' - ' .
                        $lang_field_error . '")');
                    $output .= $product->$reference_field . ' - ' . $field_error . ' - ' . $lang_field_error . "\n";
                    continue;
                }
                // ============================
                if ($res) {
                    if (isset($tabledata['retail_price_new'], $feedproduct[$tabledata['retail_price_new']])) {
                        $retail_price = str_replace(', ', '.', $feedproduct[$tabledata['retail_price_new']]);
                        $retail_price = str_replace('#', '.', $retail_price);
                        $retail_price = str_replace('R', '', $retail_price);
                        $retail_price = trim($retail_price);
                        $retail_price = str_replace('n/a', '0.00', $retail_price);
                        $sql = ' Update `' . _DB_PREFIX_ . 'product` set retail_price_new=' . (float) $retail_price .
                            ' where  id_product = ' . (int) $product->id;
                        Db::getInstance()->Execute($sql);
                    }
                }
                if (!$res) {
                    $linecounterror = $linecounterror + 1;
                    Db::getInstance()->execute('insert into `' . _DB_PREFIX_ .
                        'pfi_import_log`(vdate, reference, product_error) value(NOW(), ' . (int) $product_id .
                        ', "' . pSQL($field_error) . ' - ' . pSQL($lang_field_error) . '")');
                    $output .= $this->l('Error : product add-update error') . "\n";
                    continue;
                } else {
                    $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
                    $temp_p_array[] = $product->id;
                    $temp_p_array2[] = $product->$reference_field;
                    // Product supplier 

                    // Vérifier si on garde cette partie ou pas

                    // $product->supplier_reference = '';
                    // if (isset($product->id_supplier, $product->supplier_reference)) {
                    //     $id_product_supplier = ProductSupplier::getIdByProductAndSupplier(
                    //         (int) $product->id,
                    //         0,
                    //         (int) $product->id_supplier
                    //     );
                    //     if ($id_product_supplier) {
                    //         $product_supplier = new ProductSupplier((int) $id_product_supplier);
                    //     } else {
                    //         $product_supplier = new ProductSupplier();
                    //     }
                    //     $product_supplier->id_product = $product->id;
                    //     $product_supplier->id_product_attribute = 0;
                    //     $product_supplier->id_supplier = $product->id_supplier;
                    //     $product_supplier->product_supplier_price_te = $product->wholesale_price;
                    //     $product_supplier->product_supplier_reference = $product->supplier_reference;
                    //     $product_supplier->save();
                    // }
                }
                if (isset($product->id_category)) {
                    $product->updateCategories(array_map('intval', $product->id_category));
                }

                //
                // @edit Definima
                // Mise à jour de l'impact prix et poids des déclinaisons
                //
                if (Combination::isFeatureActive()) {
                    CombinationVccsv::updatePriceAndWeight($product, $amount_tax, $default_language_id, $shops);
                }

                // =================================
                // quantity
                $pdv = Configuration::get('SYNC_STOCK_PDV');
                if (!empty($pdv)) {
                    // Prise en compte uniquement des stocks du PDV renseigné
                    $softwareid = Configuration::get('PI_SOFTWAREID');
                    $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
                    $pdv = explode(',', $pdv);
                    $pdv = array_map('strtolower', $pdv);
                    $pdv = array_map('trim', $pdv);
                    $stock = 0;
                    $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                    $stock_pdvs = $sc->getStocksFromCode($softwareid, $product->$reference_field);
                    if (is_array($stock_pdvs->stockPdv)) {
                        $stocks = $stock_pdvs->stockPdv;
                    } else {
                        $stocks = [$stock_pdvs->stockPdv];
                    }
                    foreach ($stocks as $st) {
                        if (in_array($st->idPdv, $pdv)) {
                            $stock += $st->stock;
                        }
                    }
                } else {
                    $stock = $product->quantity;
                }
                if (StockAvailable::setQuantity((int) $product->id, 0, $stock) !== false) {
                    $output .= 'Article ' . $product->$reference_field . ' mis a jour sur Prestashop depuis Rezomatic' . "\n";
                } else {
                    $output .= 'Article ' . $product->$reference_field . ' <b>NON</b> mis a jour sur Prestashop' . "\n";
                }
                $res = Db::getInstance()->Execute('update `' . _DB_PREFIX_ . 'pfi_import_update` set sync_reference="' .
                    pSQL($reference) . '" where feedid =1');

                //
                // @edit Definima
                // Ajout des images dans le tableau des images à traiter
                //
                if (Configuration::get('PI_ALLOW_PRODUCTIMAGEIMPORT') == '1') {
                    if (
                        isset($feedproduct[$tabledata['image_url']])
                        && trim($feedproduct[$tabledata['image_url']]) != ''
                        && $feedproduct[$tabledata['image_url']] != '0'
                    ) {
                        $img_separator = ',';

                        $import_images[] = [
                            'urls' => explode($img_separator, $feedproduct[$tabledata['image_url']]),
                            'product' => $product,
                            'reference' => $reference,
                            'shops' => $shops,
                        ];
                    }
                }
            } catch (Exception $e) {
                $reference = Configuration::get('PI_PRODUCT_REFERENCE');

                $res = Db::getInstance()->Execute('update `' . _DB_PREFIX_ . 'pfi_import_update` set sync_reference="' .
                    pSQL($reference) . '" where feedid =1');
                continue;
            }
        }

        // $this->dump($tabledata, $combinations);
        // exit;

        /*
         * @edit Definima
         * Traitement des déclinaisons
         */
        if (Combination::isFeatureActive()) {
            if ($this->isPrestashop8()) {
                $Attribute = "ProductAttribute";
            } else {
                $Attribute = "Attribute";
            }
            // $linecountedited_combinations = 0;
            $linecountadded_combinations = 0;
            $linecounterror_combinations = 0;
            $linecount_combinations = 0;
            if (!empty($combinations)) {
                // Boucle sur les déclinaisons
                foreach ($combinations as $feedproduct) {
                    try {
                        $reference = $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]]; // 
                        $product_reference = $feedproduct[$tabledata['combination_reference']];
                        $pname = $feedproduct[$tabledata['name']];

                        if (trim($pname) == '') {
                            $output .= $this->l('Combination name') . ' : ' . $pname . "\n";
                            continue;
                        }
                        if ($Submitoffset != '0' && $Submitoffset != '') {
                            if (trim($reference) != $Submitoffset && $gotit == 0) {
                                continue;
                            } else {
                                $gotit = 1;
                            }
                        }
                        if ($Submitlimit != '0' && $Submitlimit != '') {
                            if ((int) $linecount_combinations >= $Submitlimit) {
                                break;
                            }
                        }
                        if (trim($reference) == '') {
                            $output .= $this->l('Combination reference not found') . ' : ' . $pname . "\n";
                            continue;
                        }
                        $linecount_combinations = $linecount_combinations + 1;

                        // Récupère le produit de base
                        $id_product_base = ProductVccsv::getProductIdByRefRezomatic($product_reference);

                        if (!$id_product_base) {
                            Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                                'pfi_import_log`(vdate, reference, product_error) VALUE(NOW(), "' .
                                pSQL($reference) . '", "Combination error : base product ' . $product_reference .
                                ' not found")');
                            $output .= $reference . ' Combination error : base product ' . $product_reference .
                                ' not found' . "\n";
                            continue;
                        }

                        $product = new Product($id_product_base);
                        $shops = [];
                        if (isset($product->shop)) {
                            $product_shop = explode(';', $product->shop);
                            foreach ($product_shop as $shop) {
                                $shop = trim($shop);
                                if (!is_numeric($shop)) {
                                    $shop = ShopGroup::getIdByName($shop);
                                }
                                $shops[] = $shop;
                            }
                        }
                        if (empty($shops)) {
                            $shops = Shop::getContextListShopID();
                        }

                        // Récupère la déclinaison par défaut
                        $is_combination_base = false;
                        if ($has_combination_base && $has_combination_base == $reference) {
                            $is_combination_base = true;
                        }
                        $has_default_combination = Product::getDefaultAttribute($product->id);

                        // Suppression de la déclinaison par défaut si on traite la déclinaison de base
                        if ($is_combination_base) {
                            $has_default_combination = false;
                            $product->deleteDefaultAttributes();
                        }

                        // inits attribute
                        $id_product_attribute = 0;
                        $id_product_attribute_update = false;
                        $attributes_to_add = [];

                        // Pour chacun des attributs
                        foreach ($attributes as $attr_name => $attr_infos) {
                            // Groupe d'attribut non défini (depuis mapping)
                            if (empty($attr_infos['id_attribute_group']) || ($attr_infos['id_attribute_group'] == 0)) {
                                continue;
                            }

                            $value = $feedproduct[$tabledata[$attr_name . '_' . $attr_infos['id_attribute_group']]];
                            $attr_infos['value'] = $value;

                            if ($value == '0') {
                                continue;
                            }

                            // Récupère l'attribut
                            $infos_attribute = CombinationVccsv::getAttributeByGroupAndValue(
                                $attr_infos['id_attribute_group'],
                                $value,
                                $default_language_id
                            );
                            $id_attribute = 0;

                            // $this->dump($infos_attribute);
                            // exit;

                            if (!$infos_attribute) {
                                // Création de l'attribut
                                $obj = new $Attribute();
                                $obj->id_attribute_group = $attr_infos['id_attribute_group'];

                                $namearray = [];
                                foreach ($languages as $lang) {
                                    $namearray[$lang['id_lang']] = $value;
                                }

                                $obj->name = $namearray;
                                $obj->position = $Attribute::getHigherPosition($attr_infos['id_attribute_group']) + 1;

                                if (($field_error = $obj->validateFields(UNFRIENDLY_ERROR, true)) === true
                                    && ($lang_field_error = $obj->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true
                                ) {
                                    $obj->add();
                                    $obj->associateTo($shops);
                                    $id_attribute = (int) $obj->id;

                                    Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                                        'pfi_import_log`(vdate, reference, product_error) VALUE(NOW(), "' .
                                        pSQL($reference) . '", "Creation attribut - ' . $id_attribute . ')');
                                    $output .= $reference . ' Creation attribut - ' . $id_attribute . "\n";
                                } else {
                                    $output .= 'Attribute creation - ' . $field_error . ' - ' . $lang_field_error . "\n";
                                    Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                                        'pfi_import_log`(vdate, reference, product_error) VALUE(NOW(), "' .
                                        pSQL($reference) . '", "Erreur creation attribut - ' . $field_error . ' - ' .
                                        $lang_field_error . ')');
                                    $output .= $reference . ' Erreur creation attribut -  ' . $field_error . ' - ' .
                                        $lang_field_error . "\n";
                                }
                            } else {
                                $id_attribute = (int) $infos_attribute['id_attribute'];
                            }

                            // Formate les infos

                            if (isset($tabledata['id_tax_rules_group'])) {
                                $amount_tax = $feedproduct[$tabledata['id_tax_rules_group']];
                            } else {
                                $amount_tax = 20;
                            }

                            $wholesale_price = isset($feedproduct[$tabledata['wholesale_price']])
                                ? ProductVccsv::formatPriceFromWS($feedproduct[$tabledata['wholesale_price']])
                                : 0.000000;
                            $weight = isset($feedproduct[$tabledata['weight']])
                                ? (float) $feedproduct[$tabledata['weight']]
                                : 0;
                            $price = isset($feedproduct[$tabledata['price']])
                                ? ProductVccsv::formatPriceFromWS($feedproduct[$tabledata['price']], $amount_tax)
                                : 0.000000;
                            $ecotax = isset($feedproduct[$tabledata['ecotax']])
                                ? ProductVccsv::formatPriceFromWS($feedproduct[$tabledata['ecotax']])
                                : 0.000000;

                            // Impacts
                            $price = ProductVccsv::formatPriceFromWS($price - $product->price);
                            $weight = $weight - $product->weight;

                            // Récupère la déclinaison si elle existe
                            if (Configuration::get('PI_PRODUCT_REFERENCE') == 'reference') {
                                $reference_for_combination = $feedproduct[$tabledata['reference']];
                                $id_product_attribute = Combination::getIdByReference($product->id, $reference_for_combination);
                            } elseif (Configuration::get('PI_PRODUCT_REFERENCE') == 'ean13') {
                                $reference_for_combination = ''; // Pas de référence auto pour les déclinaisons en mode EAN13

                                // Chercher la déclinaison existante par EAN13
                                $id_product_attribute = 0;
                                $ean13_value = isset($feedproduct[$tabledata['ean13']]) ? $feedproduct[$tabledata['ean13']] : '';
                                if ($ean13_value) {
                                    $existing_combinations = $product->getAttributeCombinations($default_language_id);
                                    foreach ($existing_combinations as $existing_combination) {
                                        if ($existing_combination['ean13'] == $ean13_value) {
                                            $id_product_attribute = $existing_combination['id_product_attribute'];
                                            break;
                                        }
                                    }
                                }
                            }

                            // La déclinaison n'existe pas, on crée l'entité
                            if (!$id_product_attribute) {
                                $id_product_attribute = $product->addCombinationEntity(
                                    $wholesale_price, // wholesale_price
                                    $price, // price
                                    $weight, // weight
                                    0, // unit_impact
                                    Configuration::get('PS_USE_ECOTAX') ? (float) $ecotax : 0, // ecotax
                                    $feedproduct[$tabledata['quantity']], // quantity
                                    [], // id_images
                                    $reference_for_combination, // reference
                                    0, // id_supplier
                                    isset($tabledata['ean13']) && isset($feedproduct[$tabledata['ean13']])
                                        ? $feedproduct[$tabledata['ean13']]
                                        : '', // ean13
                                    $has_default_combination ? 0 : 1, // default
                                    null, // location
                                    isset($tabledata['upc']) && isset($feedproduct[$tabledata['upc']])
                                        ? $feedproduct[$tabledata['upc']]
                                        : null, // upc
                                    1, // minimal_quantity
                                    $shops, // id_shop_list
                                    null // available_date
                                );

                                if (!$id_product_attribute) {
                                    Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                                        'pfi_import_log`(vdate, reference, product_error) VALUE(NOW(), "' .
                                        pSQL($reference) . '", "Erreur creation declinaison pour produit ' .
                                        $product_reference . '")');
                                    $output .= $reference . ' Erreur creation declinaison pour produit ' .
                                        $product_reference . "\n";

                                    $linecounterror_combinations = $linecounterror_combinations + 1;
                                    continue;
                                }

                                // after insertion, we clean attribute position and group attribute position
                                $obj = new $Attribute();
                                $obj->cleanPositions((int) $attributes[$attr_name]['id_attribute_group'], false);
                                AttributeGroup::cleanPositions();

                                // log
                                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                                    'pfi_import_log`(vdate, reference, product_error) VALUE(NOW(), "' .
                                    pSQL($reference) . '", "Declinaison creee pour produit ' . $product_reference . '")');
                                // $output .= $reference . ' Declinaison creee pour produit ' . $product_reference . "\n";

                                $linecountadded_combinations = $linecountadded_combinations + 1;
                            } else {
                                // gets all the combinations of this product
                                $attribute_combinations = $product->getAttributeCombinations($default_language_id);
                                foreach ($attribute_combinations as $attribute_combination) {
                                    if (in_array($id_product_attribute, $attribute_combination)) {
                                        $product->updateAttribute(
                                            $id_product_attribute,
                                            $wholesale_price, // wholesale_price
                                            $price, // price
                                            $weight, // weight
                                            0, // unit_impact
                                            Configuration::get('PS_USE_ECOTAX') ? (float) $ecotax : 0, // ecotax
                                            [], // id_images
                                            $reference_for_combination, // reference
                                            isset($tabledata['ean13']) && isset($feedproduct[$tabledata['ean13']])
                                                ? $feedproduct[$tabledata['ean13']]
                                                : '', // ean13
                                            $has_default_combination ? 0 : 1, // default
                                            null, // location
                                            isset($tabledata['upc']) && isset($feedproduct[$tabledata['upc']])
                                                ? $feedproduct[$tabledata['upc']]
                                                : null, // upc
                                            1, // minimal_quantity
                                            null, // available_date
                                            null, // update_all_fields
                                            $shops // id_shop_list
                                        );

                                        $id_product_attribute_update = true;
                                        // $linecountedited_combinations = $linecountedited_combinations + 1;

                                        // log
                                        // Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                                        //     'pfi_import_log`(vdate, reference, product_error) VALUE(NOW(), "' .
                                        //     pSQL($reference) . '", "Declinaison mise a jour pour produit ' .
                                        //     $product_reference . '")');
                                        // $output .= $reference.' Declinaison mise a jour pour produit '.
                                        //     $product_reference . "\n";
                                    }
                                }
                            }

                            if ($id_attribute) {
                                $attributes_to_add[] = (int) $id_attribute;
                            }
                        }

                        $product->checkDefaultAttributes();
                        if (!$product->cache_default_attribute) {
                            Product::updateDefaultAttribute($product->id);
                        }

                        if ($id_product_attribute) {
                            // now adds the attributes in the attribute_combination table
                            if ($id_product_attribute_update) {
                                Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'product_attribute_combination
                                    WHERE id_product_attribute = ' . (int) $id_product_attribute);
                            }

                            foreach ($attributes_to_add as $attribute_to_add) {
                                Db::getInstance()->execute('INSERT IGNORE INTO ' . _DB_PREFIX_ .
                                    'product_attribute_combination (id_attribute, id_product_attribute)
                                    VALUES (' . (int) $attribute_to_add . ',' . (int) $id_product_attribute . ')
                                ', false);
                            }

                            // quantity
                            $pdv = Configuration::get('SYNC_STOCK_PDV');
                            $stock_product = 0;
                            if (!empty($pdv)) {
                                // Prise en compte uniquement des stocks du PDV renseigné
                                $softwareid = Configuration::get('PI_SOFTWAREID');
                                $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
                                $pdv = explode(',', $pdv);
                                $pdv = array_map('strtolower', $pdv);
                                $pdv = array_map('trim', $pdv);
                                $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                                $stock_pdvs = $sc->getStocksFromCode($softwareid, $reference);
                                if (is_array($stock_pdvs->stockPdv)) {
                                    $stocks = $stock_pdvs->stockPdv;
                                } else {
                                    $stocks = [$stock_pdvs->stockPdv];
                                }
                                foreach ($stocks as $st) {
                                    if (in_array($st->idPdv, $pdv)) {
                                        $stock_product += $st->stock;
                                    }
                                }
                            } else {
                                $stock_product = isset($feedproduct[$tabledata['quantity']]) ?
                                    (float) $feedproduct[$tabledata['quantity']] :
                                    0;
                            }
                            foreach ($shops as $shop) {
                                StockAvailable::setQuantity(
                                    (int) $product->id,
                                    (int) $id_product_attribute,
                                    (int) $stock_product,
                                    (int) $shop
                                );
                            }

                            // Ajout des images dans le tableau des images à traiter
                            // $this->dump($feedproduct[$tabledata['image_url']], $id_product_attribute);
                            if (Configuration::get('PI_ALLOW_PRODUCTIMAGEIMPORT') == '1') {
                                if (
                                    isset($feedproduct[$tabledata['image_url']])
                                    && trim($feedproduct[$tabledata['image_url']]) != ''
                                    && $feedproduct[$tabledata['image_url']] != '0'
                                ) {
                                    $img_separator = ',';

                                    $import_images[] = [
                                        'urls' => explode($img_separator, $feedproduct[$tabledata['image_url']]),
                                        'product' => $product,
                                        'reference' => $reference,
                                        'shops' => $shops,
                                        'id_product_attribute' => $id_product_attribute,
                                    ];
                                }
                            }
                        }

                        // Suppression de la table d'import temporaire
                        Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'pfi_import_tempdata_csv WHERE
                        col1 = "' . pSQL($reference) . '"');
                    } catch (Exception $e) {
                        $res = Db::getInstance()->Execute('update `' . _DB_PREFIX_ .
                            'pfi_import_update` set sync_reference="' . pSQL($reference) . '" where feedid =1');
                        continue;
                    }
                }
            }
        }

        /*
         * @edit Definima
         * Traitement des images
         */
        if (!empty($import_images)) {
            // Suppression des images Prestashop
            foreach ($import_images as $img) {
                $id_product = (int) $img['product']->id;
                $id_product_attribute = isset($img['id_product_attribute']) ? (int) $img['id_product_attribute'] : 0;
                $img['id_product_attribute'] = $id_product_attribute;
                $tmp_images = ProductVccsv::getSyncImages($id_product);
                foreach ($tmp_images as $tmpimg) {
                    if (in_array($tmpimg['reference'], $codeArtUpdated)) {
                        ProductVccsv::deleteImage($tmpimg['system_imageid']);
                    }
                }
            }
            // Rajout des images Rezomatic
            foreach ($import_images as $img) {
                $id_product = (int) $img['product']->id;
                $id_product_attribute = isset($img['id_product_attribute']) ? (int) $img['id_product_attribute'] : 0;
                $img['id_product_attribute'] = $id_product_attribute;
                $tmp_images = ProductVccsv::getSyncImages($id_product);
                sort($img['urls']);
                // Rajout des images Rezomatic
                if (is_array($img['urls']) && !empty($img['urls'])) {
                    foreach ($img['urls'] as $url) {
                        ProductVccsv::insertImage($url, $img, $languages, $this);
                    }
                }
            }
        }

        $allow_customerimport = Configuration::get('PI_ALLOW_CUSTOMERIMPORT');

        if ($allow_customerimport == 1) {
            // UPDATE CLIENTS (comme avant)
            $output .= CustomerVccsv::importCustomer();
        }

        if ($iscron == 1) {
            echo '-------------------------------------------------<br/>';
            echo $this->l('No.of Entries processed') . ' : ' . $linecount . '<br/>';
            echo '-------------------------------------------------<br/>';
            echo $this->l('No.of Entries with error') . ' : ' . $linecounterror . '<br/>';
            echo '-------------------------------------------------<br/>';
            echo $this->l('No.of Products created') . ' : ' . $linecountadded . '<br/>';
            echo '-------------------------------------------------<br/>';
            echo $this->l('No.of Products updated') . ' : ' . $linecountedited . '<br/>';
            echo '-------------------------------------------------<br/>';
            echo $this->l('No.of Customers processed') . ' : ' . CustomerVccsv::$last_import_stats['processed'] . '<br/>';
            echo '-------------------------------------------------<br/>';

            /*
             * @edit Definima
             * Ajout des déclinaisons dans le log
             */
            if (Combination::isFeatureActive()) {
                echo '-------------------------------------------------<br/>';
                echo '-------------------------------------------------<br/>';
                echo $this->l('No.of Combination entries processed') . ' : ' . $linecount_combinations . '<br/>';
                echo '-------------------------------------------------<br/>';
                echo $this->l('No.of Combination entries with error') . ' : ' . $linecounterror_combinations . '<br/>';
                echo '-------------------------------------------------<br/>';
                echo $this->l('No.of Combination created') . ' : ' . $linecountadded_combinations . '<br/>';
                echo '-------------------------------------------------<br/>';
                echo $this->l('No.of Combination updated') . ' : ' .
                    ($linecount_combinations - $linecountadded_combinations) . '<br/>';
                echo '-------------------------------------------------<br/>';
            }

            echo '-------------------------------------------------<br/>';
            echo '-------------------------------------------------<br/>';
            echo $this->l('New categories') . ' : ' . implode(', ', $this->arrcat) . '<br/>';
            echo '-------------------------------------------------<br/>';
        }
        if (!empty($output)) {
            return $output;
        } else {
            return '';
        }
    }

    /**
     * @edit Definima
     * Modification de cette fonction car ne fonctionnait pas sous PS 1.5
     *
     * copyImg function.
     *
     * @static
     *
     * @param mixed $id_entity
     * @param mixed $id_image (default: null)
     * @param mixed $url
     * @param string $entity (default: 'products')
     *
     * @return bool
     */
    public static function copyImg($id_entity, $id_image, $url, $entity = 'products', $regenerate = true)
    {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));

        switch ($entity) {
            default:
            case 'products':
                $image_obj = new Image($id_image);
                $path = $image_obj->getPathForCreation();
                break;
            case 'categories':
                $path = _PS_CAT_IMG_DIR_ . (int) $id_entity;
                break;
            case 'manufacturers':
                $path = _PS_MANU_IMG_DIR_ . (int) $id_entity;
                break;
            case 'suppliers':
                $path = _PS_SUPP_IMG_DIR_ . (int) $id_entity;
                break;
        }
        $url = str_replace(' ', '%20', trim($url));

        // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
        if (!ImageManager::checkImageMemoryLimit($url)) {
            return false;
        }

        // 'file_exists' doesn't work on distant file, and getimagesize make the import slower.
        // Just hide the warning, the traitment will be the same.
        if (Tools::copy($url, $tmpfile)) {
            ImageManager::resize($tmpfile, $path . '.jpg');
            $images_types = ImageType::getImagesTypes($entity);

            if ($regenerate) {
                foreach ($images_types as $image_type) {
                    ImageManager::resize(
                        $tmpfile,
                        $path . '-' . Tools::stripslashes($image_type['name']) . '.jpg',
                        $image_type['width'],
                        $image_type['height']
                    );
                    if (in_array($image_type['id_image_type'], $watermark_types)) {
                        Hook::exec('actionWatermark', ['id_image' => $id_image, 'id_product' => $id_entity]);
                    }
                }
            }
        } else {
            unlink($tmpfile);

            return false;
        }
        unlink($tmpfile);

        return true;
        /*$tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(', ', Configuration::get('WATERMARK_TYPES'));
        switch ($entity) {
            default:
            case 'products':
                $image_obj = new Image($id_image);
                $path = $image_obj->getPathForCreation();
                break;
            case 'categories':
                $path = _PS_CAT_IMG_DIR_.(int)$id_entity;
                break;
        }
        $url = str_replace(' ', '%20', trim($url));
        // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
        if (!ImageManager::checkImageMemoryLimit($url)) {
            if (Tools::copy($url, $tmpfile)) {
                ImageManager::resize($tmpfile, $path.'.jpg');
                $images_types = ImageType::getImagesTypes($entity);
                foreach ($images_types as $image_type) {
                    ImageManager::resize(
                        $tmpfile,
                        $path.'-'.Tools::stripslashes($image_type['name']).'.jpg',
                        $image_type['width'],
                        $image_type['height']
                    );
                }
                if (in_array($image_type['id_image_type'], $watermark_types)) {
                    Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_entity));
                }
            } else {
                if (!file_put_contents($tmpfile, Tools::file_get_contents($url))) {
                    unlink($tmpfile);
                    return false;
                } else {
                    ImageManager::resize($tmpfile, $path.'.jpg');
                    $images_types = ImageType::getImagesTypes($entity);
                    foreach ($images_types as $image_type) {
                        ImageManager::resize(
                            $tmpfile,
                            $path.'-'.Tools::stripslashes($image_type['name']).'.jpg',
                            $image_type['width'],
                            $image_type['height']
                        );
                    }
                    if (in_array($image_type['id_image_type'], $watermark_types)) {
                        Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_entity));
                    }
                }
            }
        }
        unlink($tmpfile);
        return true;*/
    }

    /**
     * @edit Definima
     * Copie des images pour les versions >= 1.6
     * Il s'agit d'une copie de AdminImportController::copyImg() (version 1.6.1.18)
     *
     * @param $id_entity
     * @param null $id_image
     * @param $url
     * @param string $entity
     * @param bool $regenerate
     *
     * @return bool
     */
    public static function copyImgNewFormat(
        $id_entity,
        $id_image = null,
        $url = '',
        $entity = 'products',
        $regenerate = true
    ) {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));

        switch ($entity) {
            default:
            case 'products':
                $image_obj = new Image($id_image);
                $path = $image_obj->getPathForCreation();
                break;
            case 'categories':
                $path = _PS_CAT_IMG_DIR_ . (int) $id_entity;
                break;
            case 'manufacturers':
                $path = _PS_MANU_IMG_DIR_ . (int) $id_entity;
                break;
            case 'suppliers':
                $path = _PS_SUPP_IMG_DIR_ . (int) $id_entity;
                break;
        }

        $url = urldecode(trim($url));
        $parced_url = parse_url($url);

        if (isset($parced_url['path'])) {
            $uri = ltrim($parced_url['path'], '/');
            $parts = explode('/', $uri);
            foreach ($parts as &$part) {
                $part = rawurlencode($part);
            }
            unset($part);
            $parced_url['path'] = '/' . implode('/', $parts);
        }

        if (isset($parced_url['query'])) {
            $query_parts = [];
            parse_str($parced_url['query'], $query_parts);
            $parced_url['query'] = http_build_query($query_parts);
        }

        if (!function_exists('http_build_url')) {
            require_once _PS_TOOL_DIR_ . 'http_build_url/http_build_url.php';
        }

        $url = http_build_url('', $parced_url);

        $orig_tmpfile = $tmpfile;

        if (Tools::copy($url, $tmpfile)) {
            // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
            if (!ImageManager::checkImageMemoryLimit($tmpfile)) {
                @unlink($tmpfile);

                return false;
            }

            $tgt_width = $tgt_height = 0;
            $src_width = $src_height = 0;
            $error = 0;
            ImageManager::resize(
                $tmpfile,
                $path . '.jpg',
                null,
                null,
                'jpg',
                false,
                $error,
                $tgt_width,
                $tgt_height,
                5,
                $src_width,
                $src_height
            );
            $images_types = ImageType::getImagesTypes($entity, true);

            if ($regenerate) {
                // $previous_path = null;
                $path_infos = [];
                $path_infos[] = [$tgt_width, $tgt_height, $path . '.jpg'];
                foreach ($images_types as $image_type) {
                    $tmpfile = self::getBestPath($image_type['width'], $image_type['height'], $path_infos);

                    if (ImageManager::resize(
                        $tmpfile,
                        $path . '-' . Tools::stripslashes($image_type['name']) . '.jpg',
                        $image_type['width'],
                        $image_type['height'],
                        'jpg',
                        false,
                        $error,
                        $tgt_width,
                        $tgt_height,
                        5,
                        $src_width,
                        $src_height
                    )) {
                        // the last image should not be added in the candidate list if it's bigger
                        // than the original image
                        if ($tgt_width <= $src_width && $tgt_height <= $src_height) {
                            $path_infos[] = [$tgt_width, $tgt_height, $path . '-' .
                                Tools::stripslashes($image_type['name']) . '.jpg',];
                        }
                        if ($entity == 'products') {
                            if (is_file(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $id_entity . '.jpg')) {
                                unlink(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $id_entity . '.jpg');
                            }
                            if (is_file(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $id_entity . '_' .
                                (int) Context::getContext()->shop->id . '.jpg')) {
                                unlink(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $id_entity . '_' .
                                    (int) Context::getContext()->shop->id . '.jpg');
                            }
                        }
                    }
                    if (in_array($image_type['id_image_type'], $watermark_types)) {
                        Hook::exec('actionWatermark', ['id_image' => $id_image, 'id_product' => $id_entity]);
                    }
                }
            }
        } else {
            @unlink($orig_tmpfile);

            return false;
        }
        unlink($orig_tmpfile);

        return true;
    }

    /**
     * @edit Definima
     * Nécessaire pour self::copyImgNewFormat()
     *
     * @param $tgt_width
     * @param $tgt_height
     * @param $path_infos
     *
     * @return string
     */
    public static function getBestPath($tgt_width, $tgt_height, $path_infos)
    {
        $path_infos = array_reverse($path_infos);
        $path = '';
        foreach ($path_infos as $path_info) {
            list($width, $height, $path) = $path_info;
            if ($width >= $tgt_width && $height >= $tgt_height) {
                return $path;
            }
        }

        return $path;
    }

    /**
     * saveTestTmpData function.
     *
     * @param mixed $id
     * @param mixed $Submitlimit
     *
     * @return void
     */
    public function saveTestTmpData($id, $Submitlimit)
    {
        Configuration::updateValue('SYNC_CSV_EMAILID', 'webmaster@tgmultimedia.com');
        $softwareid = Configuration::get('PI_SOFTWAREID');

        // $fixcategory = Tools::getValue('fixcategory');
        @ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');
        $feed_id = 1;
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        // get fields from pfi_import_feed_fields_csv
        $i = 0;
        $tabledata = '';
        $t_col = [];
        $fldarray = [];
        $t_col[] = 'feed_id';
        /**
         * @edit Definima
         * Renommage de cette variable $result en $correspondances
         */
        $correspondances = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('select `system_field`, xml_field  from `' .
            _DB_PREFIX_ . 'pfi_import_feed_fields_csv` where feed_id=' . (int) $feed_id . ' ORDER BY ID');
        // $filtercol = '';
        foreach ($correspondances as $val) {
            ++$i;
            $tabledata .= $this->balise('td') . $val['system_field'] . $this->balise('/td');
            $t_col[] = 'col' . $i;
            $fldarray[] = $val['xml_field'];
        }
        $tabledata .= $this->balise('/tr');
        // Db::getInstance()->Execute('Delete from ' . _DB_PREFIX_ . 'pfi_import_tempdata_csv ');
        if (Tools::substr($feedurl, -5) == '.wsdl' || Tools::substr($feedurl, -4) == '.csv') {
            if ($id == 3) {
                $timestamp_old = Configuration::get('PI_LAST_CRON');
            } else {
                // $timestamp = date('Y-m-d H:i:s', mktime(0, 0, 0, date('n'), date('j') - 1, date('Y') - 1));
                $timestamp_old = '2020-01-01 00:00:00';
            }
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            $art = $sc->getNewArticles($softwareid, $timestamp_old, 0);
            // $art = $sc->getNewArticles($softwareid, $timestamp, 0);

            if (!empty($art->article)) {
                // $separator = '\t';
                $final_products_arr = [];
                $i = 0;
                if (is_array($art->article) && count($art->article)) {
                    foreach ($art->article as $col) {
                        $data = (array) $col;
                        $tmp_arr = [];
                        foreach ($data as $K => $t) {
                            $tmp_arr[$K] = $t;
                        }
                        $final_products_arr[] = $tmp_arr;
                    }
                } else {
                    $data = (array) $art->article;
                    $tmp_arr = [];
                    foreach ($data as $K => $t) {
                        $tmp_arr[$K] = $t;
                    }

                    $final_products_arr[] = $tmp_arr;
                }
            } else {
                return false;
            }
        }
        $i = 0;
        $tabledata .= $this->balise('tr');
        foreach ($correspondances as $val) {
            ++$i;
            $tabledata .= $this->balise('td') . $val['xml_field'] . $this->balise('/td');
            if ($val['system_field'] == 'description') {
                $column = 'col' . $i;
                Db::getInstance()->Execute('ALTER TABLE  `' . _DB_PREFIX_ . 'pfi_import_tempdata_csv` CHANGE  `' .
                    pSQL($column) . '`  `' . pSQL($column) . '` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL');
            }
        }
        $tabledata .= $this->balise('/tr');
        $a = 0;
        $b = 0;
        // $queryrowarr = array();
        foreach ($final_products_arr as $val) {
            $tabledata .= $this->balise('tr', true);

            ++$a;
            $querycolarr = [$feed_id];
            $i = 1;
            foreach ($val as $key2 => $val2) {
                if (in_array($key2, $fldarray)) {

                    // $val2 = str_replace(', ', '#', $val2);
                    if (!in_array($key2, ['des', 'images', 'description', 'taille', 'couleur'])) {
                        // if (($key2 != 'images') && ($key2 != 'description')) {
                        $val2 = preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&\,@_-]/i', '', $val2);
                    }

                    $valori = $val2;
                    ++$b;
                    if (Tools::strlen($val2) > 100) {
                        $val2 = Tools::substr(strip_tags($val2), 0, 100) . '...';
                    }

                    if (empty($val2)) {
                        $val2 = '...';
                        $valori = '0';
                    }
                    $tabledata .= $this->balise('td') . $val2 . $this->balise('/td');
                    $querycolarr[] = '\'' . pSQL($valori) . '\'';
                }

                if (($i == 9) && ($key2 != 'poids')) {
                    $tabledata .= $this->balise('td') . 'Poids non fourni' . $this->balise('/td');
                    $querycolarr[] = '0';
                }
                ++$i;
            }
            // echo "=== DEBUG INSERTION ===\n";
            // echo "Colonnes attendues: " . count($t_col) . "\n";
            // echo "Valeurs fournies AVANT: " . count($querycolarr) . "\n";

            while (count($querycolarr) < count($t_col)) {
                $querycolarr[] = '\'0\'';
            }

            // echo "Valeurs fournies APRÈS: " . count($querycolarr) . "\n";

            $query = 'insert into `' . _DB_PREFIX_ .
                'pfi_import_tempdata_csv`(' . implode(', ', array_map('pSQL', $t_col)) . ') ' .
                'values (' . implode(', ', $querycolarr) . ')';

            // echo "Query: " . $query . "\n";

            if (count($querycolarr) == count($t_col)) {
                // echo "✅ INSERTION OK\n";
                Db::getInstance()->execute($query);
            }
            // echo "---\n";

            $tabledata .= $this->balise('/tr');
            // if ((int) $Submitlimit > 0 && $a == $Submitlimit) {
            //     break;
            // }
        }
        if ($id == 3) { // directimport
            return true;
        }
        $formatted_url = strstr($_SERVER['REQUEST_URI'], '&vc_mode=', true);
        $vc_redirect = ($formatted_url != '') ? $formatted_url : $_SERVER['REQUEST_URI'];
        $this->smarty->assign([
            'vc_redirect' => $vc_redirect,
            'base_url' => __PS_BASE_URI__,
            'secure_key' => Configuration::get('PI_SOFTWAREID'),
        ]);

        // rester sur le même template
        return;
    }

    /**
     * savePriceTestTmpData function.
     *
     * @param mixed $id
     * @param mixed $Submitlimit
     *
     * @return void
     */
    public function savePriceTestTmpData($id, $Submitlimit)
    {
        @ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');
        $feed_id = 1;
        if ($id == 2) { // directimport
            $feedurl = _PS_MODULE_DIR_ . 'pfproductimporter/import/' . Tools::getValue('import_txt');
            $Submitlimit = '10000';
        } else {
            $feedurl = Tools::getValue('vcfeedurl');
        }
        $fixcategory = Tools::getValue('selfixcategory');
        // get fields from pfi_import_feed_fields_csv
        $i = 0;
        $ii = 0;
        $tabledata = '';
        $t_col = [];
        $fldarray = [];
        $t_col[] = 'feed_id';
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('select `system_field`, xml_field  from `' .
            _DB_PREFIX_ . 'pfi_import_feedprice_fields_csv` where feed_id=' . (int) $feed_id . ' ORDER BY ID');
        // $filtercol = '';
        foreach ($result as $val) {
            ++$i;
            if ($val['system_field'] != 'Ignore Field') {
                ++$ii;
                $tabledata .= $this->balise('td') . $val['system_field'] . $this->balise('/td');
                $t_col[] = 'col' . $ii;
                $fldarray[] = $val['xml_field'];
            } else {
                $fldarray[] = '-----';
            }
        }
        $tabledata .= $this->balise('/tr');
        if (Tools::substr($feedurl, -4) == '.txt' || Tools::substr($feedurl, -4) == '.csv') {
            $handle = vccsv::openCsvFile($feedurl);
            if ($handle) {
                $result = Db::getInstance()->Execute('Delete from ' . _DB_PREFIX_ . 'pfi_import_pricetempdata_csv ');

                $separator = '\t';
                $final_products_arr = [];
                for ($current_line = 0; $line = fgetcsv($handle, 0, $separator); ++$current_line) {
                    if ($current_line == 0) {
                        continue;
                    }

                    $line = vccsv::utf8EncodeArray($line);
                    $tmp_arr = [];
                    foreach ($line as $K => $t) {
                        if ($fldarray[$K] != '-----') {
                            $tmp_arr[$fldarray[$K]] = $t;
                        }
                    }
                    $final_products_arr[] = $tmp_arr;
                }
            } else {
                return false;
            }
        }

        $tabledata .= $this->balise('tr');
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('select xml_field, system_field  from `' .
            _DB_PREFIX_ . 'pfi_import_feedprice_fields_csv` where feed_id=' . (int) $feed_id . ' ORDER BY ID');
        foreach ($result as $val) {
            if ($val['system_field'] != 'Ignore Field') {
                ++$i;
                $tabledata .= $this->balise('td') . $val['xml_field'] . $this->balise('/td');
            }
        }
        $tabledata .= $this->balise('/tr');
        $a = 0;
        $b = 0;

        foreach ($final_products_arr as $val) {
            $tabledata .= $this->balise('tr', true);
            ++$a;
            $querycolarr = [$feed_id];
            foreach ($val as $key2 => $val2) {
                if (in_array($key2, $fldarray)) {
                    // $val2 = str_replace(', ', '#', $val2);
                    $val2 = preg_replace('/[^a-z0-9 ]/i', '', $val2);
                    $valori = $val2;
                    ++$b;
                    if (Tools::strlen($val2) > 100) {
                        $val2 = Tools::substr(strip_tags($val2), 0, 100) . '...';
                    }

                    if (empty($val2)) {
                        $val2 = '...';
                    }

                    $tabledata .= $this->balise('td') . $val2 . $this->balise('/td');
                    $querycolarr[] = '\'' . pSQL($valori) . '\'';
                }
            }
            $query = 'insert into `' . _DB_PREFIX_ .
                'pfi_import_pricetempdata_csv`(' . implode(', ', array_map('pSQL', $t_col)) . ') ' .
                'values (' . implode(', ', $querycolarr) . ')';
            if (count($querycolarr) == count($t_col)) {
                Db::getInstance()->execute($query);
            }

            $tabledata .= $this->balise('/tr');
            if ((int) $Submitlimit > 0 && $a == $Submitlimit) {
                break;
            }
        }
        $formatted_url = strstr($_SERVER['REQUEST_URI'], '&vc_mode=', true);
        $vc_redirect = ($formatted_url != '') ? $formatted_url : $_SERVER['REQUEST_URI'];
        echo $this->pricetesttmpdata($vc_redirect, $tabledata, $a, $fixcategory);
    }

    public function pricetesttmpdata($vc_redirect, $tabledata, $a, $fixcategory)
    {
        $this->smarty->assign([
            'vc_redirect' => $vc_redirect,
            'tabledata' => $tabledata,
            'a' => $a,
            'fixcategory' => $fixcategory,
            'base_url' => __PS_BASE_URI__,
        ]);

        return $this->display(__FILE__, 'balise.tpl');
    }

    public function balise($balise, $css = false)
    {
        $this->smarty->assign([
            'balise' => $balise,
            'css' => $css,
        ]);

        return $this->display(__FILE__, 'balise.tpl');
    }

    /**
     * setproductcategory function.
     *
     * @param mixed &$product
     * @param mixed $default_language_id
     * @param mixed $languages
     *
     * @return void
     */
    public function setproductcategory(&$product, $default_language_id, $languages)
    {
        $output = '';
        if (isset($product->category) && is_array($product->category) && count($product->category)) {
            $product->id_category = []; // Reset default values array
            foreach ($product->category as $value) {
                if (is_numeric($value)) {
                    if (Category::categoryExists((int) $value)) {
                        $product->id_category[] = (int) $value;
                    }
                } else {
                    // if (is_numeric(Tools::substr($value, 0, 1))) {
                    //     $value = 'Marque '.$value;
                    // }
                    $value = str_replace('>', '-', $value);
                    $value = str_replace('<', '-', $value);
                    $value = str_replace('#', '-', $value);
                    $value = str_replace('=', '-', $value);
                    $value = str_replace(';', '-', $value);
                    $value = str_replace('{', '-', $value);
                    $value = str_replace('}', '-', $value);
                    $value = trim($value);

                    $category = Category::searchByName($default_language_id, $value, true);

                    if (is_array($category) && is_numeric($category['id_category'])) {
                        $product->id_category[] = (int) $category['id_category'];
                    } else {
                        $catnamearray = [];
                        $catlinkarray = [];
                        $category_to_create = new Category();

                        foreach ($languages as $lang) {
                            $catnamearray[$lang['id_lang']] = $value;
                        }

                        $category_to_create->name = $catnamearray;

                        $category_to_create->active = 1;
                        $category_to_create->id_parent = 2;
                        $category_link_rewrite = Tools::link_rewrite($category_to_create->name[$default_language_id]);
                        foreach ($languages as $lang) {
                            $catlinkarray[$lang['id_lang']] = $category_link_rewrite;
                        }

                        $category_to_create->link_rewrite = $catlinkarray;

                        if (!empty($category_to_create->name)) {
                            if ($category_to_create->add()) {
                                $product->id_category[] = (int) $category_to_create->id;
                                $this->arrcat[] = $value;
                                $output = 'Creation de la categorie ' . $value . "\n";
                            }
                        }
                    }
                }
            }
        }
        $product->id_category_default = isset($product->id_category[0]) ? (int) $product->id_category[0] : '';

        return $output;
    }

    /**
     * setmanufacturer function.
     *
     * @param mixed $Manufacturer
     * @param mixed $product
     *
     * @return void
     */
    public function setmanufacturer($Manufacturer, $product)
    {
        if (Manufacturer::getIdByName(Tools::strtolower($Manufacturer))) {
            $product->id_manufacturer = Manufacturer::getIdByName($Manufacturer);
        } elseif (Manufacturer::getIdByName(Tools::strtoupper($Manufacturer))) {
            $product->id_manufacturer = Manufacturer::getIdByName($Manufacturer);
        } elseif (Manufacturer::getIdByName(trim($Manufacturer))) {
            $product->id_manufacturer = Manufacturer::getIdByName($Manufacturer);
        } else {
            if ($Manufacturer != '' && $Manufacturer != '0') {
                $manufacturer = new Manufacturer();
                $manufacturer->name = $Manufacturer;
                $manufacturer->active = 1;
                if ($manufacturer->add()) {
                    $product->id_manufacturer = (int) $manufacturer->id;
                } else {
                    echo $this->l('manufacturer error') . "\n";
                }
            }
        }
    }

    /**
     * saveprices function.
     *
     * @param mixed $wholesale_price
     * @param mixed $price
     * @param mixed $formulaprice
     * @param mixed $formula_op
     * @param mixed $formula_val
     * @param mixed &$product
     *
     * @return void
     */
    public function saveprices($wholesale_price, $price, $formulaprice, $formula_op, $formula_val, &$product)
    {
        if (isset($wholesale_price) && $wholesale_price > 0) {
            $product->wholesale_price = str_replace(', ', '.', $wholesale_price);
            $product->wholesale_price = str_replace('#', '.', $product->wholesale_price);
            $product->wholesale_price = str_replace('R', '', $product->wholesale_price);
            if ((int) $formulaprice == 6 && $formula_op && $formula_val) {
                $product->wholesale_price = number_format($product->wholesale_price, $formulaprice, '.', '');
            }
            $product->wholesale_price = number_format($product->wholesale_price, 6, '.', '');
        } else {
            $product->wholesale_price = '0.000000';
        }

        if (isset($price)) {
            $product->price = str_replace(', ', '.', $price);
            $product->price = str_replace('#', '.', $product->price);
            $product->price = str_replace('R', '', $product->price);
            if ($product->price != '') {
                $product->price = number_format($product->price, 6, '.', '');
            } else {
                $product->price = '0.000000';
            }
        }
        $product->update();
    }

    /**
     * savenameanddescription function.
     *
     * @param mixed $pname
     * @param mixed $description
     * @param mixed $description_short
     * @param mixed $languages
     * @param mixed &$product
     *
     * @return void
     */
    public function savenameanddescription($pname, $description, $description_short, $languages, &$product)
    {
        if (!is_null($description)) {
            $description = str_replace(["\n", "\r"], ['<br />', ''], $description);
        }
        if (!is_null($description_short)) {
            $description_short = str_replace(["\n", "\r"], ['<br />', ''], $description_short);
        }
        $namearray = [];
        $description_shortarray = [];
        $description_array = [];
        foreach ($languages as $lang) {
            if (!is_null($pname)) {
                $namearray[$lang['id_lang']] = $pname;
            }
            if (!is_null($description)) {
                $description_array[$lang['id_lang']] = stripslashes(pSQL($description, true));
            }
            if (!is_null($description_short)) {
                $description_shortarray[$lang['id_lang']] = stripslashes(pSQL($description_short, true));
            }
        }
        if (!empty($namearray)) {
            $product->name = $namearray;
        }
        if (!empty($description_shortarray)) {
            $product->description_short = $description_shortarray;
        }
        if (!empty($description_array)) {
            $product->description = $description_array;
        }
        // $product->update();
    }

    /**
     * getxmlfields function.
     *
     * @return void
     */
    public function getxmlfields()
    {
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('select system_field, xml_field  from `' .
            _DB_PREFIX_ . 'pfi_import_feed_fields_csv` where feed_id = 1 ORDER BY id');
        $data = [];
        foreach ($result as $val) {
            if ($val['system_field'] != 'Ignore Field') {
                $data[$val['system_field']] = $val['xml_field'];
            }
        }

        return $data;
    }

    /**
     * @edit Definima
     * Gestion des soldes
     *
     * @return string
     */
    public function salesSyncCron()
    {
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $output = '';

        // Réinitialisation des tarifs soldés du site
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ .
            'specific_price` WHERE id_specific_price_rule=0');
        $output .= 'Reset tarifs soldes' . "\n";
        try {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            // Vérification période de soldes
            if ($sc->isSoldesEnCours($softwareid)) {
                $output .= 'Soldes en cours';
                // Récupère les points de vente actifs
                $pdv_actifs = $sc->getPdvsActifs($softwareid);
                if (is_array($pdv_actifs->idPdv)) {
                    $pdvs = $pdv_actifs->idPdv;
                } else {
                    $pdvs = [$pdv_actifs->idPdv];
                }
                // Récupère les pdv configurés dans l'admin PS
                $configured_pdv = Configuration::get('PI_SYNC_SALES_PDV');
                $sync_pdv = [];
                if ($configured_pdv && trim($configured_pdv) != '') {
                    $sync_pdv = explode(',', $configured_pdv);
                    $sync_pdv = array_map('strtolower', $sync_pdv);
                    $sync_pdv = array_map('trim', $sync_pdv);
                }
                foreach ($pdvs as $pdv) {
                    if (!empty($sync_pdv) && !in_array($pdv, $sync_pdv)) {
                        continue;
                    }
                    $output .= ' sur ' . $pdv . '\n';
                    // Récupère tous les articles soldés
                    $all_article_solde = $sc->getAllTarifsSoldesFor($softwareid, $pdv);

                    if (isset($all_article_solde->tarifsSoldes) && is_array($all_article_solde->tarifsSoldes)) {
                        foreach ($all_article_solde->tarifsSoldes as $art) {
                            $reference = $art->codeArt;

                            // Récupère le produit sur Rezomatic
                            // $rz_product = $sc->getArticleFromCode($softwareid, $reference);

                            // Vérifie si c'est une déclinaison d'un produit principal
                            // $combination = [];
                            // if (isset($rz_product->codeDeclinaison) && $rz_product->codeDeclinaison
                            //     && $rz_product->codeDeclinaison != '0' && $rz_product->codeDeclinaison != $reference) {
                            $combination = CombinationVccsv::getCombinationByReference(
                                $reference,
                                Configuration::get('PI_PRODUCT_REFERENCE')
                            );
                            // }

                            // Récupère le produit PS en fonction de la référence
                            $id_product_attribute = 0;
                            if ($combination) {
                                $id_product = $combination['id_product'];
                                $id_product_attribute = $combination['id_product_attribute'];
                            } else {
                                $id_product = ProductVccsv::getProductIdByRefRezomatic($reference);
                            }

                            if ($id_product && is_numeric($id_product) /* && $rz_product->codeArt == $reference */) {
                                // Réduction (en %)
                                if (empty($art->tarif)) {
                                    $impact_reduc = 0;
                                } else {
                                    $impact_reduc = round(1 - ($art->tarifSolde / $art->tarif), 6);
                                    if ($impact_reduc < 0) {
                                        $impact_reduc = 0;
                                    }
                                }

                                $product = new Product($id_product);

                                // Récupère les shops du produit
                                $shops = [];
                                if (isset($product->shop)) {
                                    $product_shop = explode(';', $product->shop);
                                    foreach ($product_shop as $shop) {
                                        $shop = trim($shop);
                                        if (!is_numeric($shop)) {
                                            $shop = ShopGroup::getIdByName($shop);
                                        }
                                        $shops[] = $shop;
                                    }
                                }
                                if (empty($shops)) {
                                    $shops = Shop::getContextListShopID();
                                }

                                foreach ($shops as $id_shop) {
                                    // Récupère un potentiel prix spécifique
                                    $specific_price = SpecificPrice::getSpecificPrice(
                                        $product->id,
                                        $id_shop,
                                        0,
                                        0,
                                        0,
                                        1,
                                        $id_product_attribute,
                                        0,
                                        0,
                                        0
                                    );

                                    if (is_array($specific_price) && isset($specific_price['id_specific_price'])) {
                                        $specific_price = new SpecificPrice($specific_price['id_specific_price']);
                                    } else {
                                        $specific_price = new SpecificPrice();
                                    }
                                    $specific_price->id_product = (int) $product->id;
                                    $specific_price->id_specific_price_rule = 0;
                                    $specific_price->id_shop = $id_shop;
                                    $specific_price->id_currency = 0;
                                    $specific_price->id_country = 0;
                                    $specific_price->id_group = 0;
                                    $specific_price->price = -1;
                                    $specific_price->id_customer = 0;
                                    $specific_price->from_quantity = 1;

                                    $specific_price->reduction = $impact_reduc;
                                    $specific_price->reduction_type = 'percentage';
                                    $specific_price->from = '0000-00-00 00:00:00';
                                    $specific_price->to = '0000-00-00 00:00:00';
                                    $specific_price->id_product_attribute = $id_product_attribute;
                                    try {
                                        if (!$specific_price->save()) {
                                            if ($combination) {
                                                $output .= 'Soldes update error for combination ' .
                                                    $id_product_attribute . ' of product ' . $product->id . ' ' .
                                                    $reference . ' : ' .
                                                    $art->tarif . ' -> ' . $art->tarifSolde . ' (' . ($impact_reduc * 100) .
                                                    '%)' . "\n";
                                            } else {
                                                $output .= 'Soldes update error for product ' . $product->id .
                                                    ' ' . $reference . ' : ' .
                                                    $art->tarif . ' -> ' . $art->tarifSolde . ' (' .
                                                    ($impact_reduc * 100) . '%)' . "\n";
                                            }
                                        } else {
                                            if ($combination) {
                                                $output .= 'Soldes update for combination ' . $id_product_attribute .
                                                    ' of product ' . $product->id . ' ' . $reference . ' : ' .
                                                    $art->tarif . ' -> ' . $art->tarifSolde . ' (' . ($impact_reduc * 100) .
                                                    '%)' . "\n";
                                            } else {
                                                $output .= 'Soldes update for product ' . $product->id . ' ' . $reference .
                                                    ' : ' . $art->tarif . ' -> ' . $art->tarifSolde . ' (' . ($impact_reduc * 100) .
                                                    '%)' . "\n";
                                            }
                                        }
                                    } catch (Exception $e) {
                                        $this->dump($e);
                                        $this->dump($specific_price);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (SoapFault $exception) {
            $output .= Vccsv::logError($exception);
        }

        return $output;
    }

    /**
     * @edit Definima
     * Mise à jour status commandes
     *
     * @return string
     */
    public function orderStatusSyncCron()
    {
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $output = '';

        $sc = new SoapClient($feedurl, ['keep_alive' => false]);

        // Récupère les commandes dont les statuts sont en attente
        $get_status_orders = [
            1, // En attente de paiement par chèque
            10, // En attente de virement bancaire
        ];
        if ($this->isPrestashop15()) {
            $get_status_orders[] = 11; // En attente de paiement PayPal
        }
        if ($this->isPrestashop16()) {
            $get_status_orders[] = 11; // En attente de paiement PayPal
            $get_status_orders[] = 13; // En attente de réapprovisionnement (non payé)
            $get_status_orders[] = 14; // En attente de paiement à la livraison
        }
        if ($this->isPrestashop17()) {
            $get_status_orders[] = 12; // En attente de réapprovisionnement (non payé)
            $get_status_orders[] = 13; // En attente de paiement à la livraison
        }

        // Nouveau status
        $new_status_orders = [
            'preparation' => 3,
            'annule' => 6,
        ];

        $id_orders = OrderVccsv::getOrderIdsByStatus($get_status_orders);

        $OrderHistory = new OrderHistory();

        foreach ($id_orders as $o) {
            try {
                $api_orderid = Db::getInstance()->getValue('select api_orderid from ' . _DB_PREFIX_ .
                    'pfi_order_apisync where system_orderid=' . (int) $o['id_order']);

                if (!$api_orderid) {
                    $output .= 'Correspondance commande num ' . $o['id_order'] . ' introuvable\n';
                    continue;
                }

                $rz_status = $sc->getCommandesStatuts($softwareid, $api_orderid);

                if (isset($rz_status->commandeState)) {
                    if (is_array($rz_status->commandeState)) {
                        $last_state = end($rz_status->commandeState);
                    } else {
                        $last_state = $rz_status->commandeState;
                    }
                }

                if (!$last_state) {
                    continue;
                }

                $new_status = 0;
                switch ($last_state->statut) {
                    // RZ Terminé => PS En Préparation
                    case 2:
                        $new_status = $new_status_orders['preparation'];
                        break;
                    // Annulée
                    case 3:
                    case 4:
                        $new_status = $new_status_orders['annule'];
                        break;
                }

                if (!$new_status) {
                    continue;
                }

                // Update status Prestashop
                $OrderHistory->changeIdOrderState($new_status, $o['id_order']);
                $output .= 'Statut commande num ' . $o['id_order'] . ' mis a jour => ' . $new_status;
            } catch (SoapFault $exception) {
                $output .= Vccsv::logError($exception);
            }
        }

        return $output;
    }

    /**
     * @edit Definima
     * Check si PS 1.5
     *
     * @return bool
     */
    public function isPrestashop15()
    {
        return version_compare(_PS_VERSION_, '1.6.0', '<') === true;
    }

    /**
     * @edit Definima
     * Check si PS 1.6
     *
     * @return bool
     */
    public function isPrestashop16()
    {
        return version_compare(_PS_VERSION_, '1.7.0', '<') === true;
    }

    /**
     * @edit Definima
     * Check si PS 1.7
     *
     * @return bool
     */
    public function isPrestashop17()
    {
        return version_compare(_PS_VERSION_, '1.6', '>') === true;
    }

    /**
     * @edit Definima
     * Check si PS 1.7
     *
     * @return bool
     */
    public function isPrestashop8()
    {
        return version_compare(_PS_VERSION_, '8', '>') === true;
    }

    /**
     * @edit Definima
     * Juste un var_dump entouré de pre
     */
    protected function dump()
    {
        if (func_num_args()) {
            echo '<pre>';
            foreach (func_get_args() as $arg) {
                var_dump($arg);
                echo PHP_EOL;
            }
            echo '</pre>';
        }
    }

    /**
     * hookDisplayShoppingCart function.
     * Mise à jour des stocks à l'affichage du panier
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookDisplayShoppingCart($params)
    {
        $allow_cart_stock_update = Configuration::get('SYNC_STOCK_PDV');
        if ($allow_cart_stock_update == 1) {
            $this->stockSyncCron();
        }
    }
}
