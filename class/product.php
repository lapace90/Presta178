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
     * Recherche un produit par référence (produit simple OU déclinaison)
     * @param string $ref_product
     * @return array|null ['id_product' => int, 'id_product_attribute' => int] ou null
     */
    public static function findProductByReference($ref_product)
    {
        if (!$ref_product) {
            return null;
        }

        // Déterminer le champ de référence
        if (Tools::substr($ref_product, 0, 4) == 'LT__') {
            $reference_field = 'reference';
        } else {
            $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
        }

        // 1. Chercher d'abord dans les produits simples
        $query = new DbQuery();
        $query->select('id_product');
        $query->from('product');
        $query->where($reference_field . ' = \'' . pSQL($ref_product) . '\'');

        $id_product = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);

        if ($id_product) {
            return [
                'id_product' => (int)$id_product,
                'id_product_attribute' => 0
            ];
        }

        // 2. Chercher dans les déclinaisons si pas trouvé
        $query = new DbQuery();
        $query->select('pa.id_product, pa.id_product_attribute');
        $query->from('product_attribute', 'pa');
        $query->where('pa.' . $reference_field . ' = \'' . pSQL($ref_product) . '\'');

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($query);

        if ($result) {
            return [
                'id_product' => (int)$result['id_product'],
                'id_product_attribute' => (int)$result['id_product_attribute']
            ];
        }

        return null;
    }

    /**
     * Ajouter un produit au pack en gérant les doublons (cumul des quantités)
     * @param int $id_pack
     * @param int $id_product
     * @param int $quantity
     * @return bool
     */
    public static function addItemToPack($id_pack, $id_product, $quantity)
    {
        // Vérifier si le produit existe déjà dans le pack
        $existing_qty = Db::getInstance()->getValue('
        SELECT quantity 
        FROM `' . _DB_PREFIX_ . 'pack` 
        WHERE `id_product_pack` = ' . (int)$id_pack . ' 
        AND `id_product_item` = ' . (int)$id_product . '
        AND `id_product_attribute_item` = 0
    ');

        if ($existing_qty !== false) {
            // Le produit existe déjà : mettre à jour la quantité
            $new_quantity = (int)$existing_qty + (int)$quantity;
            return Db::getInstance()->update(
                'pack',
                ['quantity' => $new_quantity],
                '`id_product_pack` = ' . (int)$id_pack . ' 
             AND `id_product_item` = ' . (int)$id_product . '
             AND `id_product_attribute_item` = 0'
            );
        } else {
            // Le produit n'existe pas : l'ajouter normalement
            return Pack::addItem($id_pack, $id_product, $quantity);
        }
    }

    /**
     * importLot function 
     * 
     */
    public static function importLot()
    {
        $allow_productimport = Configuration::get('PI_ALLOW_PRODUCTIMPORT');
        if ($allow_productimport != 1) {
            return 'Import de lots desactive' . "\n";
        }

        $softwareid = Configuration::get('PI_SOFTWAREID');
        $output = '';
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $reference_field = 'reference';
        $languages = Language::getLanguages();

        // Compteurs pour le résumé
        $lots_traites = 0;
        $lots_crees = 0;
        $lots_mis_a_jour = 0;
        $lots_erreurs = 0;

        try {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            $liste_lots = $sc->getExistingLot($softwareid);

            $lots = [];
            if (isset($liste_lots->lot)) {
                if (is_array($liste_lots->lot)) {
                    $lots = $liste_lots->lot;
                } else {
                    $lots = [$liste_lots->lot];
                }
            }

            $output .= "=== IMPORT LOTS ===\n";
            $output .= count($lots) . " lots a traiter\n";

            if (empty($lots)) {
                $output .= "Aucun lot a importer\n";
                $output .= "=== FIN IMPORT LOTS ===\n";
                return $output;
            }

            foreach ($lots as $lot) {
                $lots_traites++;
                $pack_code = $lot->codeLot;
                $designation = $lot->des;

                try {
                    // Récupération des articles du lot
                    $products_from_pack = $sc->getLotFromCode($softwareid, $pack_code);
                    $articles = [];
                    if (isset($products_from_pack->article)) {
                        if (is_array($products_from_pack->article)) {
                            $articles = $products_from_pack->article;
                        } else {
                            $articles = [$products_from_pack->article];
                        }
                    }

                    // Vérification si le lot existe déjà
                    $existing_product_id = self::getProductIdByRefRezomatic($pack_code);
                    $is_creation = (null == $existing_product_id);

                    if ($is_creation) {
                        // Créer nouveau lot
                        $product_pack = new Product();
                        $product_pack->ean13 = '';
                        $product_pack->upc = '';
                        $product_pack->ecotax = 0;
                        $product_pack->minimal_quantity = 1;
                        $product_pack->default_on = 0;
                        $product_pack->cache_is_pack = 1;
                        $product_pack->condition = 'new';

                        $namearray = [];
                        foreach ($languages as $lang) {
                            $namearray[$lang['id_lang']] = $designation;
                        }
                        $product_pack->name = $namearray;
                        $product_pack->$reference_field = $pack_code;
                        $product_pack->wholesale_price = '0.000000';
                        $product_pack->price = '0.000000';
                        $product_pack->weight = '0.00';

                        self::setproductlinkRewrite($product_pack, $id_lang, $languages);

                        if ($product_pack->add()) {
                            $lots_crees++;
                        } else {
                            $lots_erreurs++;
                            $output .= "Erreur création lot: $pack_code\n";
                            continue;
                        }
                    } else {
                        // Mettre à jour lot existant
                        Pack::deleteItems($existing_product_id);
                        $product_pack = new Product($existing_product_id);
                        $lots_mis_a_jour++;
                    }

                    // Mise à jour du nom
                    $namearray = [];
                    foreach ($languages as $lang) {
                        $namearray[$lang['id_lang']] = $designation;
                    }
                    $product_pack->name = $namearray;

                    // Traitement des articles du lot
                    $articles_ajoutes = 0;
                    $stock_lot = 10000;
                    $prix_total = 0;
                    $poids_total = 0;
                    $tauxtva = 0;

                    foreach ($articles as $article) {
                        $qty = isset($article->stock) ? (int)$article->stock : 1;
                        $prix_unitaire = isset($article->pvTTC) ? (float)$article->pvTTC : 0;

                        // Recherche du produit
                        $found_product = self::findProductByReference($article->codeArt);

                        if (!$found_product && isset($article->codeDeclinaison) && !empty($article->codeDeclinaison)) {
                            $found_product = self::findProductByReference($article->codeDeclinaison);
                        }

                        if ($found_product) {
                            // Ajouter au pack
                            $success = self::addItemToPack($product_pack->id, $found_product['id_product'], $qty);
                            if ($success) {
                                $articles_ajoutes++;
                                $prix_total += $prix_unitaire * $qty;

                                // Calculer le stock maximum possible
                                $product_stock = StockAvailable::getQuantityAvailableByProduct($found_product['id_product']);
                                if ($product_stock > 0) {
                                    $stock_possible = floor($product_stock / $qty);
                                    $stock_lot = min($stock_lot, $stock_possible);
                                } else {
                                    $stock_lot = 0;
                                }
                            }
                        }
                    }

                    // Finalisation du produit pack
                    $product_pack->id_tax_rules_group = 1;

                    if ($tauxtva > 0) {
                        $prix_ht = $prix_total / (1 + $tauxtva / 100);
                    } else {
                        $prix_ht = $prix_total;
                    }

                    $product_pack->price = number_format($prix_ht, 6, '.', '');
                    $product_pack->weight = $poids_total;

                    // Mise à jour du stock
                    StockAvailable::setQuantity((int) $product_pack->id, 0, $stock_lot);

                    $product_pack->update();
                } catch (Exception $e) {
                    $lots_erreurs++;
                    $output .= "Erreur lot $pack_code: " . $e->getMessage() . "\n";
                }
            }
        } catch (SoapFault $exception) {
            $lots_erreurs++;
            $output .= "Erreur SOAP: " . Vccsv::logError($exception);
        } catch (Exception $e) {
            $lots_erreurs++;
            $output .= "Erreur generale: " . $e->getMessage() . "\n";
        }

        // Résumé final
        $output .= "--- RESUME ---\n";
        $output .= "Lots traites: $lots_traites\n";
        $output .= "Lots crees: $lots_crees\n";
        $output .= "Lots mis a jour: $lots_mis_a_jour\n";
        if ($lots_erreurs > 0) {
            $output .= "Erreurs: $lots_erreurs\n";
        }
        $output .= "=== FIN IMPORT LOTS ===\n";

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
            $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
            $softwareid = Configuration::get('PI_SOFTWAREID');
            // Forcer l'affichage en temps réel pour les crons
            if ($iscron == 1) {
                flush();
                ini_set('max_execution_time', 0);
                ini_set('memory_limit', '2048M');
            }
            try {
                $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                // Récupérer la date de dernière exécution du cron
                $last_cron = Configuration::get('PI_LAST_CRON');
                if ($iscron == 1) {
                    echo '=================================================<br/>';
                    echo 'EXPORT CATALOGUE VERS REZOMATIC<br/>';
                    echo 'Début : ' . date('Y-m-d H:i:s') . '<br/>';
                    echo '=================================================<br/>';
                    if ($last_cron) {
                        echo "Mode : Export incrémental (depuis $last_cron)<br/>";
                    } else {
                        echo "Mode : Export complet<br/>";
                    }
                    echo '-------------------------------------------------<br/>';
                }
                // Récupérer tous les produits actifs (SANS LIMITE)
                $sql = 'SELECT p.id_product
                FROM ' . _DB_PREFIX_ . 'product p
                WHERE p.active = 1';
                if ($last_cron && $iscron == 1) {
                    $sql .= ' AND p.date_upd >= "' . pSQL($last_cron) . '"';
                }
                $sql .= ' ORDER BY p.id_product';
                $products = Db::getInstance()->executeS($sql);
                if ($iscron == 1) {
                    echo 'Nombre de produits à traiter : ' . count($products) . '<br/>';
                    echo '-------------------------------------------------<br/>';
                }
                // Si aucun produit à traiter, retourner un message simple
                if (empty($products)) {
                    if ($iscron == 1) {
                        echo 'Aucun produit à exporter<br/>';
                    }
                    return "Aucun produit a exporter";
                }
                $exported_count = 0;
                $error_count = 0;
                $processed_count = 0;
                foreach ($products as $product_data) {
                    try {
                        $id_product = $product_data['id_product'];
                        $processed_count++;
                        $result = self::productSync($id_product);
                        if (!empty($result)) {
                            $exported_count++;
                            $output .= $result;
                            if ($iscron == 1 && $exported_count % 10 == 0) {
                                echo "[" . date('H:i:s') . "] Produits exportés : {$exported_count}/{$processed_count}<br/>";
                                flush();
                            }
                        }
                    } catch (Exception $e) {
                        $error_count++;
                        $error_msg = $e->getMessage();
                        $output .= "Erreur produit {$id_product}: {$error_msg}\n";
                        if ($iscron == 1 && $error_count % 5 == 0) {
                            echo "<span style='color:red;'>[ERREUR] {$error_count} erreurs détectées</span><br/>";
                            flush();
                        }
                    }
                }
                if ($iscron == 1) {
                    echo '=================================================<br/>';
                    echo "EXPORT CATALOGUE TERMINÉ<br/>";
                    echo "Résumé :<br/>";
                    echo "- Produits traités : {$processed_count}<br/>";
                    echo "- Produits exportés : {$exported_count}<br/>";
                    if ($error_count > 0) {
                        echo "- <span style='color:red;'>Erreurs : {$error_count}</span><br/>";
                    }
                    echo "Fin : " . date('Y-m-d H:i:s') . '<br/>';
                    echo '=================================================<br/>';
                    flush();
                }
                $output .= "\n=== EXPORT CATALOGUE TERMINÉ ===\n";
                $output .= "Produits traités : {$processed_count}\n";
                $output .= "Produits exportés : {$exported_count}\n";
                if ($error_count > 0) {
                    $output .= "Erreurs : {$error_count}\n";
                }
            } catch (SoapFault $soapException) {
                $soap_error = $soapException->getMessage();
                $output = "Erreur SOAP : " . $soap_error;
                if ($iscron == 1) {
                    echo '=================================================<br/>';
                    echo "<span style='color:red;'>ERREUR EXPORT CATALOGUE</span><br/>";
                    echo '-------------------------------------------------<br/>';
                    echo "Erreur SOAP : " . $soap_error . "<br/>";
                    echo '=================================================<br/>';
                }
            } catch (Exception $e) {
                $general_error = $e->getMessage();
                $output = "Erreur générale : " . $general_error;
                if ($iscron == 1) {
                    echo '=================================================<br/>';
                    echo "<span style='color:red;'>ERREUR EXPORT CATALOGUE</span><br/>";
                    echo '-------------------------------------------------<br/>';
                    echo "Erreur générale : " . $general_error . "<br/>";
                    echo '=================================================<br/>';
                }
            }
        } else {
            $output = "Export désactivé dans la configuration (PI_ALLOW_PRODUCTEXPORT)";
            if ($iscron == 1) {
                echo '=================================================<br/>';
                echo "<span style='color:red;'>EXPORT CATALOGUE DÉSACTIVÉ</span><br/>";
                echo '-------------------------------------------------<br/>';
                echo $output . "<br/>";
                echo '=================================================<br/>';
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
     * @edit Ilaria (+fix DEEE)
     * Formate un prix venant du WS pour PrestaShop.
     * - $price : pvTTC renvoyé par le WS (string|float)
     * - $tax   : taux TVA en % (ex: 20). Si 0/null → juste normalise $price.
     * - $deee : écotaxe TTC renvoyée par le WS (ex: $art->deee). Par défaut 0.
     *
     * Retourne un string formaté 6 décimales, correspondant au HT **hors DEEE**,
     * donc directement compatible avec Product->price.
     */
    public static function formatPriceFromWS($price, $tax = 0, $deee = 0)
    {
        if ($price === null || $price === '') {
            return '0.000000';
        }

        // normalisation legacy
        $price = str_replace([', ', '#', 'R'], ['.', '.', ''], (string)$price);
        $price = (float)$price;

        // si pas de TVA → juste normaliser
        if (!$tax) {
            return number_format($price, 6, '.', '');
        }

        // ⚠️ retirer l'écotaxe TTC avant de retirer la TVA
        $deee = is_numeric($deee) ? (float)$deee : 0.0;
        $taxCoef = 1.0 + ((float)$tax / 100.0);
        $htBaseSansDeee = ($price - $deee) / $taxCoef;

        return number_format($htBaseSansDeee, 6, '.', '');
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
