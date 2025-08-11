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

class ProductVccsv extends Vccsv
{
    public static $exported = [];

    /**
     * getWarehousesByProductIdRezomatic function.
     *
     * @static
     *
     * @param mixed $id_product
     * @param int $id_product_attribute (default: 0)
     *
     * @return void
     */
    public static function getWarehousesByProductIdRezomatic($id_product, $id_product_attribute = 0)
    {
        if (!$id_product && !$id_product_attribute) {
            return [];
        }

        $query = new DbQuery();
        $query->select('DISTINCT w.id_warehouse, w.reference, w.name');
        $query->from('warehouse', 'w');
        $query->leftJoin('stock', 's', 's.id_warehouse = w.id_warehouse');
        if ($id_product) {
            $query->where('s.id_product = ' . (int) $id_product);
        }
        if ($id_product_attribute) {
            $query->where('s.id_product_attribute = ' . (int) $id_product_attribute);
        }
        $query->orderBy('w.reference ASC');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
    }

    /**
     * getWarehouseIdByRefRezomatic function.
     *
     * @static
     *
     * @param mixed $ref_warehouse
     *
     * @return void
     */
    public static function getWarehouseIdByRefRezomatic($ref_warehouse)
    {
        $query = new DbQuery();
        $query->select('id_warehouse');
        $query->from('warehouse');
        $query->where('reference = \'' . pSQL($ref_warehouse) . '\'');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * getProductIdByRefRezomatic function.
     *
     * @static
     *
     * @param mixed $ref_product
     *
     * @return void
     */
    public static function getProductIdByRefRezomatic($ref_product)
    {
        // Si la référence est un Lot, champs reference
        if (Tools::substr($ref_product, 0, 4) == 'LT__') {
            $reference_field = 'reference';
        } else {
            $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
        }
        $query = new DbQuery();
        $query->select('id_product');
        $query->from('product');
        $query->where($reference_field . ' = \'' . pSQL($ref_product) . '\'');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * getProductPhysicalQuantitiesRezomatic function.
     *
     * @static
     *
     * @param mixed $id_product
     * @param mixed $id_product_attribute
     * @param mixed $ids_warehouse (default: null)
     * @param bool $usable (default: false)
     *
     * @return void
     */
    public static function getProductPhysicalQuantitiesRezomatic(
        $id_product,
        $id_product_attribute,
        $ids_warehouse = null,
        $usable = false
    ) {
        if (!is_null($ids_warehouse)) {
            // in case $ids_warehouse is not an array
            if (!is_array($ids_warehouse)) {
                $ids_warehouse = [$ids_warehouse];
            }

            // casts for security reason
            $ids_warehouse = array_map('intval', $ids_warehouse);
            if (!count($ids_warehouse)) {
                return 0;
            }
        } else {
            $ids_warehouse = [];
        }

        $query = new DbQuery();
        $query->select('SUM(' . ($usable ? 's.usable_quantity' : 's.physical_quantity') . ')');
        $query->from('stock', 's');
        $query->where('s.id_product = ' . (int) $id_product);
        if (0 != $id_product_attribute) {
            $query->where('s.id_product_attribute = ' . (int) $id_product_attribute);
        }

        if (count($ids_warehouse)) {
            $query->where('s.id_warehouse IN(' . implode(', ', array_map('intval', $ids_warehouse)) . ')');
        }

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * setProductLocationRezomatic function.
     *
     * @static
     *
     * @param mixed $id_product
     * @param mixed $id_product_attribute
     * @param mixed $id_warehouse
     * @param mixed $location
     *
     * @return void
     */
    public static function setProductLocationRezomatic($id_product, $id_product_attribute, $id_warehouse, $location)
    {
        Db::getInstance()->execute('
                    DELETE FROM `' . _DB_PREFIX_ . 'warehouse_product_location`
                    WHERE `id_product` = ' . (int) $id_product . '
                    AND `id_product_attribute` = ' . (int) $id_product_attribute . '
                    AND `id_warehouse` = ' . (int) $id_warehouse);

        $row_to_insert = [
            'id_product' => (int) $id_product,
            'id_product_attribute' => (int) $id_product_attribute,
            'id_warehouse' => (int) $id_warehouse,
            'location' => pSQL($location),
        ];

        return Db::getInstance()->insert('warehouse_product_location', $row_to_insert);
    }

    /**
     * stockSync function.
     *
     * @static
     *
     * @return void
     */
    public static function stockSync()
    {
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $cron = Configuration::get('PI_CRON_TASK');
        $multistock = Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT');
        $pdv = Configuration::get('SYNC_STOCK_PDV');
        if (!empty($pdv)) {
            $pdv = explode(',', $pdv);
            $pdv = array_map('strtolower', $pdv);
            $pdv = array_map('trim', $pdv);
        }
        $page_name = Tools::getValue('controller');
        $output = '';
        if ($cron && ($multistock != 1) && ($page_name == 'product')) {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            if ($page_name == 'product') {
                $id_product = (int) Tools::getValue('id_product');
                $result = Db::getInstance()->executeS('select id_product, ' .
                    Configuration::get('PI_PRODUCT_REFERENCE') . ' from ' . _DB_PREFIX_ .
                    'product where active = 1 and advanced_stock_management = 0 and id_product = ' .
                    (int) $id_product . '');
            }
            foreach ($result as $feedproduct) {
                $id_product = $feedproduct['id_product'];
                // Si la référence est un Lot, pas de synchro des stocks
                if (Pack::isPack($id_product)) {
                    continue;
                }
                // Si le produit n'existe pas
                $id_lang = Context::getContext()->language->id;
                $product = new Product($id_product, false, $id_lang);
                if (!$product) {
                    continue;
                }
                // Si produit avec declinaisons
                $combinations = $product->getAttributeCombinations($id_lang);
                if ($combinations) {
                    foreach ($combinations as $c) {
                        try {
                            $reference = $c[Configuration::get('PI_PRODUCT_REFERENCE')];
                            if (empty($reference))
                                continue;
                            $stock_available = StockAvailable::getQuantityAvailableByProduct(
                                $c['id_product'],
                                $c['id_product_attribute']
                            );
                            // Prise en compte uniquement des stocks du PDV renseigné ?
                            if (empty($pdv)) {
                                // Stock global
                                $stock_rezomatic = $sc->getStockFromCode($softwareid, $reference);
                            } else {
                                // Stock PDV
                                $stock_rezomatic = 0;
                                $stock_pdvs = $sc->getStocksFromCode($softwareid, $reference);
                                if (isset($stock_pdvs->stockPdv)) {
                                    if (is_array($stock_pdvs->stockPdv)) {
                                        $stocks = $stock_pdvs->stockPdv;
                                    } else {
                                        $stocks = [$stock_pdvs->stockPdv];
                                    }
                                    foreach ($stocks as $st) {
                                        if (in_array($st->idPdv, $pdv)) {
                                            $stock_rezomatic += $st->stock;
                                        }
                                    }
                                }
                            }
                            $commandecours = $sc->getCommandeEnCours($softwareid, $reference);
                            $new_stock = $stock_rezomatic - $commandecours;
                            if (StockAvailable::setQuantity(
                                $c['id_product'],
                                $c['id_product_attribute'],
                                $new_stock
                            ) !== false) {
                                $output .= 'Stock mis a jour produit ' . $reference . ' : ' .
                                    $stock_available . ' -> ' . $new_stock . '\n';
                            } else {
                                $output .= 'Erreur de mise a jour stock declinaison ' . $c['id_product_attribute']
                                    . ' ' . $reference . ' : ' .
                                    $stock_available . ' -> ' . $new_stock . '\n';
                            }
                        } catch (SoapFault $exception) {
                            $output .= Vccsv::logError($exception);
                        }
                    }
                } else {
                    // Sinon produit sans declinaison
                    try {
                        $reference = $feedproduct[Configuration::get('PI_PRODUCT_REFERENCE')];
                        if (empty($reference))
                            continue;
                        $stock_available = StockAvailable::getQuantityAvailableByProduct($id_product);
                        // Prise en compte uniquement des stocks du PDV renseigné ?
                        if (empty($pdv)) {
                            // Stock global
                            $stock_rezomatic = $sc->getStockFromCode($softwareid, $reference);
                        } else {
                            // Stock PDV
                            $stock_rezomatic = 0;
                            $stock_pdvs = $sc->getStocksFromCode($softwareid, $reference);
                            if (isset($stock_pdvs->stockPdv)) {
                                if (is_array($stock_pdvs->stockPdv)) {
                                    $stocks = $stock_pdvs->stockPdv;
                                } else {
                                    $stocks = [$stock_pdvs->stockPdv];
                                }
                                foreach ($stocks as $st) {
                                    if (in_array($st->idPdv, $pdv)) {
                                        $stock_rezomatic += $st->stock;
                                    }
                                }
                            }
                        }
                        $commandecours = $sc->getCommandeEnCours($softwareid, $reference);
                        $new_stock = $stock_rezomatic - $commandecours;
                        if (StockAvailable::setQuantity($id_product, 0, $new_stock) !== false) {
                            $output .= 'Stock mis a jour produit ' . $reference . ' : ' .
                                $stock_available . ' -> ' . $new_stock . '\n';
                        } else {
                            $output .= 'Erreur de mise a jour stock ' . $id_product . ' ' . $reference . ' : ' .
                                $stock_available . ' -> ' . $new_stock . '\n';
                        }
                    } catch (SoapFault $exception) {
                        $output .= Vccsv::logError($exception);
                    }
                }
            }

            return $output;
        }
    }

    /**
     * importLot function.
     *
     * @static
     *
     * @return void
     */
    public static function importLot()
    {
        $allow_productimport = Configuration::get('PI_ALLOW_PRODUCTIMPORT');
        if ($allow_productimport != 1) {
            return '';
        }

        $softwareid = Configuration::get('PI_SOFTWAREID');
        $output = '';

        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        // $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
        $reference_field = 'reference';

        $languages = Language::getLanguages();

        try {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            $liste_lots = $sc->getExistingLot($softwareid);

            $namearray = [];
            $description_array = [];
            $description_shortarray = [];
            $stock = 10000;
            $tauxtva = 0;
            $lots = [];
            if (isset($liste_lots->lot)) {
                if (is_array($liste_lots->lot)) {
                    $lots = $liste_lots->lot;
                } else {
                    $lots = [$liste_lots->lot];
                }
            }
            foreach ($lots as $lot) {
                $pack_code = $lot->codeLot;
                $designation = $lot->des;
                $stock = 10000;
                $wholesale_price = '0.000000';
                $price = '0.000000';
                $weight = '0.00';
                $products_from_pack = $sc->getLotFromCode($softwareid, $pack_code);
                if (is_array($products_from_pack->article)) {
                    $articles = $products_from_pack->article;
                } else {
                    $articles = [$products_from_pack->article];
                }
                // Nouveau lot ?
                if (null == self::getProductIdByRefRezomatic($pack_code)) {
                    $product_pack = new Product();
                    $product_pack->ean13 = '';
                    $product_pack->upc = '';
                    $product_pack->ecotax = 0;
                    $product_pack->minimal_quantity = 1;
                    $product_pack->default_on = 0;
                    $product_pack->cache_is_pack = 1;
                    foreach ($languages as $lang) {
                        $namearray[$lang['id_lang']] = $designation;
                        $description = '';
                        $description_array[$lang['id_lang']] = pSQL($description);
                        $description_short = '';
                        $description_shortarray[$lang['id_lang']] = pSQL($description_short);
                    }
                    $product_pack->name = $namearray;
                    // $product_pack->description_short = $description_shortarray;
                    // $product_pack->description = $description_array;
                    $product_pack->$reference_field = $pack_code;
                    $product_pack->wholesale_price = $wholesale_price;
                    $product_pack->price = $price;
                    $product_pack->weight = $weight;
                    $product_pack->condition = 'new';
                    self::setproductlinkRewrite($product_pack, $id_lang, $languages);

                    if ($product_pack->add()) {
                        $output .= 'Ajout du Lot ' . $pack_code . '\n';
                    } else {
                        $output .= 'Erreur ajout du lot ' . $pack_code . '\n';
                    }
                } else {
                    // Update lot
                    $pack_id = self::getProductIdByRefRezomatic($pack_code);
                    Pack::deleteItems($pack_id);
                    $product_pack = new Product($pack_id);
                }
                // Pack name
                foreach ($languages as $lang) {
                    $namearray[$lang['id_lang']] = $designation;
                }
                $product_pack->name = $namearray;
                // Articles
                foreach ($articles as $article) {
                    $id_item = self::getProductIdByRefRezomatic($article->codeArt);
                    if ($id_item) {
                        Pack::addItem((int) $product_pack->id, $id_item, $article->stock);
                        // QUANTITE DISPONIBLE POUR LE LOT
                        $qty_item = StockAvailable::getQuantityAvailableByProduct($id_item);
                        $nbr_pack = floor($qty_item / $article->stock);
                        if ($nbr_pack < $stock) {
                            $stock = $nbr_pack;
                            StockAvailable::setQuantity((int) $product_pack->id, 0, $stock);
                        }
                        $wholesale_price += (float) $article->paHT * $article->stock;
                        $price += (float) $article->pvTTC;
                        $weight += (float) $article->poids * $article->stock;
                        $tauxtva = (float) $article->tTVA;
                    }
                }
                // Default Tax
                $product_pack->id_tax_rules_group = 1;
                // Try to find right id_tax_rules_group
                $rows = Db::getInstance()->executeS('SELECT rg.`id_tax_rules_group`, t.`rate`
                    FROM `' . _DB_PREFIX_ . 'tax_rules_group` rg
                    LEFT JOIN `' . _DB_PREFIX_ . 'tax_rule` tr ON (tr.`id_tax_rules_group` = rg.`id_tax_rules_group`)
                    LEFT JOIN `' . _DB_PREFIX_ . 'tax` t ON (t.`id_tax` = tr.`id_tax`)
                    GROUP BY rate');
                foreach ($rows as $row) {
                    if ((float) $row['rate'] == $tauxtva) {
                        $product_pack->id_tax_rules_group = $row['id_tax_rules_group'];
                        break;
                    }
                }
                $product_pack->wholesale_price = number_format($wholesale_price, 6, '.', '');
                $price = $price / (1 + $tauxtva / 100);
                $product_pack->price = number_format($price, 6, '.', '');
                $product_pack->weight = $weight; // CALCUL DES TROIS POIDS
                $product_pack->update();
            }
        } catch (SoapFault $exception) {
            $output .= Vccsv::logError($exception);
        }

        return $output;
    }

    /**
     * productSync function.
     *
     * @static
     *
     * @return void
     */
    public static function productSync($id_product)
    {
        $allow_productexport = Configuration::get('PI_ALLOW_PRODUCTEXPORT');
        $allow_categoryexport = Configuration::get('PI_ALLOW_CATEGORYEXPORT');
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $output = '';

        if ($allow_productexport == 1) {
            $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
            $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
            $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
            try {
                $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                $product = new Product($id_product);

                $name = $product->name[$id_lang];
                $name = Tools::replaceAccentedChars($name);
                $name = Tools::substr(trim(preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&\,@_-]/i', ' ', $name)), 0, 119);
                $wholesale_price = $product->wholesale_price;

                //Extraction de la hiérarchie complète : rayon > fam > sFam
                $rayon = null;
                $fam = null;
                $sfam = null;

                if ($allow_categoryexport == 1) {
                    $id_category_default = $product->id_category_default;
                    // Récupération du chemin complet de la catégorie
                    if (version_compare(_PS_VERSION_, '1.6', '>') === true) {
                        $full_path = Tools::getPath('', $id_category_default);
                    } else {
                        $full_path = Tools::getPath($id_category_default);
                    }
                    if (!empty($full_path)) {
                        // Nettoyage du chemin (suppression des balises HTML)
                        $clean_path = Tools::replaceAccentedChars(strip_tags($full_path));
                        $clean_path = preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&\,@_>-]/i', ' ', $clean_path);
                        $clean_path = trim(preg_replace('/\s{2,}/', ' ', $clean_path));
                        // Séparation des niveaux de hiérarchie (utilise " > " comme séparateur)
                        $hierarchy_levels = array_map('trim', explode('>', $clean_path));
                        $output .= print_r($hierarchy_levels, true) . '\n';
                        if (isset($hierarchy_levels[0])) $rayon = $hierarchy_levels[0];
                        if (isset($hierarchy_levels[1])) $fam = $hierarchy_levels[1];
                        if (isset($hierarchy_levels[2])) $sfam = $hierarchy_levels[2];
                    }
                }

                // Fournisseur
                if (!empty($product->id_supplier)) {
                    $id_supplier = $product->id_supplier;
                    $supplier = new Supplier($id_supplier);
                    $four = Tools::replaceAccentedChars($supplier->name);
                    $four = Tools::substr(trim(preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&\,@_-]/i', ' ', $four)), 0, 29);
                } else {
                    $four = null;
                }
                $reference = $product->$reference_field;
                $ecotax = $product->ecotax;
                $price_taxin = Product::getPriceStatic((int) $id_product, true, false, 6, null, '', false, false);
                $loyalty = '';
                $weight = $product->weight;
                $tax = Tax::getProductTaxRate((int) $id_product);
                $isvirtual = 0;
                $ean = $product->ean13;
                if ($product->condition == 'new') {
                    $condition = 1;
                } else {
                    $condition = 0;
                }

                /**
                 * Vérification des déclinaisons AVANT l'export du produit parent
                 */
                $combinations = $product->getAttributeCombinations($id_lang);
                $has_combinations = false;

                if ($combinations && count($combinations) > 0) {
                    $has_combinations = true;

                    // Export des déclinaisons uniquement
                    foreach ($combinations as $c) {
                        if (!empty($c[$reference_field])) {
                            $id_product_attribute = (int) $c['id_product_attribute'];
                            $output .= CombinationVccsv::combinationSync($id_product_attribute);
                        }
                    }

                    // Pour les produits avec déclinaisons, 
                    // on n'exporte PAS le produit parent sur Rezomatic
                    // Son codeArt servira de codeDeclinaison pour ses variations
                    return $output;
                }

                // Si la référence est vide, pas d'export
                if (empty($reference) && (!is_numeric($reference))) {
                    return '';
                }
                // Si la référence est un Lot, pas d'export
                if (Pack::isPack($id_product)) {
                    return '';
                }
                if (Tools::substr($reference, 0, 4) == 'LT__') {
                    return '';
                }
                // Si déjà exportée dans le même script, pas de nouvel export
                if ((!empty(ProductVccsv::$exported)) && in_array($reference, ProductVccsv::$exported)) {
                    return '';
                } else {
                    ProductVccsv::$exported[] = $reference;
                }

                $taille = '';
                $couleur = '';

                $free = $sc->isFreeCodeArt($softwareid, $reference);
                if (!$free) {
                    $art = $sc->updateArticle(
                        $softwareid,
                        $reference,
                        $name,
                        $rayon, // rayon
                        $fam,
                        $sfam,
                        $wholesale_price,
                        $tax,
                        $price_taxin,
                        $weight,
                        $taille,
                        $couleur,
                        $loyalty,
                        $ecotax,
                        $isvirtual,
                        null,
                        null,
                        null, // type
                        null,
                        $wholesale_price,
                        $condition,
                        null,
                        null,
                        $four,
                        null, // codeDeclinaison
                        null, // description  
                        null, // saison
                        null, // annee
                        null, // uniteMesure
                        null, // tailleContenant
                        null, // forceSerial
                        null, // typeSerial
                        null, // garantie
                        null, // mp

                    );

                    $output .= parent::l('Product') . ' ' . $reference . ' ' . parent::l('updated') . ' -> ' . $reference . '\n';
                    $output .= print_r((array) $art, true) . '\n';
                } else {
                    $art = $sc->createArticle(
                        $softwareid,
                        $reference,
                        $name,
                        $rayon, // rayon
                        $fam,
                        $sfam,
                        $wholesale_price,
                        $tax,
                        $price_taxin,
                        $weight,
                        $taille,
                        $couleur,
                        $loyalty,
                        $ecotax,
                        $isvirtual,
                        null,
                        null,
                        null, // type
                        null,
                        $wholesale_price,
                        $condition,
                        null,
                        null,
                        $four

                    );
                    $output .= parent::l('Product') . ' ' . $reference . ' ' . parent::l('created') . ' -> ' . $condition . '\n';
                    $output .= print_r((array) $art, true) . '\n';
                }

                if ($ean != '') {
                    $free_ean = $sc->isFreeCodeArt($softwareid, $ean);
                    if ($free_ean) {
                        $sc->addAssociationCodeArticle($softwareid, $reference, $ean);
                    }
                }
            } catch (SoapFault $exception) {
                $output .= Vccsv::logError($exception);
            }
        }

        return $output;
    }

    /**
     * exportAll function.
     *
     * @static
     *
     * @return void
     */
    public static function exportAll($iscron = 0)
    {
        $allow_productexport = Configuration::get('PI_ALLOW_PRODUCTEXPORT');
        $output = '';

        if ($allow_productexport == 1) {
            $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');

            // Forcer l'affichage en temps réel pour les crons
            if ($iscron == 1) {
                flush();
                ini_set('max_execution_time', 0);
                ini_set('memory_limit', '2048M');
            }

            try {
                // Récupérer la date de dernière exécution du cron
                $last_cron = Configuration::get('PI_LAST_CRON');

                if ($iscron == 1) {
                    echo '-------------------------------------------------<br/>';
                    echo 'EXPORT CATALOGUE VERS REZOMATIC<br/>';
                    echo '-------------------------------------------------<br/>';
                    if ($last_cron) {
                        echo 'Exporter les produits modifiés depuis le dernier cron<br/>';
                        echo 'Dernière exécution du cron : ' . $last_cron . '<br/>';
                    } else {
                        echo 'Exporter tous les produits (premier export)<br/>';
                    }
                    echo '-------------------------------------------------<br/>';
                }

                // Construire la requête pour récupérer les produits
                if ($last_cron) {
                    // Export des produits modifiés depuis le dernier cron
                    $products = Db::getInstance()->executeS('
                    SELECT p.id_product, pl.name, p.date_add, p.date_upd
                    FROM `' . _DB_PREFIX_ . 'product` p
                    LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int)$id_lang . ')
                    WHERE p.active = 1 
                    AND (p.date_add > "' . pSQL($last_cron) . '" OR p.date_upd > "' . pSQL($last_cron) . '")
                    ORDER BY p.date_upd DESC, p.date_add DESC
                ');
                } else {
                    // Premier export ou pas de date de référence : export complet
                    $products = Product::getProducts($id_lang, 0, 0, 'id_product', 'asc', false, 0);
                }

                $total_products = count($products);
                $processed = 0;
                $success = 0;
                $errors = 0;

                if ($iscron == 1) {
                    echo 'Produits à exporter : ' . $total_products . '<br/>';
                    echo '-------------------------------------------------<br/>';
                }

                if ($total_products == 0) {
                    $msg = 'Aucun produit à exporter';
                    $output .= $msg . "\n";
                    if ($iscron == 1) {
                        echo $msg . '<br/>';
                        if ($last_cron) {
                            echo 'Tous les produits sont à jour depuis le dernier cron<br/>';
                        }
                        echo '-------------------------------------------------<br/>';
                    }
                    return $output;
                }

                foreach ($products as $item) {
                    $processed++;

                    try {
                        $result = ProductVccsv::productSync($item['id_product']);

                        if ($iscron == 1 && $processed % 10 == 0) {
                            echo 'Progress : ' . $processed . '/' . $total_products . ' products processed<br/>';
                            flush();
                        }

                        if ($result && strpos($result, 'Error') === false) {
                            $success++;
                        } else {
                            $errors++;
                            $output .= 'Erreur produit ID ' . $item['id_product'] . ': ' . $result . "\n";
                        }

                        $output .= $result;
                    } catch (Exception $e) {
                        $errors++;
                        $error_msg = 'Exception for product ID ' . $item['id_product'] . ': ' . $e->getMessage();
                        $output .= $error_msg . "\n";

                        if ($iscron == 1) {
                            echo $error_msg . '<br/>';
                        }
                    }
                }
            } catch (SoapFault $exception) {
                $error_msg = Vccsv::logError($exception);
                $output .= $error_msg;
                $errors++;

                if ($iscron == 1) {
                    echo 'SOAP Error : ' . $error_msg . '<br/>';
                }
            }

            // Statistiques finales
            if ($iscron == 1) {
                echo '-------------------------------------------------<br/>';
                echo 'Nombre de produits traités : ' . $processed . '<br/>';
                echo '-------------------------------------------------<br/>';
                echo 'Nombre de produits exportés avec succès : ' . $success . '<br/>';
                echo '-------------------------------------------------<br/>';
                echo 'Nombre de produits en erreur à l\'export : ' . $errors . '<br/>';
                echo '-------------------------------------------------<br/>';
                if ($last_cron) {
                    echo 'Export terminé : Produits modifiés depuis ' . $last_cron . '<br/>';
                } else {
                    echo 'Export terminé : Tous les produits<br/>';
                }
                echo '-------------------------------------------------<br/>';
            }
        } else {
            $error_msg = 'Product export not allowed';
            $output = $error_msg . "\n";

            if ($iscron == 1) {
                echo '-------------------------------------------------<br/>';
                echo $error_msg . '<br/>';
                echo '-------------------------------------------------<br/>';
            }
        }

        return $output;
    }

    /**
     * setproductlinkRewrite function.
     *
     * @static
     *
     * @param mixed &$product
     * @param mixed $default_language_id
     * @param mixed $languages
     *
     * @return void
     */
    public static function setproductlinkRewrite(&$product, $default_language_id, $languages)
    {
        if (is_array($product->link_rewrite) && count($product->link_rewrite)) {
            $link_rewrite = trim($product->link_rewrite[$default_language_id]);
        } else {
            $link_rewrite = '';
        }
        if (Tools::strlen($link_rewrite) < 5) {
            $link_rewrite = Tools::link_rewrite($product->name[$default_language_id]);
        }
        $link_rewrite_array = [];
        foreach ($languages as $lang) {
            $link_rewrite_array[$lang['id_lang']] = $link_rewrite;
        }
        $product->link_rewrite = $link_rewrite_array;
    }

    /**
     * productDelete function.
     *
     * @static
     *
     * @param mixed $id_product
     *
     * @return void
     */
    public static function productDelete($id_product)
    {
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $output = '';

        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        try {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            $reference = $id_product;

            $output .= 'Deleting ref' . $reference;
            if ($sc->dropArticle($softwareid, $reference)) {
                $output .= 'Product' . $reference . ' deleted\n';
            } else {
                $output .= 'Product' . $reference . ' not deleted\n';
            }
        } catch (SoapFault $exception) {
            $output = Vccsv::logError($exception);
        }

        return $output;
    }

    /**
     * @edit Definima
     * Permet de formater un prix venant du webservice pour Prestashop (évite la duplication de code)
     *
     * @param $price
     * @param $tax
     *
     * @return float|mixed|string
     */
    public static function formatPriceFromWS($price, $tax = 0)
    {
        if ($price) {
            $price = str_replace(', ', '.', $price);
            $price = str_replace('#', '.', $price);
            $price = str_replace('R', '', $price);
            $price = (float) $price;

            if ($tax) {
                $price = $price / (1 + $tax / 100);
            }

            $price = number_format($price, 6, '.', '');
        } else {
            $price = 0.000000;
        }

        return $price;
    }

    /**
     * @edit Definima
     * Récupère les images synchronisées dans la table pfi_images_apisync
     *
     * @param $id_product
     *
     * @return mixed
     */
    public static function getSyncImages($id_product = 0)
    {
        $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
        $q = '
            SELECT i.*, IFNULL(pa.' . $reference_field . ', p.' . $reference_field . ') as reference
            FROM ' . _DB_PREFIX_ . 'pfi_images_apisync i
            LEFT JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = i.system_productid
            LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON pa.id_product_attribute = i.system_combinationid
            WHERE p.id_product IS NOT NULL
        ';

        if ($id_product) {
            if (is_array($id_product)) {
                $q .= '
                    AND i.system_productid IN (' . implode(',', $id_product) . ')
                ';
            } else {
                $q .= '
                    AND i.system_productid = ' . (int) $id_product . '
                ';
            }
        }
        $q .= '
            ORDER BY i.url ASC
        ';

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($q);
    }

    /**
     * @edit Definima
     * Création image produit / declinaison
     *
     * @param $url
     * @param $img
     * @param $languages
     * @param $module
     *
     * @return string
     */
    public static function insertImage($url, $img, $languages, $module)
    {
        $output = '';

        $url = trim($url);
        $product_has_images = false;
        foreach ($languages as $l) {
            if ((bool) Image::getImages($l['id_lang'], $img['product']->id)) {
                $product_has_images = true;
                break;
            }
        }

        $image = new Image();
        $image->id_product = (int) $img['product']->id;
        $image->position = Image::getHighestPosition($img['product']->id) + 1;
        $image->cover = !$product_has_images;

        $field_error = $image->validateFields(UNFRIENDLY_ERROR, true);
        $lang_field_error = $image->validateFieldsLang(UNFRIENDLY_ERROR, true);

        if ($field_error === true && $lang_field_error === true && $image->add()) {
            $image->associateTo($img['shops']);

            // En fonction de la version de PS on choisi l'une ou l'autre version de la copie des images
            if ($module->isPrestashop15()) {
                $copyImg = $module::copyImg($img['product']->id, $image->id, $url, 'products', true);
            } else {
                $copyImg = $module::copyImgNewFormat($img['product']->id, $image->id, $url, 'products', true);
            }

            if (!$copyImg) {
                $image->delete();

                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                    'pfi_import_log`(vdate, reference, product_error) VALUE(NOW(), "' .
                    pSQL($img['reference']) . '", "Error copying image ' . $url . '")');
                $output .= $img['reference'] . ' Error copying image ' . $url . "\n";
            } else {
                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                    'pfi_import_log`(vdate, reference, product_error) VALUE(NOW(), "' .
                    pSQL($img['reference']) . '", "Import image url ' . $url . ' - id ' . $image->id . '")');
                $output .= $img['reference'] . ' Import image url ' . $url . ' - id ' . $image->id . "\n";

                // Combination
                $id_product_attribute = 0;
                Db::getInstance()->delete('product_attribute_image', 'id_image = ' . (int) $image->id);
                if (
                    isset($img['id_product_attribute']) && $img['id_product_attribute']
                    && $img['id_product_attribute'] != 0
                ) {
                    $id_product_attribute = $img['id_product_attribute'];
                    Db::getInstance()->insert('product_attribute_image', [
                        'id_product_attribute' => (int) $img['id_product_attribute'],
                        'id_image' => (int) $image->id,
                    ]);
                }

                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                    'pfi_images_apisync`(system_productid, system_combinationid, url, system_imageid) VALUE("' .
                    pSQL($img['product']->id) . '", "' . (int) $id_product_attribute . '", "' .
                    pSQL($url) . '", "' . pSQL($image->id) . '")');
            }
        } else {
            Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ .
                'pfi_import_log`(vdate, reference, product_error) VALUE(NOW(), "' .
                pSQL($img['reference']) . '", "Erreur creation image - ' . $field_error . ' - ' . $lang_field_error . ')');
            $output .= $img['reference'] . ' Erreur creation image -  ' . $field_error . ' - ' . $lang_field_error . "\n";
        }

        return $output;
    }

    /**
     * @edit Definima
     * Suppression image
     *
     * @param $id_image
     */
    public static function deleteImage($id_image)
    {
        $image = new Image((int) $id_image);
        $image->delete();

        Db::getInstance()->delete('product_attribute_image', 'id_image = ' . (int) $id_image);

        Db::getInstance()->Execute('
            DELETE FROM ' . _DB_PREFIX_ . 'pfi_images_apisync 
            WHERE system_imageid = ' . (int) $id_image . '
        ');
    }
}
