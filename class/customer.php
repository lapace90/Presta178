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
     * @var array Statistiques du dernier import pour le cron
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
     * Recherche fiable d'un client par email (anti-doublons)
     * 
     * @param string $email
     * @return int|false ID du client ou false si non trouvé
     */
    private static function getCustomerIdByEmail($email)
    {
        if (!Validate::isEmail($email)) {
            return false;
        }
        
        // Utilisation de la méthode native PrestaShop (plus fiable)
        $customer_id = Customer::customerExists($email, true, false);
        
        return $customer_id ? (int)$customer_id : false;
    }

    /**
     * Met à jour un client existant avec vérifications
     * 
     * @param int $customer_id
     * @param array $customerData
     * @return bool
     */
    private static function updateExistingCustomer($customer_id, $customerData)
    {
        try {
            $customer = new Customer($customer_id);
            
            // Mise à jour seulement si les nouvelles valeurs sont valides
            if (!empty($customerData['firstname']) && !is_numeric($customerData['firstname'])) {
                $customer->firstname = $customerData['firstname'];
            }
            if (!empty($customerData['lastname']) && !is_numeric($customerData['lastname'])) {
                $customer->lastname = $customerData['lastname'];
            }
            if (!empty($customerData['birthday']) && $customerData['birthday'] !== '1970-01-01') {
                $customer->birthday = $customerData['birthday'];
            }
            if (isset($customerData['gender'])) {
                $customer->id_gender = $customerData['gender'];
            }
            if (isset($customerData['newsletter'])) {
                $customer->newsletter = $customerData['newsletter'];
            }
            
            return $customer->update();
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Met à jour l'adresse principale d'un client
     * 
     * @param int $customer_id
     * @param array $addressData
     * @return bool
     */
    private static function updateCustomerAddress($customer_id, $addressData)
    {
        try {
            $id_address = Address::getFirstCustomerAddressId($customer_id);
            if (!$id_address) {
                return false;
            }
            
            $address = new Address($id_address);
            
            // Mise à jour seulement si les nouvelles valeurs sont valides
            if (!empty($addressData['firstname']) && !is_numeric($addressData['firstname'])) {
                $address->firstname = $addressData['firstname'];
            }
            if (!empty($addressData['lastname']) && !is_numeric($addressData['lastname'])) {
                $address->lastname = $addressData['lastname'];
            }
            if (!empty($addressData['address1'])) {
                $address->address1 = $addressData['address1'];
            }
            if (!empty($addressData['address2'])) {
                $address->address2 = $addressData['address2'];
            }
            if (!empty($addressData['postcode'])) {
                $address->postcode = $addressData['postcode'];
            }
            if (!empty($addressData['city'])) {
                $address->city = $addressData['city'];
            }
            if (!empty($addressData['phone'])) {
                $address->phone = str_replace(' ', '', $addressData['phone']);
            }
            if (!empty($addressData['id_country'])) {
                $address->id_country = $addressData['id_country'];
            }
            
            return $address->update();
            
        } catch (Exception $e) {
            return false;
        }
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
     * VERSION AMÉLIORÉE : Anti-doublons + statistiques pour le cron
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

        // Réinitialisation des statistiques
        $clients_processed = 0;
        $clients_created = 0;
        $clients_updated = 0;
        $clients_errors = 0;

        if ($allow_customerimport == 1) {
            try {
                $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                $art = $sc->getNewClients($softwareid, $timestamp);
                
                if (empty($art->client)) {
                    $clientsrezo = [];
                } elseif (is_array($art->client)) {
                    $clientsrezo = $art->client;
                } else {
                    $clientsrezo = [$art->client];
                }

                foreach ($clientsrezo as $item) {
                    $clients_processed++;
                    $default_language_id = (int) Configuration::get('PS_LANG_DEFAULT');
                    $customerlist = (array) $item;

                    // Validation email première étape (anti-doublons)
                    $email = trim($customerlist['mail']);
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $output .= parent::l('Invalid email') . ' : ' . $email . "\n";
                        $clients_errors++;
                        continue;
                    }

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
                    $phone = (empty($customerlist['tel'])) ? '0000000000' : $customerlist['tel'];
                    $ann = (empty($customerlist['ann'])) ? '1970-01-01' : $customerlist['ann'];
                    $api_customerid = $customerlist['num'];
                    $newsletter = $customerlist['acceptCommCommerciale'];

                    // STRATÉGIE ANTI-DOUBLONS : Recherche par email d'abord
                    $existing_customer_id = self::getCustomerIdByEmail($email);
                    
                    if ($existing_customer_id) {
                        // Client existant trouvé par email - Mise à jour
                        $customer_id = $existing_customer_id;
                        
                        // Mettre à jour le client
                        $update_success = self::updateExistingCustomer($customer_id, [
                            'firstname' => $firstname,
                            'lastname' => $lastname,
                            'birthday' => $ann,
                            'gender' => $gender,
                            'newsletter' => $newsletter
                        ]);
                        
                        if ($update_success) {
                            $clients_updated++;
                            $output .= 'Customer updated: ' . $email . ' (ID: ' . $customer_id . ')' . "\n";
                            
                            // Mettre à jour l'adresse
                            self::updateCustomerAddress($customer_id, [
                                'firstname' => $firstname,
                                'lastname' => $lastname,
                                'address1' => $customerlist['add1'],
                                'address2' => $customerlist['add2'],
                                'postcode' => $customerlist['cp'],
                                'city' => $customerlist['ville'],
                                'phone' => $phone,
                                'id_country' => Country::getByIso(empty($customerlist['codePays']) ?
                                    Configuration::get('PS_LOCALE_COUNTRY') : $customerlist['codePays'])
                            ]);
                        } else {
                            $clients_errors++;
                            $output .= 'Error updating customer: ' . $email . "\n";
                        }
                        
                        // Créer/mettre à jour la liaison API
                        $array_data = [
                            'system_customerid' => (int) $customer_id,
                            'api_customerid' => (int) $api_customerid,
                        ];
                        Db::getInstance()->insert('pfi_customer_apisync', $array_data, false, false, Db::ON_DUPLICATE_KEY, true);
                        
                    } else {
                        // Pas de client existant par email - Vérification par API (fallback)
                        $sync_customer_id = Db::getInstance()->getValue(
                            'SELECT system_customerid FROM ' . _DB_PREFIX_ .
                                'pfi_customer_apisync WHERE api_customerid = ' . (int) $api_customerid .
                                ' ORDER BY system_customerid DESC'
                        );
                        
                        if ($sync_customer_id && Customer::customerIdExistsStatic($sync_customer_id)) {
                            // Client trouvé par sync mais pas par email (email a changé ?)
                            $customer_id = $sync_customer_id;
                            
                            $update_success = self::updateExistingCustomer($customer_id, [
                                'firstname' => $firstname,
                                'lastname' => $lastname,
                                'birthday' => $ann,
                                'gender' => $gender,
                                'newsletter' => $newsletter
                            ]);
                            
                            if ($update_success) {
                                $clients_updated++;
                                $output .= 'Customer updated via sync: ' . $email . ' (ID: ' . $customer_id . ')' . "\n";
                            } else {
                                $clients_errors++;
                                $output .= 'Error updating customer via sync: ' . $email . "\n";
                            }
                            
                        } else {
                            // DOUBLE-CHECK avant création (sécurité anti-doublons)
                            $double_check = self::getCustomerIdByEmail($email);
                            
                            if ($double_check) {
                                // Un autre processus a créé le client pendant notre traitement
                                $customer_id = $double_check;
                                $clients_updated++;
                                $output .= 'Customer found during double-check: ' . $email . ' (ID: ' . $customer_id . ')' . "\n";
                            } else {
                                // Création d'un nouveau client
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
                                    $customer_id = $customer->id;
                                    $clients_created++;
                                    $output .= 'Customer created: ' . $email . ' (ID: ' . $customer_id . ')' . "\n";
                                    
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
                                    } catch (Exception $e) {
                                        $output .= 'Address creation error: ' . $e->getMessage() . "\n";
                                    }
                                    
                                } catch (Exception $e) {
                                    $clients_errors++;
                                    $output .= 'Customer creation error: ' . $e->getMessage() . "\n";
                                    continue;
                                }
                            }
                            
                            // Créer la liaison API
                            $array_data = [
                                'system_customerid' => (int) $customer_id,
                                'api_customerid' => (int) $api_customerid,
                            ];
                            Db::getInstance()->insert('pfi_customer_apisync', $array_data, false, false, Db::ON_DUPLICATE_KEY, true);
                        }
                    }
                }

            } catch (SoapFault $exception) {
                $output .= Vccsv::logError($exception);
                $clients_errors++;
            }
        } else {
            $output .= 'Customer import not allowed' . "\n";
        }

        // Sauvegarder les statistiques pour le cron
        self::$last_import_stats = [
            'processed' => $clients_processed,
            'created' => $clients_created,
            'updated' => $clients_updated,
            'errors' => $clients_errors
        ];

        return $output;
    }
}