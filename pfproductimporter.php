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
        $this->version = '2.7.2';
        $this->author = 'Definima/TGM';
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
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
            && $this->registerHook('actionOrderStatusUpdate')
            && $this->registerHook('actionProductUpdate')
            && $this->registerHook('actionPDFInvoiceRender')
            && $this->registerHook('displayShoppingCart')
            // && $this->registerHook('actionProductDelete')
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
            $debut = time();

            try {
                // MODE AJAX : un seul appel de récupération
                if (Tools::getValue('ajax_mode')) {
                    $timestamp = Tools::getValue('timestamp');
                    $result = $this->saveTestTmpData(0, 0, $timestamp);
                    die($result);
                }

                // MODE NORMAL : comme avant (tout d'un coup)
                $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                $timestamp_old = '2020-01-01 00:00:00';
                $art = $sc->getNewArticles($softwareid, $timestamp_old);

                if (!empty($art->article)) {
                    $articles = is_array($art->article) ? $art->article : [$art->article];
                    $log_output = '<u>IMPORT MANUEL - ' . count($articles) . ' articles a traiter</u>' . "\n";

                    $this->saveTestTmpData(0, $articles);
                    $this->countimport();
                    $result = $this->finalimport('', '', 0);
                    $log_output .= $result;
                    $lotOutput = ProductVccsv::importLot();
                    $log_output .= $lotOutput;

                    $fin = time();
                    $log_output .= "\n" . 'Import manuel termine en ' . ($fin - $debut) . 's.' . "\n";
                    $this->mylog($log_output);

                    return $output . $result;
                }
            } catch (Exception $e) {
                $this->mylog("ERREUR Import manuel : " . $e->getMessage());
                return "Erreur : " . $e->getMessage();
            }
        }
        if (Tools::isSubmit('sync_sales_now')) {
            // Verifier que la synchronisation des soldes est activee
            if (Configuration::get('PI_ALLOW_PRODUCTSALESIMPORT') != '1') {
                $this->mylog("Synchronisation manuelle des soldes : fonctionnalite desactivee");
                return "Erreur : La synchronisation des soldes n'est pas activee.";
            }

            $debut = time();

            try {
                // Log de debut
                $log_output = '<u>SYNCHRONISATION MANUELLE DES SOLDES</u>' . "\n";
                $log_output .= 'Declenchee le ' . date('Y-m-d H:i:s') . "\n";

                // Appeler la fonction de synchronisation des soldes
                $result = $this->salesSyncCron();
                $log_output .= $result;

                // Timer final
                $fin = time();
                $log_output .= "\n" . 'Synchronisation des soldes terminee en ' . ($fin - $debut) . 's.' . "\n";

                // Sauvegarder dans les logs
                $this->mylog($log_output);

                // Si requête AJAX, retourner juste les donnees
                if (Tools::getValue('ajax') || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    die($result);
                }

                return $output . $result;
            } catch (Exception $e) {
                $error_message = "ERREUR Synchronisation manuelle des soldes : " . $e->getMessage();
                $this->mylog($error_message);

                // Si requête AJAX, retourner juste l'erreur
                if (Tools::getValue('ajax') || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    die($error_message);
                }

                return "Erreur : " . $e->getMessage();
            }
        }

        // Gestion de la soumission du formulaire d'association des paiements
        if (Tools::isSubmit('SubmitSavePaymentMappings')) {
            if ($this->savePaymentMappings()) {
                $output .= $this->displayConfirmation('Les correspondances de paiement ont été enregistrées avec succès.');
            } else {
                $output .= $this->displayError('Erreur lors de l\'enregistrement des correspondances de paiement.');
            }
        }
        // Gestion des differentes etapes de la configuration / importation
        if (Tools::isSubmit('SubmitSaveMainSettings')) {
            // 1. Save Main Settings
            if ($this->saveMainSettingsForm()) {
                $output = $this->displayConfirmation($this->l('Settings updated'));
                if (Tools::getValue('PI_ALLOW_PRODUCTIMPORT')) {
                    $output .= $this->renderMainSettingsForm();
                } elseif (Tools::getValue('PI_ALLOW_PRODUCTEXPORT')) {
                    $output .= $this->displayConfirmation(
                        'L\'exportation des produits est activee. Vous pouvez maintenant exporter votre catalogue.'
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
                $output = $this->displayConfirmation('Exportation du catalogue terminee.');
            } else {
                $output = $this->displayError('Erreur lors de l\'exportation du catalogue : ');
            }
            $output .= $this->renderMainSettingsForm();

            return $output;
        } elseif (Tools::isSubmit('SubmitExportorder')) {
            if ($this->saveMainSettingsForm()) {
                $output = $this->displayConfirmation('Parametres des commandes enregistres avec succes.');
            }
            $output .= $this->renderMainSettingsForm();
            // TODO : Export d'une commande, a supprimer ?
            // $order_id = Tools::getValue('txtorderid');
            // $output .= OrderVccsv::orderSync($order_id);
            return $output;
        } elseif (Tools::isSubmit('SubmitImportcustomer')) {
            // TODO : Bloc a supprimer ?
            // $output = CustomerVccsv::importCustomer();
            if (!empty($output)) {
                $output = $this->displayError($output);
            } else {
                $output = $this->displayConfirmation($this->l('Parametres des clients enregistres avec succes.'));
            }
            return $output;
        } elseif (Tools::isSubmit('SubmitExportcustomer')) {
            // TODO : Bloc a supprimer ?
            $customerid = Tools::getValue('txtcustomerid');
            // $output = CustomerVccsv::customerSync($customerid);

            return $output;
        } elseif (Tools::isSubmit('SubmitExportproduct')) {
            // TODO : Bloc a supprimer ?
            $productid = Tools::getValue('txtproductid');
            $output = ProductVccsv::productSync($productid);
            $output = $this->renderMainSettingsForm();

            return $output;
        } elseif (Tools::isSubmit('submitgotomain')) {
            // TODO : Import tache cron ?
            $url = AdminController::$currentIndex . '&modulename=' . $this->name . '&configure=' . $this->name .
                '&token=' . Tools::getAdminTokenLite('AdminModules') . '&tab_module=payments_gateways';
            Tools::redirectAdmin($url);
        } elseif (Tools::isSubmit('submitStateMapping')) {
            // Gestion de la soumission du formulaire d'association des etats
            if ($this->saveStateMapping()) {
                $output = $this->displayConfirmation('Les associations des etats de commandes ont ete enregistrees avec succes.');
            } else {
                $output = $this->displayError('Une erreur est survenue lors de l\'enregistrement des associations.');
            }
            $output .= $this->renderMainSettingsForm();
            return $output;
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
     * hookActionProductAdd function.
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionProductAdd($params)
    {
        if (!empty($params['id_product'])) {
            $output = ProductVccsv::productSync($params['id_product']);
            $this->mylog($output);
        }
    }

    /**
     * hookActionProductUpdate function.
     *
     * @edit Definima
     * La creation ou la mise a jour se fait sur un produit deja existant
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionProductUpdate($params)
    {
        if (!empty($params['id_product'])) {
            $output = ProductVccsv::productSync($params['id_product']);
            // Cas particulier Prestashop 1.6
            if (($this->isPrestashop16()) && (Configuration::get('PI_ALLOW_PRODUCTEXPORT') == 1)) {
                $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
                $output .= CombinationVccsv::syncCombination(
                    $params['id_product'],
                    Tools::getValue('attribute_' . $reference_field),
                    Tools::getValue('attribute_ean13'),
                    Tools::getValue('attribute_wholesale_price'),
                    Tools::getValue('attribute_price_impact'),
                    Tools::getValue('attribute_priceTI'),
                    Tools::getValue('attribute_weight'),
                    Tools::getValue('attribute_combination_list')
                );
            }
            $this->mylog($output);
        }
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
        // Récupérer le contexte
        $context = Context::getContext();
        // Vérifier si un client est connecté pour la mise a jour des bons
        if ($context->customer->isLogged()) {
            $output = CustomerVccsv::customerSync($context->customer->id);
            self::mylog($output);
        }
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
        // Récupérer le contexte
        $context = Context::getContext();
        // Vérifier si un client est connecté pour la mise a jour des bons
        if ($context->customer->isLogged()) {
            $output = CustomerVccsv::customerSync($context->customer->id);
            self::mylog($output);
        }
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
        if (!empty($params['object']->id)) {
            $output = CustomerVccsv::customerSync($params['object']->id);
            $this->mylog($output);
        }
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
        // Récupérer le contexte
        $context = Context::getContext();
        // Vérifier si un client est connecté pour la mise a jour des bons
        if ($context->customer->isLogged()) {
            $output = CustomerVccsv::loyaltySync($context->customer->id, $context->customer->email);
            self::mylog($output);
        }
    }

    /**
     * hookDisplayShoppingCart function.
     * Mise a jour des stocks a l'affichage du panier
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookDisplayShoppingCart($params)
    {
        // Récupérer le contexte
        $context = Context::getContext();
        // Vérifier si un client est connecté pour la mise a jour des bons
        if ($context->customer->isLogged()) {
            $output = CustomerVccsv::loyaltySync($context->customer->id, $context->customer->email);
            self::mylog($output);
        }
        // Mise a jour des stocks ?
        if (Configuration::get('PI_ALLOW_STOCKIMPORT') == 1) {
            $output = $this->stockSyncCron();
            self::mylog($output);
        }
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
        // $this->mylog("hookActionValidateOrder:".print_r($params['order'], true));
        if (isset($params['order']) && isset($params['order']->id)) {
            $output = OrderVccsv::orderSync($order->id);
            $this->mylog($output);
        }
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
        // $this->mylog("hookActionOrderStatusUpdate:".print_r($params, true));
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
     * hookActionPDFInvoiceRender function.
     * Remplacement PDF Facture par celle de Rezomatic
     *
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionPDFInvoiceRender($params)
    {
        $collection = $params['order_invoice_list'];
        // On prend la première facture (généralement il n'y en a qu'une par commande)
        $invoices = $collection->getResults();
        if (!empty($invoices)) {
            /** @var OrderInvoice $invoice */
            $invoice = reset($invoices);
            $id_order = (int)$invoice->id_order;
            // Vérification existance de la facture Rezomatic
            $factRezo = Db::getInstance()->getValue('select urlInvoice from ' . _DB_PREFIX_ .
                'pfi_order_apisync where system_orderid=' . (int) $id_order);
            if (!empty($factRezo)) {
                if (!Tools::usingSecureMode())
                    $factRezo = str_replace('https://', 'http://', $factRezo);
                $pdfContent = @file_get_contents($factRezo);
                header("Content-disposition: attachment; filename=invoice-" . $id_order . ".pdf");
                header("Content-Type: application/force-download");
                header("Content-Transfer-Encoding: binary\n");
                header("Content-Length: " . strlen($pdfContent));
                header("Pragma: no-cache");
                header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
                header("Expires: 0");
                echo $pdfContent;
                exit;
            }
        }
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

                    // Pour chaque article retourne
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
                                $output .= 'ERREUR mise a jour stock article ' . $reference . ' (' . $id_product . ') : ' .
                                    $stock_available . ' -> ' . $new_stock . "\n";
                            } else {
                                $output .= 'Stock article ' . $reference . ' mis a jour : ' .
                                    $stock_available . ' -> ' . $new_stock . "\n";
                            }
                        }

                        /**
                         * @edit Definima
                         * Recupere les declinaisons qui pourraient avoir cette reference
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
                                    $output .= 'ERREUR mise a jour declinaison ' . $reference . ' (' . $id_product . ') : ' .
                                        $stock_available . ' -> ' . $new_stock . "\n";
                                } else {
                                    $output .= 'Stock declinaison ' . $reference . ' mis a jour : ' .
                                        $stock_available . ' -> ' . $new_stock . "\n";
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
        // Si l'import des produits n'est pas active, pas de suppression
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
                            $output .= 'Article deleted:' . $codeArt . '\n';
                        }
                    }

                    /**
                     * @edit Definima
                     * Recupere les declinaisons qui pourraient avoir cette reference
                     */
                    $combinations = CombinationVccsv::getCombinationsByReference(
                        $codeArt,
                        Configuration::get('PI_PRODUCT_REFERENCE')
                    );

                    if ($combinations) {
                        foreach ($combinations as $c) {
                            $combination = new Combination($c['id_product_attribute']);
                            if ($combination && $combination->delete()) {
                                $output .= 'Declinaison deleted:' . $codeArt . "\n";
                            }
                        }
                    }
                }
            }
        } catch (SoapFault $exception) {
            $output = Vccsv::logError($exception);
        }
        return $output;
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
         * Ne fait rien car on ne veut pas supprimer les clients de PS s'ils sont supprimes de Rezomatic.
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
        // Recuperer toutes les configurations
        $config_values = array(
            'SYNC_CSV_FEEDURL' => Configuration::get('SYNC_CSV_FEEDURL'),
            'PI_SOFTWAREID' => Configuration::get('PI_SOFTWAREID'),
            'PI_CRON_TASK' => Configuration::get('PI_CRON_TASK'),
            'PI_ALLOW_STOCKIMPORT' => Configuration::get('PI_ALLOW_STOCKIMPORT'),
            'SYNC_STOCK_PDV' => Configuration::get('SYNC_STOCK_PDV'),
            'PI_ALLOW_PRODUCTIMPORT' => Configuration::get('PI_ALLOW_PRODUCTIMPORT'),
            'PI_ALLOW_PRODUCTIMAGEIMPORT' => Configuration::get('PI_ALLOW_PRODUCTIMAGEIMPORT'),
            'PI_UPDATE_DESIGNATION' => Configuration::get('PI_UPDATE_DESIGNATION'),
            'PI_ALLOW_PRODUCTSALESIMPORT' => Configuration::get('PI_ALLOW_PRODUCTSALESIMPORT'),
            'PI_SYNC_SALES_PDV' => Configuration::get('PI_SYNC_SALES_PDV'),
            'PI_ACTIVE' => Configuration::get('PI_ACTIVE'),
            'PI_ALLOW_PRODUCTEXPORT' => Configuration::get('PI_ALLOW_PRODUCTEXPORT'),
            'PI_EXPORT_ATTRIBUTES_IN_DESIGNATION' => Configuration::get('PI_EXPORT_ATTRIBUTES_IN_DESIGNATION'),
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

        // LOGS - Variables avec pagination et recherche
        $today = date('Y-m-d');
        $logs_today_url = $this->_path . 'logs_rezomatic' . $today . '.html';
        $logs_today_file = dirname(__FILE__) . '/logs_rezomatic' . $today . '.html';
        $logs_today_exists = file_exists($logs_today_file);
        $logs_today_size = $logs_today_exists ? round(filesize($logs_today_file) / 1024, 2) : 0;

        // Parametres de recherche et pagination
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

            // Trier par date decroissante
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
            '02' => 'Fevrier',
            '03' => 'Mars',
            '04' => 'Avril',
            '05' => 'Mai',
            '06' => 'Juin',
            '07' => 'Juillet',
            '08' => 'Août',
            '09' => 'Septembre',
            '10' => 'Octobre',
            '11' => 'Novembre',
            '12' => 'Decembre'
        ];

        // Variables pour le mapping
        $feedid = 1;
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $fixcategory = Tools::getValue('selfixcategory', '');

        // Creer newproductfields (logique de buildMappingFieldsForm)
        $productfields = Vccsv::getxiProductFields();
        if (!is_array($productfields)) {
            $productfields = [];
        }
        $productfields[] = 'image_url';
        $productfields[] = 'product_url';
        $productfields[] = 'manufacturer';
        $productfields[] = 'available_date';
        $productfields[] = 'combination_reference';

        // Preparer les variables pour les logs
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

        // Preparer les donnees pour le mapping si necessaire
        // Si on a une URL de feed, recuperer les donnees pour le mapping
        $raw_products_arr = array();
        $final_products_arr = array();

        if ($feedurl && Tools::strlen($feedurl) > 0) {
            // Recuperer les donnees pour le mapping des champs
            $raw_products_arr = $this->getFieldsFromFeed($feedurl);

            // Recuperer les categories du feed
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

        // Determiner l'onglet actif
        $active_tab = 'general';
        if (Tools::getValue('active_tab')) {
            $active_tab = Tools::getValue('active_tab');
        }
        // Recuperer les categories Prestashop
        $cats = Category::getNestedCategories(null, 1, true);

        $categoryOptionsArray = $this->buildCategoryOptionsArray($cats, 0);

        // Precalcul des categories mappees
        $mappedCategories = [];
        if (!empty($final_products_arr)) {
            foreach ($final_products_arr as $category_name) {
                $row = Vccsv::getFeedByVal($category_name, $feedid);
                $mappedCategories[$category_name] = $row ? $row : null;
            }
        }
        $source_states = $this->getRezoStates();
        $prestashop_states = $this->getPrestashopStates();
        $existing_state_mapping = $this->getExistingStateMapping();

        // Preparer toutes les variables pour le template
        $this->context->smarty->assign(array(
            'token' => Tools::getAdminTokenLite('AdminModules'),
            'configure' => $this->name,
            'tab_module' => 'payments_gateways',
            'current_index' => AdminController::$currentIndex,
            'module_dir' => $this->_path,
            'cats' => $cats,
            'cron_url' => Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . 'modules/pfproductimporter/cron_crontab.php?secure_key=' . Configuration::get('PI_SOFTWAREID'),
            'categoryOptionsArray' => $categoryOptionsArray,
            'mappedCategories' => $mappedCategories,
            'module_name' => $this->name,
            'ps_version' => _PS_VERSION_,
            'fields_value' => array_merge($config_values, array('state_mapping' => $existing_state_mapping)),
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
            'source_states' => $source_states,
            'prestashop_states' => $prestashop_states,
            'state_mapping' => $existing_state_mapping,
            'fixcategory' => $fixcategory,
            'base_url' => __PS_BASE_URI__,
            'secure_key' => Configuration::get('PI_SOFTWAREID'),
            'pi_softwareid' => Configuration::get('PI_SOFTWAREID'),            // Variables pour le mapping des champs
            'newproductfields' => $newproductfields,
            'attrgrp' => $attrgrp,
            'payment_mappings' => $this->loadPaymentMappings(),
            'active_payment_modules' => $this->getActivePaymentModules(),
        ));

        return $this->display(__FILE__, 'views/templates/admin/main_settings.tpl');
    }

    // Construire le tableau d'options de categories
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
     * Sauvegarder les parametres principaux avec logging des modifications
     *
     * @return bool
     */
    public function saveMainSettingsForm()
    {
        // Recuperer les anciennes valeurs pour comparaison
        $oldValues = array(
            'SYNC_CSV_FEEDURL' => Configuration::get('SYNC_CSV_FEEDURL'),
            'PI_SOFTWAREID' => Configuration::get('PI_SOFTWAREID'),
            'PI_CRON_TASK' => Configuration::get('PI_CRON_TASK'),
            'PI_ALLOW_STOCKIMPORT' => Configuration::get('PI_ALLOW_STOCKIMPORT'),
            'SYNC_STOCK_PDV' => Configuration::get('SYNC_STOCK_PDV'),
            'PI_ALLOW_PRODUCTIMPORT' => Configuration::get('PI_ALLOW_PRODUCTIMPORT'),
            'PI_ALLOW_PRODUCTIMAGEIMPORT' => Configuration::get('PI_ALLOW_PRODUCTIMAGEIMPORT'),
            'PI_UPDATE_DESIGNATION' => Configuration::get('PI_UPDATE_DESIGNATION'),
            'PI_ALLOW_PRODUCTSALESIMPORT' => Configuration::get('PI_ALLOW_PRODUCTSALESIMPORT'),
            'PI_SYNC_SALES_PDV' => Configuration::get('PI_SYNC_SALES_PDV'),
            'PI_ACTIVE' => Configuration::get('PI_ACTIVE'),
            'PI_ALLOW_PRODUCTEXPORT' => Configuration::get('PI_ALLOW_PRODUCTEXPORT'),
            'PI_EXPORT_ATTRIBUTES_IN_DESIGNATION' => Configuration::get('PI_EXPORT_ATTRIBUTES_IN_DESIGNATION'),
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

        // Sauvegarder les nouvelles valeurs (code original)
        Configuration::updateValue('SYNC_CSV_FEEDURL', trim(Tools::getValue('SYNC_CSV_FEEDURL')));
        Configuration::updateValue('PI_SOFTWAREID', Tools::getValue('PI_SOFTWAREID'));
        Configuration::updateValue('PI_CRON_TASK', Tools::getValue('PI_CRON_TASK'));
        Configuration::updateValue('PI_ALLOW_STOCKIMPORT', Tools::getValue('PI_ALLOW_STOCKIMPORT'));
        Configuration::updateValue('SYNC_STOCK_PDV', Tools::getValue('SYNC_STOCK_PDV'));
        Configuration::updateValue('PI_ALLOW_PRODUCTIMPORT', Tools::getValue('PI_ALLOW_PRODUCTIMPORT'));
        Configuration::updateValue('PI_ALLOW_PRODUCTIMAGEIMPORT', Tools::getValue('PI_ALLOW_PRODUCTIMAGEIMPORT'));
        Configuration::updateValue('PI_UPDATE_DESIGNATION', Tools::getValue('PI_UPDATE_DESIGNATION'));
        Configuration::updateValue('PI_ALLOW_PRODUCTSALESIMPORT', Tools::getValue('PI_ALLOW_PRODUCTSALESIMPORT'));
        Configuration::updateValue('PI_SYNC_SALES_PDV', Tools::getValue('PI_SYNC_SALES_PDV'));
        Configuration::updateValue('PI_ACTIVE', Tools::getValue('PI_ACTIVE'));
        Configuration::updateValue('PI_ALLOW_PRODUCTEXPORT', Tools::getValue('PI_ALLOW_PRODUCTEXPORT'));
        Configuration::updateValue('PI_EXPORT_ATTRIBUTES_IN_DESIGNATION', Tools::getValue('PI_EXPORT_ATTRIBUTES_IN_DESIGNATION'));
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
        // Logger les modifications apres sauvegarde
        $newValues = array(
            'SYNC_CSV_FEEDURL' => trim(Tools::getValue('SYNC_CSV_FEEDURL')),
            'PI_SOFTWAREID' => Tools::getValue('PI_SOFTWAREID'),
            'PI_CRON_TASK' => Tools::getValue('PI_CRON_TASK'),
            'PI_ALLOW_STOCKIMPORT' => Tools::getValue('PI_ALLOW_STOCKIMPORT'),
            'SYNC_STOCK_PDV' => Tools::getValue('SYNC_STOCK_PDV'),
            'PI_ALLOW_PRODUCTIMPORT' => Tools::getValue('PI_ALLOW_PRODUCTIMPORT'),
            'PI_ALLOW_PRODUCTIMAGEIMPORT' => Tools::getValue('PI_ALLOW_PRODUCTIMAGEIMPORT'),
            'PI_UPDATE_DESIGNATION' => Tools::getValue('PI_UPDATE_DESIGNATION'),
            'PI_ALLOW_PRODUCTSALESIMPORT' => Tools::getValue('PI_ALLOW_PRODUCTSALESIMPORT'),
            'PI_SYNC_SALES_PDV' => Tools::getValue('PI_SYNC_SALES_PDV'),
            'PI_ACTIVE' => Tools::getValue('PI_ACTIVE'),
            'PI_ALLOW_PRODUCTEXPORT' => Tools::getValue('PI_ALLOW_PRODUCTEXPORT'),
            'PI_EXPORT_ATTRIBUTES_IN_DESIGNATION' => Tools::getValue('PI_EXPORT_ATTRIBUTES_IN_DESIGNATION'),
            'PI_ALLOW_CATEGORYEXPORT' => Tools::getValue('PI_ALLOW_CATEGORYEXPORT'),
            'PI_PRODUCT_REFERENCE' => Tools::getValue('PI_PRODUCT_REFERENCE'),
            'PI_ALLOW_CUSTOMERIMPORT' => Tools::getValue('PI_ALLOW_CUSTOMERIMPORT'),
            'PI_ALLOW_CUSTOMEREXPORT' => Tools::getValue('PI_ALLOW_CUSTOMEREXPORT'),
            'PI_ALLOW_ORDEREXPORT' => Tools::getValue('PI_ALLOW_ORDEREXPORT'),
            'PI_VALID_ORDER_ONLY' => Tools::getValue('PI_VALID_ORDER_ONLY'),
            'PI_UPDATE_ORDER_STATUS' => Tools::getValue('PI_UPDATE_ORDER_STATUS'),
            'PI_RG1' => Tools::getValue('PI_RG1'),
            'PI_RG2' => Tools::getValue('PI_RG2'),
            'PI_RG3' => Tools::getValue('PI_RG3'),
            'PI_RG4' => Tools::getValue('PI_RG4'),
            'PI_RG5' => Tools::getValue('PI_RG5'),
            'PI_RG6' => Tools::getValue('PI_RG6'),
            'PI_RG7' => Tools::getValue('PI_RG7'),
            'PI_RG8' => Tools::getValue('PI_RG8'),
            'PI_RG9' => Tools::getValue('PI_RG9'),
            'PI_RG10' => Tools::getValue('PI_RG10'),
        );

        // Identifier les changements
        $changes = array();
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key];
            if ($oldValue != $newValue) {
                $oldDisplay = $this->formatBooleanValue($oldValue === null || $oldValue === '' ? 'vide' : $oldValue);
                $newDisplay = $this->formatBooleanValue($newValue === null || $newValue === '' ? 'vide' : $newValue);
                $changes[] = $key . ": " . $oldDisplay . " -> " . $newDisplay;
            }
        }

        // Logger les modifications si il y en a
        if (!empty($changes)) {
            // Reconstruire le format avec tableau mais sans infos utilisateur/IP
            $logMessage = '<h3>CONFIGURATION MODIFIEE</h3>';
            $logMessage .= '<strong>Date:</strong> ' . date('d/m/Y H:i:s') . '<br><br>';

            $logMessage .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 40%;">';
            $logMessage .= '<tr style="background-color: #e6e6e6ff;">
                            <th>Parametre</th>

                            <th>Nouvelle valeur</th>
                        </tr>';

            foreach ($newValues as $key => $newValue) {
                $oldValue = $oldValues[$key];
                if ($oldValue != $newValue) {
                    // $oldDisplay = $this->formatBooleanValue($oldValue === null || $oldValue === '' ? 'vide' : $oldValue);
                    $newDisplay = $this->formatBooleanValue($newValue === null || $newValue === '' ? 'vide' : $newValue);

                    $logMessage .= '<tr>';
                    $logMessage .= '<td><strong>' . $key . '</strong></td>';
                    // $logMessage .= '<td>' . htmlspecialchars($oldDisplay) . '</td>';
                    $logMessage .= '<td>' . htmlspecialchars($newDisplay) . '</td>';
                    $logMessage .= '</tr>';
                }
            }

            $logMessage .= '</table><br>';

            self::mylog($logMessage, true);
        }

        // Test TWS Connection (code original)
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
     * Récupère la liste des modules de paiement actifs
     */
    public function getActivePaymentModules()
    {
        $paymentModules = array();

        $installedPayments = PaymentModule::getInstalledPaymentModules();

        foreach ($installedPayments as $payment) {
            if (Module::isEnabled($payment['name'])) {
                $module = Module::getInstanceByName($payment['name']);
                if ($module) {
                    $paymentModules[] = array(
                        'name' => $payment['name'],
                        'display_name' => $module->displayName,
                        'technical_name' => strtoupper($payment['name'])
                    );
                }
            }
        }

        return $paymentModules;
    }

    /**
     * Charge les mappings de paiement
     */
    public function loadPaymentMappings()
    {
        $json = Configuration::get('PI_PAYMENT_MAPPINGS');
        return $json ? json_decode($json, true) : array();
    }

    /**
     * Sauvegarde les mappings de paiement
     */
    public function savePaymentMappings()
    {
        $mappings = array();

        if (Tools::isSubmit('payment_mappings')) {
            $data = Tools::getValue('payment_mappings');
            foreach ($data as $mapping) {
                if (!empty($mapping['prestashop']) && !empty($mapping['rezomatic'])) {
                    $mappings[] = array(
                        'prestashop' => strtoupper($mapping['prestashop']),
                        'display_name' => isset($mapping['display_name']) ? $mapping['display_name'] : strtoupper($mapping['prestashop']),
                        'rezomatic' => strtoupper($mapping['rezomatic'])
                    );
                }
            }
        }

        return Configuration::updateValue('PI_PAYMENT_MAPPINGS', json_encode($mappings));
    }

    /**
     * Formate les valeurs booleennes pour un affichage plus lisible
     */
    private function formatBooleanValue($value)
    {
        if ($value === '1') {
            return 'Active';
        } elseif ($value === '0') {
            return 'Desactive';
        } else {
            return $value;
        }
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
        $colid = 1; // pour eviter de chercher col0
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
         * Liste des declinaisons, a traiter apres la gestion des articles
         */
        $import_images = [];
        $combinations = [];
        $attributes = [];

        $combination_references_seen = [];

        $has_combination_base = false;

        // Recupere les infos pour les attributs
        $attributes = CombinationVccsv::getAttributes($tabledata);

        // Creation du tableau des images a importer
        $import_images = [];

        // $count = $final_products_arr;
        // $iterations = 0;
        foreach ($final_products_arr as $feedproduct) {
            try {
                $codeArtUpdated[] = $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]];

                /*
             * @edit Definima
             * Gestion des declinaisons a traiter apres les produits
             */
                // Si codeDeclinaison == reference, c'est le produit de base,
                // sinon c'est une declinaison du produit "reference"
                if (
                    $feedproduct[$tabledata['combination_reference']] != ''
                    && $feedproduct[$tabledata['combination_reference']] != '0'
                    && $feedproduct[$tabledata['combination_reference']] !=
                    $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]]
                ) {
                    $ref = $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]];

                    if (!in_array($ref, $combination_references_seen)) {
                        $combinations[] = $feedproduct;
                        $combination_references_seen[] = $ref;
                    }
                    continue;
                }

                // Creation du tableau des attributs pour le produit en cours
                foreach ($attributes as $attr_name => $attr_infos) {
                    if (empty($attr_infos['id_attribute_group']) || ($attr_infos['id_attribute_group'] == 0)) {
                        continue;
                    }

                    $attributes[$attr_name]['value'] =
                        $feedproduct[$tabledata[$attr_name . '_' . $attr_infos['id_attribute_group']]];
                }

                // Si le produit de base a la valeur taille ou couleur renseignee,
                // il faut creer une declinaison (la principale)
                if ((!empty($attributes['taille']['value'])) || (!empty($attributes['couleur']['value']))) {
                    $ref = $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]];
                    if (!in_array($ref, $combination_references_seen)) {
                        $tmp_feedproduct = $feedproduct;
                        $tmp_feedproduct[$tabledata['combination_reference']] =
                            $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]];

                        $combinations[] = $tmp_feedproduct;
                        $combination_references_seen[] = $ref;
                        $has_combination_base = $feedproduct[$tabledata[Configuration::get('PI_PRODUCT_REFERENCE')]];
                    }
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
                // Ecotaxe du feed (TTC) → convertir en HT pour Presta
                $deeeTTC = (isset($tabledata['ecotax'], $feedproduct[$tabledata['ecotax']]))
                    ? (float) str_replace(['#', 'R', ', '], ['', '.', '.'], (string)$feedproduct[$tabledata['ecotax']])
                    : 0.0;

                $product->ecotax = ProductVccsv::formatEcotaxFromWS($deeeTTC);

                // Installation de la taxe liee au produit
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
                    if (isset($tabledata['price'], $feedproduct[$tabledata['price']])) {
                        // taux reel si mappe, sinon defaut 20
                        $amount_tax = isset($tabledata['id_tax_rules_group'])
                            ? (float)$feedproduct[$tabledata['id_tax_rules_group']]
                            : 20.0;

                        // HT base attendu par Presta = (pvTTC - deeeTTC) / (1 + TVA)
                        $product->price = ProductVccsv::formatPriceFromWS(
                            $feedproduct[$tabledata['price']],   // pvTTC du feed
                            $amount_tax,                         // TVA du feed
                            $deeeTTC                             // ⚠️ DEEE **TTC** du feed (pas le HT)
                        );
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
                        $output .= "Produit $reference mis a jour\n";
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
                            try {
                                if (isset($product->date_add) && $product->date_add != '') {
                                    $res = $product->add(false);
                                } else {
                                    $res = $product->add();
                                }
                            } catch (Exception $e) {
                                $output .= Vccsv::logError($e);
                            }
                            $linecountadded = $linecountadded + 1;
                            $output .= "Produit simple $reference cree\n";
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

                    // Verifier si on garde cette partie ou pas

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
                // Mise a jour de l'impact prix et poids des declinaisons
                //
                if (Combination::isFeatureActive()) {
                    CombinationVccsv::updatePriceAndWeight($product, $amount_tax, $default_language_id, $shops);
                }

                // =================================
                // quantity
                $pdv = Configuration::get('SYNC_STOCK_PDV');
                if (!empty($pdv)) {
                    // Prise en compte uniquement des stocks du PDV renseigne
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
                    $output .= 'Stock article ' . $product->$reference_field . ' mis a jour a ' . $stock . ' sur Prestashop depuis Rezomatic' . "\n";
                } else {
                    $output .= 'Article ' . $product->$reference_field . ' <b>NON</b> mis a jour sur Prestashop' . "\n";
                }
                $res = Db::getInstance()->Execute('update `' . _DB_PREFIX_ . 'pfi_import_update` set sync_reference="' .
                    pSQL($reference) . '" where feedid =1');

                //
                // @edit Definima
                // Ajout des images dans le tableau des images a traiter
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
     * Traitement des declinaisons
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
                // Boucle sur les declinaisons
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

                        // Recupere le produit de base
                        $id_product_base = ProductVccsv::getProductIdByRefRezomatic($product_reference);

                        if (!$id_product_base) {
                            // Le parent n'existe pas, essayer de le creer automatiquement
                            $id_product_base = $this->createMissingParentProduct($product_reference);

                            if (!$id_product_base) {
                                // Échec de creation du parent
                                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                                    'pfi_import_log`(vdate, reference, product_error) VALUE(NOW(), "' .
                                    pSQL($reference) . '", "Combination error : base product ' . $product_reference .
                                    ' not found in PrestaShop and Rezomatic")');
                                $output .= $reference . ' Combination error : base product ' . $product_reference .
                                    ' not found in PrestaShop and Rezomatic' . "\n";
                                continue;
                            }

                            // Parent cree avec succes
                            $output .= "Produit parent $product_reference cree automatiquement pour la declinaison $reference\n";
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

                        // Recupere la declinaison par defaut
                        $is_combination_base = false;
                        if ($has_combination_base && $has_combination_base == $reference) {
                            $is_combination_base = true;
                        }
                        $has_default_combination = Product::getDefaultAttribute($product->id);

                        // Suppression de la declinaison par defaut si on traite la declinaison de base
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
                            // Groupe d'attribut non defini (depuis mapping)
                            if (empty($attr_infos['id_attribute_group']) || ($attr_infos['id_attribute_group'] == 0)) {
                                continue;
                            }

                            $value = $feedproduct[$tabledata[$attr_name . '_' . $attr_infos['id_attribute_group']]];
                            $attr_infos['value'] = $value;

                            if ($value == '0') {
                                continue;
                            }

                            // Recupere l'attribut
                            $infos_attribute = CombinationVccsv::getAttributeByGroupAndValue(
                                $attr_infos['id_attribute_group'],
                                $value,
                                $default_language_id
                            );
                            $id_attribute = 0;

                            if (!$infos_attribute) {
                                // Creation de l'attribut
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
                            $amount_tax = isset($tabledata['id_tax_rules_group'])
                                ? (float)$feedproduct[$tabledata['id_tax_rules_group']]
                                : 20.0;

                            $ecotaxTTC = isset($tabledata['ecotax'])
                                ? (float) ProductVccsv::formatPriceFromWS($feedproduct[$tabledata['ecotax']]) // normalisation simple
                                : 0.0;
                            $ecotaxHT = (float) ProductVccsv::formatEcotaxFromWS($ecotaxTTC);

                            // Prix HT de la declinaison (sans DEEE), aligne Presta
                            $priceHTDecli = isset($tabledata['price'])
                                ? (float) ProductVccsv::formatPriceFromWS(
                                    $feedproduct[$tabledata['price']],  // pvTTC de la declinaison
                                    $amount_tax,
                                    $ecotaxTTC                             // deee TTC de la declinaison
                                )
                                : 0.000000;

                            // Impact HT = HT_declinaison - HT_base_produit
                            $impactHT = (float) number_format(($priceHTDecli - (float)$product->price), 6, '.', '');

                            $weight = $weight - $product->weight;

                            // Recupere la declinaison si elle existe
                            if (Configuration::get('PI_PRODUCT_REFERENCE') == 'reference') {
                                $reference_for_combination = $feedproduct[$tabledata['reference']];
                                $id_product_attribute = Combination::getIdByReference($product->id, $reference_for_combination);

                                // Ajout des images specifiques de la declinaison
                                if (Configuration::get('PI_ALLOW_PRODUCTIMAGEIMPORT') == '1' && $id_product_attribute) {
                                    if (
                                        isset($feedproduct[$tabledata['image_url']])
                                        && trim($feedproduct[$tabledata['image_url']]) != ''
                                        && $feedproduct[$tabledata['image_url']] != '0'
                                    ) {
                                        // ✅ CORRECTIF : Ne pas ajouter l'image si c'est la déclinaison principale
                                        // La déclinaison principale hérite l'image du parent (id_product_attribute = 0)
                                        $is_main_combination = ($is_combination_base ||
                                            $feedproduct[$tabledata['combination_reference']] == $reference);

                                        if (!$is_main_combination) {
                                            $img_separator = ',';

                                            $import_images[] = [
                                                'urls' => explode($img_separator, $feedproduct[$tabledata['image_url']]),
                                                'product' => $product,
                                                'reference' => $reference,
                                                'id_product_attribute' => $id_product_attribute,
                                                'shops' => $shops,
                                            ];

                                            $output .= "Image declinaison $reference ajoutee au traitement\n";
                                        } else {
                                            // $output .= "Image declinaison principale $reference skippee (herite du parent)\n";
                                        }
                                    }
                                }
                            } elseif (Configuration::get('PI_PRODUCT_REFERENCE') == 'ean13') {
                                $reference_for_combination = ''; // Pas de reference auto pour les declinaisons en mode EAN13

                                // Chercher la declinaison existante par EAN13
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

                            // La declinaison n'existe pas, on cree l'entite
                            if (!$id_product_attribute) {
                                $id_product_attribute = $product->addCombinationEntity(
                                    $wholesale_price, // wholesale_price
                                    $impactHT, // price
                                    $weight, // weight
                                    0, // unit_impact
                                    Configuration::get('PS_USE_ECOTAX') ? $ecotaxHT : 0,
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

                                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                                    'pfi_import_log`(vdate, reference, product_error) VALUE(NOW(), "' .
                                    pSQL($reference) . '", "Declinaison creee pour produit ' . $product_reference . '")');

                                $linecountadded_combinations = $linecountadded_combinations + 1;
                                $output .= "Declinaison $reference creee\n";
                            } else {
                                // gets all the combinations of this product
                                $attribute_combinations = $product->getAttributeCombinations($default_language_id);
                                foreach ($attribute_combinations as $attribute_combination) {
                                    if (in_array($id_product_attribute, $attribute_combination)) {
                                        $product->updateAttribute(
                                            $id_product_attribute,
                                            $wholesale_price, // wholesale_price
                                            $impactHT, // price
                                            $weight, // weight
                                            0, // unit_impact
                                            Configuration::get('PS_USE_ECOTAX') ? $ecotaxHT : 0,
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
                                    }
                                }
                                $output .= "Declinaison $reference mise a jour\n";
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
                                // Prise en compte uniquement des stocks du PDV renseigne
                                $softwareid = Configuration::get('PI_SOFTWAREID');
                                $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
                                $pdv = explode(',', $pdv);
                                $pdv = array_map('strtolower', $pdv);
                                $pdv = array_map('trim', $pdv);
                                $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                                $stock_pdvs = $sc->getStocksFromCode($softwareid, $reference);
                                if (isset($stock_pdvs) && isset($stock_pdvs->stockPdv)) {
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

        // Traitement des images à importer
        if (!empty($import_images)) {
            // Déduplication par clé unique
            $deduplicated_images = [];
            foreach ($import_images as $img) {
                $id_product = (int) $img['product']->id;
                $id_product_attribute = isset($img['id_product_attribute']) ? (int) $img['id_product_attribute'] : 0;

                sort($img['urls']);
                $key = $id_product . '_' . $id_product_attribute . '_' . implode('|', $img['urls']);

                if (!isset($deduplicated_images[$key])) {
                    $deduplicated_images[$key] = $img;
                }
            }
            $import_images = array_values($deduplicated_images);

            // Construction de la liste des URLs à importer
            $urls_to_import = [];
            foreach ($import_images as $img) {
                $id_product = (int) $img['product']->id;
                $id_product_attribute = isset($img['id_product_attribute']) ? (int) $img['id_product_attribute'] : 0;

                foreach ($img['urls'] as $url) {
                    $url = trim($url);
                    $key = $id_product . '_' . $id_product_attribute . '_' . $url;
                    $urls_to_import[$key] = true;
                }
            }

            // Suppression des images qui ne sont plus dans le flux
            $nb_images_supprimees = 0;
            foreach ($import_images as $img) {
                $id_product = (int) $img['product']->id;
                $tmp_images = ProductVccsv::getSyncImages($id_product);

                foreach ($tmp_images as $tmpimg) {
                    if (in_array($tmpimg['reference'], $codeArtUpdated)) {
                        $existing_key = $id_product . '_' . (int)$tmpimg['system_combinationid'] . '_' . trim($tmpimg['url']);

                        if (!isset($urls_to_import[$existing_key])) {
                            ProductVccsv::deleteImage($tmpimg['system_imageid']);
                            $nb_images_supprimees++;
                        }
                    }
                }
            }

            // Insertion des nouvelles images
            $nb_images_ajoutees = 0;
            foreach ($import_images as $img) {
                $id_product = (int) $img['product']->id;
                $id_product_attribute = isset($img['id_product_attribute']) ? (int) $img['id_product_attribute'] : 0;
                $img['id_product_attribute'] = $id_product_attribute;

                $tmp_images = ProductVccsv::getSyncImages($id_product);
                $existing_urls = [];
                foreach ($tmp_images as $tmpimg) {
                    $existing_key = (int)$tmpimg['system_combinationid'] . '_' . trim($tmpimg['url']);
                    $existing_urls[$existing_key] = true;
                }

                sort($img['urls']);

                if (is_array($img['urls']) && !empty($img['urls'])) {
                    foreach ($img['urls'] as $url) {
                        $url = trim($url);
                        $check_key = $id_product_attribute . '_' . $url;

                        if (!isset($existing_urls[$check_key])) {
                            ProductVccsv::insertImage($url, $img, $languages, $this);
                            $nb_images_ajoutees++;
                        }
                    }
                }
            }

            // Résumé du traitement des images
            $output .= "--- IMAGES ---\n";
            $output .= "Images traitees: " . count($import_images) . "\n";
            if ($nb_images_supprimees > 0) {
                $output .= "Images supprimees: " . $nb_images_supprimees . "\n";
            }
            if ($nb_images_ajoutees > 0) {
                $output .= "Images ajoutees: " . $nb_images_ajoutees . "\n";
            }
        }

        //  ReSUMe AJOUTe AVANT LES LOGS CRON
        $output .= "Produits traites: $linecount\n";
        $output .= "Produits crees: $linecountadded\n";
        $output .= "Produits mis a jour: $linecountedited\n";
        if (isset($linecount_combinations) && $linecount_combinations > 0) {
            $output .= "Declinaisons traitees: $linecount_combinations\n";
            $output .= "Declinaisons creees: $linecountadded_combinations\n";
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
     * Necessaire pour self::copyImgNewFormat()
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
    public function saveTestTmpData($id, $Submitlimit, $timestamp = null)
    {
        Configuration::updateValue('SYNC_CSV_EMAILID', 'webmaster@tgmultimedia.com');
        $softwareid = Configuration::get('PI_SOFTWAREID');

        @ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');
        $feed_id = 1;
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');

        // Si pas de timestamp fourni, c'est le premier appel
        if (empty($timestamp)) {
            if ($id == 3) {
                $timestamp = Configuration::get('PI_LAST_CRON');
            } else {
                $timestamp = '2020-01-01 00:00:00';
            }
            // Vider la table temp au premier appel
            Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'pfi_import_tempdata_csv');
        }

        // get fields from pfi_import_feed_fields_csv
        $i = 0;
        $tabledata = '';
        $t_col = [];
        $fldarray = [];
        $t_col[] = 'feed_id';

        $correspondances = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('select `system_field`, xml_field  from `' .
            _DB_PREFIX_ . 'pfi_import_feed_fields_csv` where feed_id=' . (int) $feed_id . ' ORDER BY ID');

        foreach ($correspondances as $val) {
            ++$i;
            $tabledata .= $this->balise('td') . $val['system_field'] . $this->balise('/td');
            $t_col[] = 'col' . $i;
            $fldarray[] = $val['xml_field'];
        }
        $tabledata .= $this->balise('/tr');

        if (Tools::substr($feedurl, -5) == '.wsdl' || Tools::substr($feedurl, -4) == '.csv') {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);

            // Utiliser le timestamp fourni en paramètre
            $art = $sc->getNewArticles($softwareid, $timestamp, 100);

            if (!empty($art->article)) {
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
                // Plus d'articles à importer
                if ($id == 3) {
                    return true;
                }
                return json_encode([
                    'continue' => false,
                    'count' => 0,
                    'timestamp' => $timestamp
                ]);
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
        foreach ($final_products_arr as $val) {
            $tabledata .= $this->balise('tr', true);

            ++$a;
            $querycolarr = [$feed_id];
            $i = 1;
            foreach ($val as $key2 => $val2) {
                if (in_array($key2, $fldarray)) {
                    if (!in_array($key2, ['des', 'images', 'description', 'taille', 'couleur'])) {
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

            while (count($querycolarr) < count($t_col)) {
                $querycolarr[] = '\'0\'';
            }

            $query = 'insert into `' . _DB_PREFIX_ .
                'pfi_import_tempdata_csv`(' . implode(', ', array_map('pSQL', $t_col)) . ') ' .
                'values (' . implode(', ', $querycolarr) . ')';

            if (count($querycolarr) == count($t_col)) {
                Db::getInstance()->execute($query);
            }

            $tabledata .= $this->balise('/tr');
        }

        if ($id == 3) {
            return true;
        }

        // Retourner JSON avec le nouveau timestamp
        $next_timestamp = date('Y-m-d H:i:s');

        return json_encode([
            'continue' => true,
            'count' => count($final_products_arr),
            'timestamp' => $next_timestamp
        ]);
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

        // Reinitialisation des tarifs soldes du site
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ .
            'specific_price` WHERE id_specific_price_rule=0');
        $output .= 'Reset tarifs soldes' . "\n";
        try {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            // Verification periode de soldes
            if ($sc->isSoldesEnCours($softwareid)) {
                $output .= 'Soldes en cours';
                // Recupere les points de vente actifs
                $pdv_actifs = $sc->getPdvsActifs($softwareid);
                if (is_array($pdv_actifs->idPdv)) {
                    $pdvs = $pdv_actifs->idPdv;
                } else {
                    $pdvs = [$pdv_actifs->idPdv];
                }
                // Recupere les pdv configures dans l'admin PS
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
                    // Recupere tous les articles soldes
                    $all_article_solde = $sc->getAllTarifsSoldesFor($softwareid, $pdv);
                    if (isset($all_article_solde->tarifsSoldes)) {
                        if (is_array($all_article_solde->tarifsSoldes)) {
                            $all_art_solde = $all_article_solde->tarifsSoldes;
                        } else {
                            $all_art_solde = [$all_article_solde->tarifsSoldes];
                        }

                        foreach ($all_art_solde as $art) {
                            $reference = $art->codeArt;

                            // Recupere le produit sur Rezomatic
                            // $rz_product = $sc->getArticleFromCode($softwareid, $reference);

                            // Verifie si c'est une declinaison d'un produit principal
                            // $combination = [];
                            // if (isset($rz_product->codeDeclinaison) && $rz_product->codeDeclinaison
                            //     && $rz_product->codeDeclinaison != '0' && $rz_product->codeDeclinaison != $reference) {
                            $combination = CombinationVccsv::getCombinationByReference(
                                $reference,
                                Configuration::get('PI_PRODUCT_REFERENCE')
                            );
                            // }

                            // Recupere le produit PS en fonction de la reference
                            $id_product_attribute = 0;
                            if ($combination) {
                                $id_product = $combination['id_product'];
                                $id_product_attribute = $combination['id_product_attribute'];
                            } else {
                                $id_product = ProductVccsv::getProductIdByRefRezomatic($reference);
                            }

                            if ($id_product && is_numeric($id_product) /* && $rz_product->codeArt == $reference */) {
                                // Reduction (en %)
                                if (empty($art->tarif)) {
                                    $impact_reduc = 0;
                                } else {
                                    $impact_reduc = round(1 - ($art->tarifSolde / $art->tarif), 6);
                                    if ($impact_reduc < 0) {
                                        $impact_reduc = 0;
                                    }
                                }

                                $product = new Product($id_product);

                                // Recupere les shops du produit
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
                                    // Recupere un potentiel prix specifique
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
     * @edit TGM
     * Mise a jour status commandes Prestashop à partir de Rezomatic
     *
     * @return string
     */
    public function orderStatusSyncCron()
    {
        // Vérification de base
        if (Configuration::get('PI_UPDATE_ORDER_STATUS') != '1') {
            return 'Mise a jour des statuts de commandes inactif.';
        }

        $softwareid = Configuration::get('PI_SOFTWAREID');
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $output = '';

        // Récupération des états de commande Prestashop liés aux commandes Rezomatic
        for ($i = 1; $i <= 5; $i++)
            $get_status_orders[] = $this->getPrestashopStateFromRezoNumber($i);
        // Récupération des statuts de commandes Rezomatic qui ont bougé depuis la dernière synchro
        try {
            $timestamp = Configuration::get('PI_LAST_CRON');
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            $commandesStatuts = $sc->getCommandesStatutsFromTimestamp($softwareid, $timestamp);
            if (!empty($commandesStatuts->commandeState)) {
                if (is_array($commandesStatuts->commandeState)) {
                    $statuts = $commandesStatuts->commandeState;
                } else {
                    $statuts = [$commandesStatuts->commandeState];
                }
                $updates_count = 0;
                $skipped_count = 0;
                $errors_count = 0;
                // Pour chaque statut retourné
                foreach ($statuts as $statut) {
                    if (isset($get_status_orders[$statut->statut])) {
                        $new_status = $get_status_orders[$statut->statut];
                        // récupération de l'id commande Prestashop
                        $orderid = Db::getInstance()->getValue('select system_orderid from ' . _DB_PREFIX_ .
                            'pfi_order_apisync where api_orderid=' . (int) $statut->commandeNum);
                        if ($orderid) {
                            $order = new Order($orderid);
                            // Statut déjà à jour ? on ne fait rien
                            if ($order->current_state == $new_status) {
                                $skipped_count++;
                                continue;
                            }
                            // Mise à jour du statut
                            $OrderHistory = new OrderHistory();
                            $OrderHistory->changeIdOrderState($new_status, $orderid);
                            // Vérification du statut
                            $order = new Order($orderid);
                            if ($order->current_state == $new_status) {
                                // Succès
                                $updates_count++;
                                $rezomatic_label = $this->getRezoStateLabelFromNumber($statut->statut);
                                $output .= 'Mise a jour statut commande ' . $orderid . ' : "' . $rezomatic_label . '" (' . $new_status . ")\n";
                            } else {
                                // Échec
                                $errors_count++;
                                $output .= 'Erreur recuperation statut commande ' . $orderid . ' (' . $order->current_state . ")\n";
                            }
                            // Mise à jour facture (si existante)
                            if (!empty($statut->urlInvoice) && (Db::getInstance()->update(
                                'pfi_order_apisync',
                                ['urlInvoice' => $statut->urlInvoice],
                                '`api_orderid` = ' . (int) $statut->commandeNum
                            )))
                                $output .= 'Recuperation de la facture pour la commande ' . $orderid . "\n";
                        }
                    }
                }
                // Résumé uniquement si il y a eu des actions
                if ($updates_count > 0 || $errors_count > 0) {
                    $output .= 'Statuts mis a jour : ' . $updates_count;
                    if ($errors_count > 0) {
                        $output .= ' (erreurs : ' . $errors_count . ')';
                    }
                    $output .= "\n";
                }
                return $output;
            } else
                return "Aucun statut mis a jour.";
        } catch (Exception $e) {
            $output .= $e->getMessage() . "\n";
            return $output;
        }
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
     * Juste un var_dump entoure de pre
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
     * Sauvegarde les associations des etats de commandes
     * 
     * @return bool
     */
    private function saveStateMapping()
    {
        try {
            $state_mappings = Tools::getValue('state_mapping');

            if (!is_array($state_mappings)) {
                return false;
            }

            // Sauvegarder chaque association
            foreach ($state_mappings as $rezomatic_state => $prestashop_state_id) {
                $config_key = 'PI_STATE_MAPPING_' . strtoupper(str_replace(' ', '_', $rezomatic_state));
                Configuration::updateValue($config_key, (int)$prestashop_state_id);
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Recupere les etats Rezomatic (selon la documentation)
     * 
     * @return array
     */
    private function getRezoStates()
    {
        return [
            'EN ATTENTE',
            'TERMINE',
            'ANNULE PAR OPERATEUR',
            'ANNULE PAR CLIENT',
            'PRETE'
        ];
    }

    /**
     * Recupere les etats PrestaShop disponibles
     * 
     * @return array
     */
    private function getPrestashopStates()
    {
        $id_lang = $this->context->language->id;

        $states = Db::getInstance()->executeS('
            SELECT os.id_order_state as id_state, osl.name 
            FROM ' . _DB_PREFIX_ . 'order_state os
            LEFT JOIN ' . _DB_PREFIX_ . 'order_state_lang osl 
                ON (os.id_order_state = osl.id_order_state AND osl.id_lang = ' . (int)$id_lang . ')
            WHERE os.deleted = 0
            ORDER BY osl.name ASC
        ');
        return $states ?: [];
    }

    /**
     * Recupere les associations existantes des etats
     * 
     * @return array
     */
    private function getExistingStateMapping()
    {
        $rezo_states = $this->getRezoStates();
        $existing_mappings = [];

        foreach ($rezo_states as $state) {
            $config_key = 'PI_STATE_MAPPING_' . strtoupper(str_replace(' ', '_', $state));
            $existing_mappings[$state] = Configuration::get($config_key);
        }

        return $existing_mappings;
    }

    /**
     * Recupere l'ID de l'etat PrestaShop associe a un etat Rezomatic
     * 
     * @param string $rezomatic_state
     * @return int|false
     */
    public function getPrestashopStateFromRezomatic($rezomatic_state)
    {
        $config_key = 'PI_STATE_MAPPING_' . strtoupper(str_replace(' ', '_', $rezomatic_state));
        $ps_state_id = Configuration::get($config_key);

        return $ps_state_id ? (int)$ps_state_id : false;
    }

    /**
     * Convertit un numero de statut Rezomatic en libelle textuel
     * 
     * @param int $rezomatic_status_number
     * @return string|false
     */
    private function getRezoStateLabelFromNumber($rezomatic_status_number)
    {
        $rezomatic_states_mapping = [
            1 => 'EN ATTENTE',
            2 => 'TERMINE',
            3 => 'ANNULE PAR OPERATEUR',
            4 => 'ANNULE PAR CLIENT',
            5 => 'PRETE'
        ];

        return isset($rezomatic_states_mapping[$rezomatic_status_number])
            ? $rezomatic_states_mapping[$rezomatic_status_number]
            : false;
    }

    /**
     * Recupere l'ID de l'etat PrestaShop associe a un numero de statut Rezomatic
     * 
     * @param int $rezomatic_status_number
     * @return int|false
     */
    private function getPrestashopStateFromRezoNumber($rezomatic_status_number)
    {
        $rezomatic_label = $this->getRezoStateLabelFromNumber($rezomatic_status_number);

        if (!$rezomatic_label) {
            return false;
        }

        return $this->getPrestashopStateFromRezomatic($rezomatic_label);
    }

    /**
     * Recupere et cree un produit parent manquant depuis Rezomatic
     * 
     * @param string $product_reference La reference du produit parent manquant
     * @return int|false L'ID du produit cree, ou false en cas d'echec
     */
    private function createMissingParentProduct($product_reference)
    {
        try {
            $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
            $softwareid = Configuration::get('PI_SOFTWAREID');
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);

            $this->mylog("Tentative de recuperation du produit parent $product_reference depuis Rezomatic...");

            // Recuperer l'article depuis Rezomatic
            $article = $sc->getArticleFromCode($softwareid, $product_reference);

            if (!$article || !isset($article->codeArt)) {
                $this->mylog("Produit parent $product_reference introuvable dans Rezomatic");
                return false;
            }

            $this->mylog("Produit parent $product_reference trouve dans Rezomatic, creation dans PrestaShop...");

            // Creer le produit dans PrestaShop
            $product = new Product();

            // Reference
            $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
            $product->$reference_field = $article->codeArt;

            // Nom (requis)
            $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
            $product->name = [$default_lang => !empty($article->des) ? $article->des : $article->codeArt];

            // Description
            if (!empty($article->description)) {
                $product->description = [$default_lang => $article->description];
            }

            // Prix
            if (isset($article->pvTTC) && $article->pvTTC > 0) {
                $tax_rate = isset($article->tTVA) ? (float)$article->tTVA : 0;
                if ($tax_rate > 0) {
                    $product->price = (float)number_format($article->pvTTC / (1 + ($tax_rate / 100)), 6, '.', '');
                } else {
                    $product->price = (float)$article->pvTTC;
                }
            }

            // Prix d'achat
            if (isset($article->paHT)) {
                $product->wholesale_price = (float)$article->paHT;
            }

            // EAN13
            if (!empty($article->codeArt) && strlen($article->codeArt) == 13) {
                $product->ean13 = $article->codeArt;
            }

            // Poids
            if (isset($article->poids)) {
                $product->weight = (float)$article->poids;
            }

            // Ecotaxe
            if (isset($article->deee)) {
                $product->ecotax = (float)$article->deee;
            }

            // Condition (neuf/occasion)
            if (isset($article->neuf)) {
                $product->condition = $article->neuf ? 'new' : 'used';
            }

            // Categorie par defaut
            $product->id_category_default = 2; // Home par defaut

            // Actif
            $product->active = Configuration::get('PI_ACTIVE') == 1 ? 1 : 0;

            // Link rewrite (requis pour PrestaShop)
            $product->link_rewrite = [$default_lang => Tools::str2url($product->name[$default_lang])];

            // Ajouter le produit
            if ($product->add()) {
                // Mettre a jour le stock si disponible
                if (isset($article->stock) && $article->stock > 0) {
                    StockAvailable::setQuantity($product->id, 0, (int)$article->stock);
                }
                return $product->id;
            }
            $this->mylog("Echec de creation du produit parent $product_reference");
            return false;
        } catch (Exception $e) {
            $this->mylog("Erreur lors de la creation du parent $product_reference : " . $e->getMessage());
            return false;
        }
    }
}
