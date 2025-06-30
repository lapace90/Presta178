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
 * Synchronisation des clients
 */
class CustomerVccsv extends Vccsv
{

    /**
     * @var string
     */
    public static $last_import_stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0];

    /**
     * customerSync function.
     *
     * @static
     *
     * @param mixed $id_customer
     *
     * @return void
     */
    public static function customerSync($id_customer, $forceExport = false)
    {
        $allow_customerexport = Configuration::get('PI_ALLOW_CUSTOMEREXPORT');
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $output = '';
        if ($forceExport || ($allow_customerexport == 1)) {
            $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
            try {
                $sc = new SoapClient($feedurl);
                $customer = new Customer($id_customer, ['keep_alive' => false]);
                $name = $customer->lastname . ' ' . $customer->firstname;
                $name = preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&#\,@_-]/i', ' ', $name);
                $name = trim($name);
                $id_address = Address::getFirstCustomerAddressId($id_customer);
                $address = new Address($id_address);
                $id_gender = $customer->id_gender;
                $mr = '';
                if ($id_gender == 1) {
                    $mr = 'Mr';
                } elseif ($id_gender == 2) {
                    $mr = 'Mme';
                } else {
                    $mr = 'Mr';
                }

                $addresse = (empty($address->address1) ? '' : $address->address1);
                $addresse = Tools::substr(preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&#\,@_-]/i', ' ', $addresse), 0, 49);
                $postcode = $address->postcode;
                $city = (empty($address->city) ? '' : $address->city);
                $city = Tools::substr(preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&#\,@_-]/i', ' ', $city), 0, 49);
                $phone = $address->phone;
                $email = $customer->email;
                if ($email == 'NOSEND-EBAY') {
                    $email = 'emailebay' . $id_customer . '@remplacementebay.com';
                }
                $birthday = $customer->birthday;
                $country = Country::getIsoById($address->id_country);
                $raisonSociale = Tools::replaceAccentedChars(empty($customer->company) ? '' : $customer->company);
                $raisonSociale = preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&#\,@_-]/i', ' ', $raisonSociale);
                $siret = (empty($customer->siret) ? '' : $customer->siret);
                $ape = (empty($customer->ape) ? '' : $customer->ape);
                $numtva = (empty($address->vat_number) ? '' : $address->vat_number);
                $newsletter = $customer->newsletter;
                // Recherche du num client Rezomatic dans la base Prestashop
                /*$api_customerid = Db::getInstance()->getValue('select api_customerid from ' . _DB_PREFIX_ .
                    'pfi_customer_apisync where system_customerid= ' . (int) $id_customer);*/
                $api_customerid = false;
                // Si pas trouvé, on recherche le client par son e-mail
                if (!$api_customerid) {
                    $clients = $sc->getClientsFromEMail($softwareid, $email);
                    if (!isset($clients->client)) {
                        $api_customerid = false;
                    } elseif (is_array($clients->client)) {
                        $client = current($clients->client);
                        $api_customerid = $client->num;
                    } else {
                        $client = $clients->client;
                        $api_customerid = $client->num;
                    }
                }
                // Si le num client Rezomatic est connu, on update le client existant
                if ($api_customerid) {
                    try {
                        $cli = $sc->updateClient(
                            $softwareid,
                            $api_customerid,
                            $mr,
                            $name,
                            $addresse,
                            NULL,
                            NULL,
                            $postcode,
                            $city,
                            $phone,
                            $email,
                            $birthday,
                            $country,
                            NULL,         // accesCatalogue
                            NULL,         // baseHT
                            NULL,         // grilleTarif
                            NULL,         // detaxe
                            NULL,         // iban
                            NULL,         // bic
                            NULL,         // profession
                            $raisonSociale,
                            $siret,
                            $ape,
                            NULL,         // RCS
                            NULL,         // Commercial
                            NULL,         // Condition Reglement
                            NULL,         // Mode Reglement
                            $numtva,
                            NULL,         // Id Alpha
                            NULL,         // B2B
                            NULL,         // Entrepot
                            NULL,         // Fidelite
                            $newsletter,
                            $newsletter
                        );
                        $output .= parent::l('Customer') . ' ' . $id_customer . ' ' . parent::l('updated') . '\n';
                        $output .= print_r((array) $cli, true) . '\n';
                    } catch (SoapFault $exception) {
                        $output .= Vccsv::logError($exception);
                        $api_customerid = false;
                    }
                }
                // Si pas de num client Rezomatic, on crée le client Rezomatic
                if (!$api_customerid) {
                    try {
                        $api_customerid = $sc->getFreeCodeClient($softwareid);
                        $cli = $sc->createClient(
                            $softwareid,
                            $api_customerid,
                            $mr,
                            $name,
                            $addresse,
                            '',
                            '',
                            $postcode,
                            $city,
                            $phone,
                            $email,
                            $birthday,
                            $country,
                            '',         // accesCatalogue
                            false,      // baseHT
                            '',         // grilleTarif
                            false,      // detaxe
                            '',         // iban
                            '',         // bic
                            '',         // profession
                            $raisonSociale,
                            $siret,
                            $ape,
                            '',         // RCS
                            '',         // Commercial
                            '',         // Condition Reglement
                            '',         // Mode Reglement
                            $numtva,
                            '',         // Id Alpha
                            false,      // B2B
                            '',         // Entrepot
                            true,       // Fidelite
                            $newsletter,
                            $newsletter
                        );
                        $output .= parent::l('Customer') . ' ' . $api_customerid . ' ' . parent::l('created') . '\n';
                        $output .= print_r((array) $cli, true) . '\n';
                    } catch (SoapFault $exception) {
                        $output .= Vccsv::logError($exception);
                        $api_customerid = false;
                    }
                }
                // Ajout Liaison ID Prestashop / ID Rezomatic
                if ($api_customerid) {
                    $array_data = [
                        'system_customerid' => (int) $id_customer,
                        'api_customerid' => (int) $api_customerid,
                    ];
                    Db::getInstance()->insert('pfi_customer_apisync', $array_data, false, false, Db::ON_DUPLICATE_KEY, true);
                }
            } catch (SoapFault $exception) {
                $output .= Vccsv::logError($exception);
            }
        }

        return $output;
    }

    /**
     * registerDiscountRezomatic function.
     *
     * @static
     *
     * @param mixed $id_customer
     * @param mixed $amount
     * @param mixed $name
     * @param bool $register (default: false)
     * @param int $id_currency (default: 0)
     *
     * @return void
     */
    public static function registerDiscountRezomatic($id_customer, $amount, $name, $id_currency = 0)
    {
        $cartRule = new CartRule();
        $cartRule->reduction_amount = (float) $amount;
        $cartRule->reduction_tax = 1;
        $cartRule->quantity = 1;
        $cartRule->quantity_per_user = 1;
        $cartRule->date_from = date('Y-m-d H:i:s', time());
        $cartRule->date_to = date('Y-m-d H:i:s', time() + 31536000); // + 1 year
        $cartRule->code = $name;
        $languages = Language::getLanguages();
        $array = [];
        foreach ($languages as $language) {
            $array[$language['id_lang']] = 'Bon de reduction';
        }
        $cartRule->name = $array;
        $cartRule->id_customer = (int) $id_customer;
        $cartRule->reduction_currency = (int) $id_currency;
        if ($cartRule->add()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * loyaltySync function.
     *
     * @static
     *
     * @param mixed $id_customer
     * @param mixed $email_customer
     *
     * @return void
     */
    public static function loyaltySync($id_customer, $email_customer)
    {
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $output = '';
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        try {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            $clients = $sc->getClientsFromEMail($softwareid, $email_customer);
            if (isset($clients->client) && is_object($clients->client)) {
                $rm = $sc->getBonsFromClientNum($softwareid, $clients->client->num);
                if (isset($rm->bons)) {
                    if (is_array($rm->bons)) {
                        $bons = $rm->bons;
                    } else {
                        $bons = [$rm->bons];
                    }
                    foreach ($bons as $bon) {
                        if (!CartRule::cartRuleExists($bon->codeBon)) {
                            if ($bon->dateValidation == 'N/D' && $bon->dateAnnulation == 'N/D') {
                                if (self::registerDiscountRezomatic(
                                    $id_customer,
                                    $bon->valeur,
                                    $bon->codeBon
                                )) {
                                    $output .= 'Coupon ' . $bon->codeBon . ' added\n';
                                } else {
                                    $output .= 'Coupon ' . $bon->codeBon . ' not added\n';
                                }
                            }
                        } else {
                            if ($bon->dateValidation != 'N/D' || $bon->dateAnnulation != 'N/D') {
                                $sql = ' Update `' . _DB_PREFIX_ . 'cart_rule` set active=0 where code = \'' .
                                    pSQL($bon->codeBon) . '\'';
                                Db::getInstance()->Execute($sql);
                            }
                            $output .= 'Coupon ' . $bon->codeBon . ' exists\n';
                        }
                    }
                } else {
                    $output .= 'No coupon for customer ' . $email_customer . ' !\n';
                }
            } else {
                $output = 'No customer for mail ' . $email_customer . ' !\n';
            }
        } catch (SoapFault $exception) {
            $output = Vccsv::logError($exception);
        }

        return $output;
    }

    /**
     * importCustomer function.
     * VERSION MODIFIÉE : Import basé sur l'email + compatible avec la tâche cron existante
     *
     * @static
     *
     * @return string
     */
    public static function importCustomer()
    {
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $allow_customerimport = Configuration::get('PI_ALLOW_CUSTOMERIMPORT');
        $customer_id = '';
        $output = '';
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $timestamp = Configuration::get('PI_LAST_CRON');

        $clients_processed = 0;
        $clients_created = 0;
        $clients_updated = 0;
        $clients_errors = 0;


        // Log de début
        $output .= "=== CUSTOMER IMPORT STARTED (Email-based) ===\n";
        $output .= "Timestamp: " . $timestamp . "\n";

        if ($allow_customerimport == 1) {
            try {
                $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                $art = $sc->getNewClients($softwareid, $timestamp);
                $i = 0;

                if (empty($art->client)) {
                    $clientsrezo = [];
                    $output .= "No new clients found since last cron\n";
                } elseif (is_array($art->client)) {
                    $clientsrezo = $art->client;
                } else {
                    $clientsrezo = [$art->client];
                }

                $clients_created = 0;
                $clients_updated = 0;
                $clients_errors = 0;

                foreach ($clientsrezo as $item) {
                    $clients_processed++;
                    ++$i;
                    $default_language_id = (int) Configuration::get('PS_LANG_DEFAULT');
                    $customerlist = (array) $item;

                    // Traitement du genre
                    $gender = Tools::strtolower($customerlist['etCiv']);
                    switch ($gender) {
                        case 'mle':
                        case 'mlle':
                        case 'melle':
                        case 'mademoiselle':
                        case 'mme':
                        case 'ms':
                        case 'miss':
                        case 'madame':
                        case 'femme':
                            $gender = 2;
                            break;
                        default:
                            $gender = 1;
                            break;
                    }

                    // Traitement nom/prénom
                    $arrcust = explode(' ', $customerlist['noPrn']);
                    $lastname = preg_replace('/[^A-Za-z ]/i', ' ', array_shift($arrcust));
                    $firstname = preg_replace('/[^A-Za-z ]/i', ' ', implode(' ', $arrcust));
                    $email = $customerlist['mail'];
                    $phone = (empty($customerlist['tel'])) ? '0000000000' : $customerlist['tel'];
                    $ann = (empty($customerlist['ann'])) ? '1970-01-01' : $customerlist['ann'];
                    $api_customerid = $customerlist['num'];
                    $newsletter = $customerlist['acceptCommCommerciale'];

                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $output .= parent::l('Email incorrect') . ' : ' . $email . "\n";
                        $clients_errors++;
                        continue;
                    }

                    // NOUVELLE LOGIQUE : Recherche d'abord par email
                    $customer_id = null;
                    $existing_customer = null;

                    // 1. Chercher d'abord un client existant par email dans PrestaShop
                    $existing_customer_id = Customer::customerExists($email, true, false);

                    if ($existing_customer_id) {
                        // Client trouvé par email
                        $customer_id = $existing_customer_id;
                        $existing_customer = new Customer($customer_id);
                        $output .= 'Customer found by email: ' . $email . ' (ID: ' . $customer_id . ')' . "\n";

                        // Vérifier/créer/mettre à jour la liaison API
                        $existing_api_link = Db::getInstance()->getValue(
                            'SELECT api_customerid FROM ' . _DB_PREFIX_ .
                                'pfi_customer_apisync WHERE system_customerid = ' . (int) $customer_id
                        );

                        if (!$existing_api_link) {
                            // Créer la liaison
                            $array_data = [
                                'system_customerid' => (int) $customer_id,
                                'api_customerid' => (int) $api_customerid,
                            ];
                            Db::getInstance()->insert('pfi_customer_apisync', $array_data, false, false, Db::ON_DUPLICATE_KEY, true);
                            $output .= 'API link created for existing customer: ' . $customer_id . "\n";
                        } elseif ($existing_api_link != $api_customerid) {
                            // Mettre à jour la liaison
                            Db::getInstance()->update(
                                'pfi_customer_apisync',
                                ['api_customerid' => (int) $api_customerid],
                                'system_customerid = ' . (int) $customer_id
                            );
                            $output .= 'API link updated for existing customer: ' . $customer_id . "\n";
                        }
                    } else {
                        // 2. Si pas trouvé par email, chercher par api_customerid (fallback)
                        $customer_id = Db::getInstance()->getValue(
                            'SELECT system_customerid FROM ' . _DB_PREFIX_ .
                                'pfi_customer_apisync WHERE api_customerid = ' . (int) $api_customerid .
                                ' ORDER BY system_customerid DESC'
                        );

                        if ($customer_id) {
                            $found = Customer::customerIdExistsStatic($customer_id);
                            if (!$found) {
                                $customer_id = null; // Le client n'existe plus
                            } else {
                                $existing_customer = new Customer($customer_id);
                                $output .= 'Customer found by API ID: ' . $api_customerid . ' (ID: ' . $customer_id . ')' . "\n";
                            }
                        }
                    }

                    // 3. Traitement selon le résultat
                    if ($customer_id && $existing_customer) {
                        // Client existant - mise à jour
                        $existing_customer->firstname = (is_numeric($firstname) ? '-' : $firstname);
                        $existing_customer->lastname = (is_numeric($lastname) ? '-' : $lastname);
                        $existing_customer->birthday = $ann;
                        $existing_customer->id_gender = $gender;
                        $existing_customer->newsletter = $newsletter;

                        try {
                            $existing_customer->update();
                            $output .= 'Customer updated: ' . $customer_id . ' (' . $email . ')' . "\n";
                            $clients_updated++;

                            // Mettre à jour l'adresse principale si elle existe
                            $id_address = Address::getFirstCustomerAddressId($customer_id);
                            if ($id_address) {
                                $address = new Address($id_address);
                                $address->firstname = (is_numeric($firstname) ? '-' : $firstname);
                                $address->lastname = (is_numeric($lastname) ? '-' : $lastname);
                                $address->address1 = (empty($customerlist['add1']) ? $address->address1 : $customerlist['add1']);
                                $address->address2 = (empty($customerlist['add2']) ? $address->address2 : $customerlist['add2']);
                                $address->postcode = (empty($customerlist['cp']) ? $address->postcode : $customerlist['cp']);
                                $address->city = (empty($customerlist['ville']) ? $address->city : $customerlist['ville']);
                                $address->phone = str_replace(' ', '', $phone);
                                $address->id_country = Country::getByIso(empty($customerlist['codePays']) ?
                                    Configuration::get('PS_LOCALE_COUNTRY') : $customerlist['codePays']);

                                try {
                                    $address->update();
                                    $output .= 'Address updated for customer: ' . $customer_id . "\n";
                                } catch (Exception $e) {
                                    $output .= Vccsv::logError($e);
                                }
                            }
                        } catch (Exception $e) {
                            $output .= Vccsv::logError($e);
                            $clients_errors++;
                        }
                    } else {
                        // Nouveau client - création
                        $output .= 'Creating new customer: ' . $email . "\n";

                        $customer = new Customer();
                        $customer->firstname = (is_numeric($firstname) ? '-' : $firstname);
                        $customer->lastname = (is_numeric($lastname) ? '-' : $lastname);
                        $customer->passwd = '123456789';
                        $customer->passwd = Tools::hash($customer->passwd);
                        $customer->email = $email;
                        $customer->birthday = $ann;
                        $customer->active = 1;
                        $customer->id_shop = 1;
                        $customer->id_shop_group = 1;
                        $customer->id_gender = $gender;
                        $customer->id_lang = $default_language_id;
                        $customer->newsletter = $newsletter;

                        try {
                            $customer->add();
                            $output .= 'Customer created: ' . $email . ' (ID: ' . $customer->id . ')' . "\n";
                            $customer_id = $customer->id;
                            $clients_created++;

                            // Créer la liaison API
                            $array_data = [
                                'system_customerid' => (int) $customer_id,
                                'api_customerid' => (int) $api_customerid,
                            ];
                            Db::getInstance()->insert('pfi_customer_apisync', $array_data);

                            // Créer l'adresse
                            $address = new Address();
                            $address->id_country = Country::getByIso(empty($customerlist['codePays']) ?
                                Configuration::get('PS_LOCALE_COUNTRY') : $customerlist['codePays']);
                            $address->id_customer = $customer_id;
                            $address->alias = (is_numeric($firstname) ? 'Alias' : $firstname);
                            $address->firstname = (is_numeric($firstname) ? '-' : $firstname);
                            $address->lastname = (is_numeric($lastname) ? '-' : $lastname);
                            $address->address1 = (empty($customerlist['add1']) ? '-' : $customerlist['add1']);
                            $address->address2 = (empty($customerlist['add2']) ? '-' : $customerlist['add2']);
                            $address->postcode = (empty($customerlist['cp'])) ? '00000' : $customerlist['cp'];
                            $address->city = (empty($customerlist['ville'])) ? 'default' : $customerlist['ville'];
                            $address->phone = str_replace(' ', '', $phone);

                            try {
                                $address->add();
                                $output .= 'Address created for customer: ' . $customer_id . "\n";
                            } catch (Exception $e) {
                                $output .= Vccsv::logError($e);
                            }
                        } catch (Exception $e) {
                            $output .= Vccsv::logError($e);
                            $clients_errors++;
                        }
                    }
                }

                // Résumé
                $output .= "=== CUSTOMER IMPORT SUMMARY ===\n";
                $output .= "Clients processed: " . $i . "\n";
                $output .= "Clients created: " . $clients_created . "\n";
                $output .= "Clients updated: " . $clients_updated . "\n";
                $output .= "Clients errors: " . $clients_errors . "\n";
                $output .= "=== CUSTOMER IMPORT FINISHED ===\n";
            } catch (SoapFault $exception) {
                $output .= Vccsv::logError($exception);
            }
        } else {
            $output .= 'Customer import disabled in configuration' . "\n";
        }

        // Sauvegarder les stats
        self::$last_import_stats = [
            'processed' => $clients_processed,
            'created' => $clients_created,
            'updated' => $clients_updated,
            'errors' => $clients_errors
        ];

        return $output; // Garder le return normal
    }
}
