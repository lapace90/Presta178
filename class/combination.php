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

/**
 * @edit Definima
 * Synchronisation des déclinaisons
 */
class CombinationVccsv extends Vccsv
{
    /**
     * Permet de construire un tableau d'infos pour les attributs taille et couleur.
     *
     * @param $tabledata
     *
     * @return array
     */
    public static function getAttributes($tabledata)
    {
        $attributes = ['taille' => [], 'couleur' => []];

        foreach ($tabledata as $system_field => $colname) {
            if (
                Tools::substr($system_field, 0, Tools::strlen('taille_')) === 'taille_'
                || Tools::substr($system_field, 0, Tools::strlen('couleur_')) === 'couleur_'
            ) {
                $tmp = explode('_', $system_field);

                $attributes[$tmp[0]] = [
                    'id_attribute_group' => (int) $tmp[1],
                    'value' => 0,
                ];
            }
        }

        return $attributes;
    }

    /**
     * Retourne toutes les infos d'un attribut et son groupe par le groupe et la valeur de l'attribut
     *
     * @param $id_attribute_group
     * @param $name
     * @param $id_lang
     *
     * @return mixed
     */
    public static function getAttributeByGroupAndValue($id_attribute_group, $name, $id_lang)
    {
        return Db::getInstance()->getRow('
            SELECT *
            FROM `' . _DB_PREFIX_ . 'attribute_group` ag
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl
                ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = ' . (int) $id_lang . ')
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a
                ON a.`id_attribute_group` = ag.`id_attribute_group`
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al
                ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = ' . (int) $id_lang . ')
            ' . Shop::addSqlAssociation('attribute_group', 'ag') . '
            ' . Shop::addSqlAssociation('attribute', 'a') . '
            WHERE al.`name` = \'' . pSQL($name) . '\' AND ag.`id_attribute_group` = ' . (int) $id_attribute_group . '
            ORDER BY agl.`name` ASC, a.`position` ASC
        ');
    }

    /**
     * Met à jour l'impact du prix des déclinaisons du produit
     *
     * @param Product $product
     * @param float $amount_tax
     * @param int $id_lang
     * @param array $shops
     */
    public static function updatePriceAndWeight($product, $amount_tax, $id_lang, $shops)
    {
        $combinations = $product->getAttributeCombinations($id_lang);
        $output = '';
        if (!$combinations) {
            return;
        }

        $softwareid = Configuration::get('PI_SOFTWAREID');
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $sc = new SoapClient($feedurl, ['keep_alive' => false]);

        foreach ($combinations as $c) {
            $comb_WS = false;

            try {
                $comb_WS = $sc->getArticleFromCode($softwareid, $c[Configuration::get('PI_PRODUCT_REFERENCE')]);
            } catch (SoapFault $exception) {
                $output .= Vccsv::logError($exception);
                continue;
            }

            if (!$comb_WS) {
                continue;
            }

            $new_price = ProductVccsv::formatPriceFromWS(($comb_WS->pvTTC / (1 + $amount_tax / 100)) - $product->price);
            $new_weight = $comb_WS->poids - $product->weight;

            $product->updateAttribute(
                $c['id_product_attribute'],
                $c['wholesale_price'], // wholesale_price
                $new_price, // price
                $new_weight, // weight
                $c['unit_price_impact'], // unit_impact
                $c['ecotax'], // ecotax
                [], // id_images
                $c['reference'], // reference
                $c['ean13'], // ean13
                $c['default_on'], // default
                $c['location'], // location
                $c['upc'], // upc
                $c['minimal_quantity'], // minimal_quantity
                $c['available_date'], // available_date
                null, // update_all_fields
                $shops // id_shop_list
            );
        }
    }

    /**
     * Retourne les déclinaisons qui ont cette référence
     *
     * @param $reference
     * @param string $field_name
     *
     * @return mixed
     */
    public static function getCombinationsByReference($reference, $field_name = 'reference')
    {
        $query = new DbQuery();
        $query->select('pa.*');
        $query->from('product_attribute', 'pa');
        $query->where('pa.' . $field_name . ' LIKE \'' . pSQL($reference) . '\'');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
    }

    /**
     * Retourne la déclinaison qui a cette référence
     *
     * @param $reference
     * @param string $field_name
     *
     * @return array
     */
    public static function getCombinationByReference($reference, $field_name = 'reference')
    {
        $res = self::getCombinationsByReference($reference, $field_name);
        if (isset($res[0])) {
            return $res[0];
        }

        return [];
    }

    /**
     * Récupère les attributs associés à la déclinaison
     *
     * @param $id_product_attribute
     * @param $id_lang
     *
     * @return mixed
     */
    public static function getGroupAttributes($id_product_attribute, $id_lang)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT
al.id_attribute, al.name as value, agl.id_attribute_group, agl.name as attribute_group
FROM ' . _DB_PREFIX_ . 'product_attribute_combination pac
JOIN ' . _DB_PREFIX_ . 'attribute a ON (a.id_attribute = pac.id_attribute)
JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON (al.id_attribute = a.id_attribute AND al.id_lang=' . (int) $id_lang . ')
JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl ON (agl.id_attribute_group = a.id_attribute_group
    AND agl.id_lang=' . (int) $id_lang . ')
WHERE pac.id_product_attribute=' . (int) $id_product_attribute . '
        ');
    }

    /**
     * Synchronisation des déclinaisons PS -> Rezomatic
     *
     * @param $id_product
     * @param null $reference_combination
     * @param null $ean
     * @param null $price_impact
     * @param null $priceTI
     * @param null $weight
     * @param null $combination_list
     *
     * @return string
     *
     * @see PfProductImporter::hookActionProductUpdate()
     */
    public static function syncCombination(
        $id_product,
        $reference_combination = null,
        $ean = null,
        $wholesale_price = null,
        $price_impact = null,
        $priceTI = null,
        $weight = null,
        $combination_list = null
    ) {
        if (version_compare(_PS_VERSION_, '8', '>') === true) {
            $Attribute = 'ProductAttribute';
        } else {
            $Attribute = 'Attribute';
        }
        $allow_productexport = Configuration::get('PI_ALLOW_PRODUCTEXPORT');
        $allow_categoryexport = Configuration::get('PI_ALLOW_CATEGORYEXPORT');
        $output = '';
        // $output = "syncCombination(\n
        //             id_product = $id_product,\n
        //             reference_combination = $reference_combination,\n
        //             ean = $ean,\n
        //             wholesale_price = $wholesale_price,\n
        //             price_impact = $price_impact,\n
        //             priceTI = $priceTI,\n
        //             weight = $weight,\n
        //             )\n";

        if (!$allow_productexport) {
            return $output;
        }
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');

        try {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            $Product = new Product($id_product, false, $id_lang);
            // Des
            $name = Tools::replaceAccentedChars($Product->name);
            $name = Tools::substr(trim(preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&\,@_-]/i', ' ', $name)), 0, 119);
            if (empty($wholesale_price) || ($wholesale_price == '0,000000')) {
                $wholesale_price = $Product->wholesale_price;
            }
            //Extraction de la hiérarchie complète : rayon > fam > sFam
            $rayon = null;
            $fam = null;
            $sfam = null;

            if ($allow_categoryexport == 1) {
                $id_category_default = $Product->id_category_default;
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
                    // $output .= print_r($hierarchy_levels, true) . '\n';

                    if (isset($hierarchy_levels[0])) $rayon = $hierarchy_levels[0];
                    if (isset($hierarchy_levels[1])) $fam = $hierarchy_levels[1];
                    if (isset($hierarchy_levels[2])) $sfam = $hierarchy_levels[2];
                }
            }
            $tax = Tax::getProductTaxRate((int) $id_product);

            $loyalty = '';
            $ecotax = $Product->ecotax;
            $isvirtual = 0;

            if ($Product->condition == 'new') {
                $condition = 1;
            } else {
                $condition = 0;
            }

            $final_price = (empty($priceTI) ? 0 : $priceTI) * (empty($price_impact) ? 1 : $price_impact) + $Product->ecotax;
            $final_price = ($Product->price * (1 + ($tax / 100))) + $final_price;
            $final_price = number_format($final_price, 6, '.', '');

            $final_weight = $weight + $Product->weight;
            // Declinaisons
            $taille = '';
            $couleur = '';
            $associations = [];
            if (is_array($combination_list)) {
                // Récupère les infos pour les attributs
                $feed_id = 1;
                $i = 0;
                $tabledata = [];
                $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('select system_field  from `' . _DB_PREFIX_ .
                    'pfi_import_feed_fields_csv` where feed_id=' . (int) $feed_id . ' ORDER BY id');
                foreach ($result as $val) {
                    ++$i;
                    $tabledata[$val['system_field']] = 'col' . $i;
                }
                $attributes = CombinationVccsv::getAttributes($tabledata);

                $list_all_attributes = $Attribute::getAttributes($id_lang);
                foreach ($list_all_attributes as $a) {
                    $associations[$a['id_attribute']] = $a['name'];
                    if (isset($a['id_attribute_group']) && in_array($a['id_attribute'], $combination_list)) {
                        if (
                            isset($attributes['taille']['id_attribute_group'])
                            && ($a['id_attribute_group'] == $attributes['taille']['id_attribute_group'])
                        ) {
                            $taille = $a['name'];
                        }
                        if (
                            isset($attributes['couleur']['id_attribute_group'])
                            && ($a['id_attribute_group'] == $attributes['couleur']['id_attribute_group'])
                        ) {
                            $couleur = $a['name'];
                        }
                    }
                }
                // Si les champs taille et couleur ne sont pas définis, on utilise le 1er et 2nd attribut
                if (empty($taille) && empty($couleur)) {
                    if (!empty($associations[$combination_list[0]])) {
                        $taille = $associations[$combination_list[0]];
                    }
                    if (!empty($associations[$combination_list[1]])) {
                        $couleur = $associations[$combination_list[1]];
                    }
                }
                $taille = Tools::replaceAccentedChars($taille);
                $taille = Tools::substr(preg_replace('/[^0-9A-Za-z :\.\,\(\)\?!\+&@_-]/i', ' ', $taille), 0, 19);

                $couleur = Tools::replaceAccentedChars($couleur);
                $couleur = Tools::substr(preg_replace('/[^0-9A-Za-z :\.\,\(\)\?!\+&@_-]/i', ' ', $couleur), 0, 19);
            }
            $free = $sc->isFreeCodeArt($softwareid, $reference_combination);
            // L'article existe sur Rezomatic, on le met à jour
            if (!$free) {
                $art = $sc->updateArticle(
                    $softwareid,
                    $reference_combination,
                    $name,
                    $rayon,
                    $fam,
                    $sfam,
                    $wholesale_price,
                    $tax,
                    $final_price,
                    $final_weight,
                    $taille,
                    $couleur,
                    $loyalty,
                    $ecotax,
                    $isvirtual,
                    null,
                    null,
                    null,
                    null,
                    $wholesale_price,
                    $condition,
                    null,
                    null,
                    null,
                    $Product->$reference_field
                );
                $output .= "Export declinaison \"".$name." ".$taille." ".$couleur."\" (". $reference_combination . ") vers Rezomatic\n";
                // $output .= 'Prestashop to Rezomatic : syncCombination ' . $reference_combination . ' updated\n';
                // $output .= print_r((array) $art, true) . '\n';
            } else {
                $art = $sc->createArticle(
                    $softwareid,
                    $reference_combination,
                    $name,
                    $rayon,
                    $fam,
                    $sfam,
                    $wholesale_price,
                    $tax,
                    $final_price,
                    $final_weight,
                    $taille,
                    $couleur,
                    $loyalty,
                    $ecotax,
                    $isvirtual,
                    '',
                    '',
                    '',
                    '',
                    $wholesale_price,
                    $condition,
                    '',
                    '',
                    '',
                    $Product->$reference_field
                );
                $output .= "Export declinaison \"".$name." ".$taille." ".$couleur."\" (". $reference_combination . ") vers Rezomatic\n";
                // $output .= 'Prestashop to Rezomatic : syncCombination ' . $reference_combination . ' created\n';
                // $output .= 'EAN ' . $ean . '\n';
                // $output .= print_r((array) $art, true) . '\n';
            }
            if ($ean != '') {
                $free_ean = $sc->isFreeCodeArt($softwareid, $ean);
                if ($free_ean) {
                    $sc->addAssociationCodeArticle($softwareid, $reference_combination, $ean);
                }
            }
        } catch (SoapFault $exception) {
            $output .= Vccsv::logError($exception);
        }

        return $output;
    }

    /**
     * Synchronisation des déclinaisons PS -> Rezomatic
     * VERSION CORRIGÉE
     *
     * @param $id_product_attribute
     *
     * @return string
     */
    public static function combinationSync($id_product_attribute)
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
                $Combination = new Combination($id_product_attribute);
                $Product = new Product($Combination->id_product, false, $id_lang);

                // Récupérer la référence du produit parent pour codeDeclinaison
                $product_parent_reference = $Product->$reference_field;

                // Des
                $name = Tools::replaceAccentedChars($Product->name);
                $name = Tools::substr(trim(preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&\,@_-]/i', ' ', $name)), 0, 119);
                if ($Combination->wholesale_price > 0) {
                    $wholesale_price = $Combination->wholesale_price;
                } else {
                    $wholesale_price = $Product->wholesale_price;
                }

                //Extraction de la hiérarchie complète : rayon > fam > sFam
                $rayon = null;
                $fam = null;
                $sfam = null;

                if ($allow_categoryexport == 1) {
                    $id_category_default = $Product->id_category_default;
                    // Récupération du chemin complet de la catégorie
                    if (version_compare(_PS_VERSION_, '1.6', '>') === true) {
                        $full_path = Tools::getPath('', $id_category_default);
                    } else {
                        $full_path = Tools::getPath($id_category_default);
                    }
                    if (!empty($full_path)) {
                        // Nettoyage du chemin 
                        $clean_path = Tools::replaceAccentedChars(strip_tags($full_path));
                        $clean_path = preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&\,@_>-]/i', ' ', $clean_path);
                        $clean_path = trim(preg_replace('/\s{2,}/', ' ', $clean_path));
                        // Séparation des niveaux de hiérarchie (utilise " > " comme séparateur)
                        $hierarchy_levels = array_map('trim', explode('>', $clean_path));

                        if (isset($hierarchy_levels[0])) $rayon = $hierarchy_levels[0];
                        if (isset($hierarchy_levels[1])) $fam = $hierarchy_levels[1];
                        if (isset($hierarchy_levels[2])) $sfam = $hierarchy_levels[2];
                    }
                }

                // Fournisseur
                if (!empty($Product->id_supplier)) {
                    $id_supplier = $Product->id_supplier;
                    $supplier = new Supplier($id_supplier);
                    $four = Tools::replaceAccentedChars($supplier->name);
                    $four = Tools::substr(trim(preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&\,@_-]/i', ' ', $four)), 0, 29);
                } else {
                    $four = null;
                }

                $reference_combination = $Combination->$reference_field;

                // Si la référence est vide, pas d'export
                if (empty($reference_combination) && (!is_numeric($reference_combination))) {
                    return '';
                }

                // Si déjà exportée dans le même script, pas de nouvel export
                if ((!empty(ProductVccsv::$exported)) && in_array($reference_combination, ProductVccsv::$exported)) {
                    return '';
                } else {
                    ProductVccsv::$exported[] = $reference_combination;
                }

                $loyalty = '';
                $ecotax = $Combination->ecotax;
                $tax = Tax::getProductTaxRate((int) $Combination->id_product);
                $isvirtual = 0;
                $ean = $Combination->ean13;
                if ($Product->condition == 'new') {
                    $condition = 1;
                } else {
                    $condition = 0;
                }

                $final_price = number_format(($Combination->price + $Product->price) * (1 + ($tax / 100)) + $Combination->ecotax, 6, '.', '');
                $final_weight = $Combination->weight + $Product->weight;

                $taille = '';
                $couleur = '';
                $associations = CombinationVccsv::getGroupAttributes($id_product_attribute, $id_lang);

                // Récupère les infos pour les attributs
                $feed_id = 1;
                $i = 0;
                $tabledata = [];
                $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('select system_field  from `' . _DB_PREFIX_ .
                    'pfi_import_feed_fields_csv` where feed_id=' . (int) $feed_id . ' ORDER BY id');
                foreach ($result as $val) {
                    ++$i;
                    $tabledata[$val['system_field']] = 'col' . $i;
                }
                if (!empty($tabledata)) {
                    $attributes = CombinationVccsv::getAttributes($tabledata);
                    if (!empty($attributes['taille'])) {
                        foreach ($associations as $a) {
                            if ($a['id_attribute_group'] == $attributes['taille']['id_attribute_group']) {
                                $taille = $a['value'];
                            }
                        }
                    }
                    if (!empty($attributes['couleur'])) {
                        foreach ($associations as $a) {
                            if ($a['id_attribute_group'] == $attributes['couleur']['id_attribute_group']) {
                                $couleur = $a['value'];
                            }
                        }
                    }
                }

                // Si les champs taille et couleur ne sont pas définis, on utilise le 1er et 2nd attribut
                if (empty($taille) && empty($couleur)) {
                    if (!empty($associations[0]['value'])) {
                        $taille = $associations[0]['value'];
                    }
                    if (!empty($associations[1]['value'])) {
                        $couleur = $associations[1]['value'];
                    }
                }

                // Gestion des caractères spéciaux et virgules
                $taille = Tools::replaceAccentedChars($taille);
                $taille = preg_replace('/[^0-9A-Za-z :\.\,\(\)\?!\+&@_\/-]/i', ' ', $taille);
                $taille = trim(preg_replace('/\s{2,}/', ' ', $taille));
                $taille = Tools::substr($taille, 0, 19);

                $couleur = Tools::replaceAccentedChars($couleur);
                $couleur = preg_replace('/[^0-9A-Za-z :\.\,\(\)\?!\+&@_\/-]/i', ' ', $couleur);
                $couleur = trim(preg_replace('/\s{2,}/', ' ', $couleur));
                $couleur = Tools::substr($couleur, 0, 19);

                $free = $sc->isFreeCodeArt($softwareid, $reference_combination);

                // L'article existe sur Rezomatic, on le met à jour
                if (!$free) {
                    $art = $sc->updateArticle(
                        $softwareid,
                        $reference_combination,
                        $name,
                        $rayon,
                        $fam,
                        $sfam,
                        $wholesale_price,
                        $tax,
                        $final_price,
                        $final_weight,
                        $taille,
                        $couleur,
                        $loyalty,
                        $ecotax,
                        $isvirtual,
                        null,
                        null,
                        null,
                        null,
                        $wholesale_price,
                        $condition,
                        null,
                        null,
                        $four,
                        $product_parent_reference
                    );
                    $output .= "Export declinaison \"".$name." ".$taille." ".$couleur."\" (". $reference_combination . ") vers Rezomatic\n";
                    // $output .= 'Prestashop to Rezomatic : combinationSync ' . $reference_combination . ' updated (codeDeclinaison: ' . $product_parent_reference . ')\n';
                    // $output .= print_r((array) $art, true) . '\n';
                } else {
                    $art = $sc->createArticle(
                        $softwareid,
                        $reference_combination,
                        $name,
                        $rayon,
                        $fam,
                        $sfam,
                        $wholesale_price,
                        $tax,
                        $final_price,
                        $final_weight,
                        $taille,
                        $couleur,
                        $loyalty,
                        $ecotax,
                        $isvirtual,
                        '',
                        '',
                        '',
                        '',
                        $wholesale_price,
                        $condition,
                        '',
                        '',
                        $four,
                        $product_parent_reference
                    );
                    $output .= "Export declinaison \"".$name." ".$taille." ".$couleur."\" (". $reference_combination . ") vers Rezomatic\n";
                    // $output .= 'Prestashop to Rezomatic : combinationSync ' . $reference_combination . ' created (codeDeclinaison: ' . $product_parent_reference . ')\n';
                    // $output .= print_r((array) $art, true) . '\n';
                }

                if ($ean != '') {
                    $free_ean = $sc->isFreeCodeArt($softwareid, $ean);
                    if ($free_ean) {
                        $sc->addAssociationCodeArticle($softwareid, $reference_combination, $ean);
                    }
                }
            } catch (SoapFault $exception) {
                $output .= Vccsv::logError($exception);
            }
        }

        return $output;
    }
}
