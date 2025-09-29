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

class OrderVccsv extends Vccsv
{
    public static function getCartRulesRezomatic($order)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                SELECT *
                FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr
                INNER JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON ocr.id_cart_rule = cr.id_cart_rule
                WHERE ocr.`id_order` = ' . (int) $order);
    }

    /**
     * orderSync function.
     *
     * @static
     *
     * @param mixed $id_order
     *
     * @return void
     */
    public static function orderSync($id_order)
    {
        $allow_orderexport = Configuration::get('PI_ALLOW_ORDEREXPORT');
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $onlyvalid = Configuration::get('PI_VALID_ORDER_ONLY');
        $output = 'Order - ' . (int) $id_order . ' - ';
        $order = new Order($id_order);
        $products = $order->getProductsDetail();
        if (!$allow_orderexport) {
            $output .= parent::l('Order export not allowed') . '\n';
            return $output;
        } elseif (empty($products)) {
            $output .= parent::l('Order empty') . ' ' . $id_order . '\n';
        } else {
            $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
            try {
                $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                // Vérification existance de la commande
                $api_orderid = Db::getInstance()->getValue('select api_orderid from ' . _DB_PREFIX_ .
                    'pfi_order_apisync where system_orderid=' . (int) $id_order);
                if (!$api_orderid) {
                    $id_customer = $order->id_customer;
                    // Export du client
                    $output .= CustomerVccsv::customerSync($id_customer, true);
                    $api_customerid = Db::getInstance()->getValue('select api_customerid from ' . _DB_PREFIX_ .
                        'pfi_customer_apisync where system_customerid=' . (int) $id_customer);
                    // Si client exporté
                    if ($api_customerid) {
                        // Export de la commande
                        $neworderid = $sc->getNextCommandeNum($softwareid);
                        $pdv = Configuration::get('SYNC_STOCK_PDV');
                        $output .= 'Next order num : ' . $neworderid . '\n';
                        foreach ($products as $product) {
                            // Si c'est un Pack, champs reference uniquement
                            if (Pack::isPack($product['product_id'])) {
                                $reference_field = 'reference';
                            } else {
                                $reference_field = Configuration::get('PI_PRODUCT_REFERENCE');
                            }
                            $product_reference = $product['product_' . $reference_field];
                            // Si il s'agit d'un pack et que le code Rezomatic ne commence pas par LT__,
                            // On le rajoute
                            if (Pack::isPack($product['product_id'])
                                && (Tools::substr($product_reference, 0, 4) != 'LT__')) {
                                $product_reference = 'LT__' . $product_reference;
                            }
                            $product_quantity = $product['product_quantity'];
                            $unit_price_tax_incl = $product['unit_price_tax_incl'];
                            $total_price_tax_incl = $product['total_price_tax_incl'];
                            $sc->addCommande(
                                $softwareid,
                                $neworderid,
                                $api_customerid,
                                $product_reference,
                                $product_quantity,
                                $total_price_tax_incl,
                                $pdv
                            );
                            $output .= parent::l('Product') . ' ' . $product_reference . ' ' .
                                        parent::l('added. Total price :') . ' ' . $total_price_tax_incl . ' ' .
                                        parent::l('Quantity') . ' : ' . $product_quantity . ' ' . parent::l('Unit price') .
                                        ' ' . $unit_price_tax_incl . '\n';
                        }
                        $output .= parent::l('Order') . ' ' . $id_order . ' (' . $neworderid . ') ' . parent::l('created') . '\n';
                        // Remise
                        $codes = self::getCartRulesRezomatic($id_order);
                        foreach ($codes as $code) {
                            $code_detail = $code['code'];
                            $code_amount = $code['value'];
                            if ((!empty($code_detail)) && ($code_detail[0] == '-')) {
                                $sc->addQualifiedBonRemise(
                                    $softwareid,
                                    $neworderid,
                                    $api_customerid,
                                    $code_detail,
                                    $pdv
                                );
                            } else {
                                $sc->addbonremise($softwareid, $neworderid, $api_customerid, $code_amount, $pdv);
                            }
                            $output .= parent::l('Discount') . ' ' . $code_amount . ' - ' . $code_detail . ' - ' .
                                        parent::l('created') . '\n';
                        }
                        // HT ?
                        if (($order->total_paid_tax_incl > 0)
                            && ($order->total_paid_tax_incl == $order->total_paid_tax_excl)) {
                            $sc->setModeHT($softwareid, $neworderid, $api_customerid, $pdv);
                        }
                        // Port
                        $shippingamount = $order->total_shipping_tax_incl;
                        $sc->addFraisPort($softwareid, $neworderid, $api_customerid, $shippingamount, $pdv);
                        $output .= parent::l('Shipping') . ' ' . $shippingamount . ' ' . parent::l('created') . '\n';
                        // Ref.
                        $sc->addNote($softwareid, $neworderid, 'Ref. ' . $order->reference);
                        // Payment
                        $paymentmethod = Tools::strtoupper(Tools::replaceAccentedChars($order->payment));
                        if (($rg = Configuration::get('PI_RG1'))
                            && (!empty($rg)) && (Tools::strtoupper($rg) == $paymentmethod)) {
                            $paymentmethod = 'RG1';
                        } elseif (($rg = Configuration::get('PI_RG2'))
                            && (!empty($rg)) && (Tools::strtoupper($rg) == $paymentmethod)) {
                            $paymentmethod = 'RG2';
                        } elseif (($rg = Configuration::get('PI_RG3'))
                            && (!empty($rg)) && (Tools::strtoupper($rg) == $paymentmethod)) {
                            $paymentmethod = 'RG3';
                        } elseif (($rg = Configuration::get('PI_RG4'))
                            && (!empty($rg)) && (Tools::strtoupper($rg) == $paymentmethod)) {
                            $paymentmethod = 'RG4';
                        } elseif (($rg = Configuration::get('PI_RG5'))
                            && (!empty($rg)) && (Tools::strtoupper($rg) == $paymentmethod)) {
                            $paymentmethod = 'RG5';
                        } elseif (($rg = Configuration::get('PI_RG6'))
                            && (!empty($rg)) && (Tools::strtoupper($rg) == $paymentmethod)) {
                            $paymentmethod = 'RG6';
                        } elseif (($rg = Configuration::get('PI_RG7'))
                            && (!empty($rg)) && (Tools::strtoupper($rg) == $paymentmethod)) {
                            $paymentmethod = 'RG7';
                        } elseif (($rg = Configuration::get('PI_RG8'))
                            && (!empty($rg)) && (Tools::strtoupper($rg) == $paymentmethod)) {
                            $paymentmethod = 'RG8';
                        } elseif (($rg = Configuration::get('PI_RG9'))
                            && (!empty($rg)) && (Tools::strtoupper($rg) == $paymentmethod)) {
                            $paymentmethod = 'RG9';
                        } elseif (($rg = Configuration::get('PI_RG10'))
                            && (!empty($rg)) && (Tools::strtoupper($rg) == $paymentmethod)) {
                            $paymentmethod = 'RG10';
                        } elseif (in_array($paymentmethod, ['CHEQUE', 'BANK WIRE', 'PAYMENT BY CHECK'])) {
                            $paymentmethod = 'CHQ';
                        } elseif (in_array($paymentmethod, [
                            'CREDITCARD',
                            'PAIEMENT PAR CARTE BANCAIRE',
                            'PAIEMENT PAR CB BANQUE POPULAIRE 3D SECURE',
                            'ATOS',
                            'CM-CIC P@IEMENT',
                            'FREEPAY',
                            'PAYLINE',
                            'SOGECOMMERCE',
                        ])) {
                            $paymentmethod = 'CB';
                        }
                        $sc->addModeReglement($softwareid, $neworderid, $api_customerid, $paymentmethod, $pdv);
                        $output .= parent::l('Payment added') . ' ' . $paymentmethod . '\n';
                        $array_data = [
                            'system_orderid' => (int) $id_order,
                            'api_orderid' => (int) $neworderid,
                        ];
                        $api_orderid = $neworderid;
                        Db::getInstance()->insert('pfi_order_apisync', $array_data);
                    } else {
                        $output .= 'Customer error : ' . $order->id_customer . '\n';
                    }
                } else {
                    $output .= parent::l('Order already created') . ' ' . $id_order . '\n';
                }
                // Valid Order
                if ((!$onlyvalid) && $order->valid) {
                    $sc->addStatutCommande($softwareid, $api_orderid, 5); // Prete
                }
            } catch (SoapFault $exception) {
                $output .= Vccsv::logError($exception);
            }
        }

        return $output;
    }

    /**
     * orderCancel function.
     *
     * @static
     *
     * @param mixed $id_order
     *
     * @return void
     */
    public static function orderCancel($id_order)
    {
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $output = '';
        try {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            $api_orderid = Db::getInstance()->getValue('select api_orderid from ' . _DB_PREFIX_ .
                'pfi_order_apisync where system_orderid=' . (int) $id_order);
            if ($api_orderid) {
                if ($sc->annuleCommande($softwareid, $api_orderid)) {
                    $output .= 'Order ' . $id_order . ' (' . $api_orderid . ') cancelled\n';
                }
            } else {
                $output .= 'Order ' . $id_order . ' (' . $api_orderid . ') does not exist\n';
            }
        } catch (SoapFault $exception) {
            $output .= Vccsv::logError($exception);
        }

        return $output;
    }

    /**
     * @edit Definima
     * Récupère les commandes PS dont le status est présent dans le tableau en entrée
     *
     * @param $id_order_states
     *
     * @return array
     */
    public static function getOrderIdsByStatus($id_order_states)
    {
        $sql = 'SELECT id_order, current_state
                FROM ' . _DB_PREFIX_ . 'orders o
                WHERE o.`current_state` IN (' . implode(',', $id_order_states) . ')
                ' . Shop::addSqlRestriction(false, 'o') . '
                ORDER BY invoice_date ASC';
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $orders = [];
        foreach ($result as $order) {
            $orders[] = [
                'id_order' => (int) $order['id_order'],
                'current_state' => (int) $order['current_state'],
            ];
        }

        return $orders;
    }
}
