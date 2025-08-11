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
 * Classe de synchronisation des clients entre PrestaShop et Rezomatic via SOAP
 * 
 * Cette classe gère :
 * - La synchronisation bidirectionnelle des données clients PrestaShop ↔ Rezomatic
 * - L'import en masse depuis Rezomatic avec gestion anti-doublons
 * - La synchronisation des programmes de fidélité Rezomatic
 * - La création automatique de bons de réduction PrestaShop depuis Rezomatic
 * 
 * @edit Definima
 * @version 2.0 - Version améliorée avec anti-doublons et statistiques
 * @since PrestaShop 1.6+
 */
class CustomerVccsv extends Vccsv
{

    /**
     * Statistiques du dernier import pour le monitoring des tâches cron
     * 
     * Permet de suivre les performances et détecter les problèmes d'import
     * 
     * @var array Contient : processed, created, updated, errors
     * @static
     */
    public static $last_import_stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0];

    /**
     * Synchronise un client PrestaShop vers Rezomatic via SOAP
     *
     * Cette méthode exporte les données d'un client PrestaShop vers le logiciel de gestion Rezomatic.
     * Elle gère la création ou mise à jour selon l'existence du client dans Rezomatic.
     *
     * Processus :
     * 1. Vérification des permissions d'export
     * 2. Récupération et nettoyage des données client PrestaShop
     * 3. Recherche du client dans Rezomatic par email
     * 4. Mise à jour ou création dans Rezomatic selon le cas
     * 5. Sauvegarde de la liaison ID PrestaShop/ID Rezomatic
     *
     * @static
     * @param int  $id_customer  ID du client PrestaShop à synchroniser
     * @param bool $forceExport  Force l'export même si désactivé en config (défaut: false)
     * @return string Log des opérations effectuées (pour debug/monitoring)
     */
    public static function customerSync($id_customer, $forceExport = false)
    {
        // Vérification des permissions d'export
        $allow_customerexport = Configuration::get('PI_ALLOW_CUSTOMEREXPORT');
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $output = '';
        if ($forceExport || ($allow_customerexport == 1)) {
            $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
            try {
                // Initialisation du client SOAP
                $sc = new SoapClient($feedurl);
                
                // Récupération et nettoyage des données client PrestaShop
                $customer = new Customer($id_customer, ['keep_alive' => false]);
                $name = $customer->lastname . ' ' . $customer->firstname;
                // Nettoyage des caractères spéciaux pour éviter les erreurs SOAP
                $name = preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&#\,@_-]/i', ' ', $name);
                $name = trim($name);
                
                // Récupération de l'adresse principale du client
                $id_address = Address::getFirstCustomerAddressId($id_customer);
                $address = new Address($id_address);
                
                // Gestion de la civilité (conversion ID vers texte)
                $id_gender = $customer->id_gender;
                $mr = '';
                if ($id_gender == 1) {
                    $mr = 'Mr';
                } elseif ($id_gender == 2) {
                    $mr = 'Mme';
                } else {
                    $mr = 'Mr'; // Valeur par défaut
                }

                // Préparation et nettoyage des données d'adresse
                $addresse = (empty($address->address1) ? '' : $address->address1);
                $addresse = Tools::substr(preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&#\,@_-]/i', ' ', $addresse), 0, 49);
                $postcode = $address->postcode;
                $city = (empty($address->city) ? '' : $address->city);
                $city = Tools::substr(preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&#\,@_-]/i', ' ', $city), 0, 49);
                $phone = $address->phone;
                
                // Gestion spéciale des emails eBay (remplacés par des emails génériques)
                $email = $customer->email;
                if ($email == 'NOSEND-EBAY') {
                    $email = 'emailebay' . $id_customer . '@remplacementebay.com';
                }
                
                $birthday = $customer->birthday;
                $country = Country::getIsoById($address->id_country);
                
                // Préparation des données entreprise (B2B)
                $raisonSociale = Tools::replaceAccentedChars(empty($customer->company) ? '' : $customer->company);
                $raisonSociale = preg_replace('/[^0-9A-Za-z :\.\(\)\?!\+&#\,@_-]/i', ' ', $raisonSociale);
                $siret = (empty($customer->siret) ? '' : $customer->siret);
                $ape = (empty($customer->ape) ? '' : $customer->ape);
                $numtva = (empty($address->vat_number) ? '' : $address->vat_number);
                $newsletter = $customer->newsletter;
                
                // Recherche du num client Rezomatic dans la base Prestashop
                // Méthode commentée : recherche en base locale (ancienne approche)
                /*$api_customerid = Db::getInstance()->getValue('select api_customerid from ' . _DB_PREFIX_ .
                    'pfi_customer_apisync where system_customerid= ' . (int) $id_customer);*/
                $api_customerid = false;
                
                // Recherche du client par email dans Rezomatic
                if (!$api_customerid) {
                    $clients = $sc->getClientsFromEMail($softwareid, $email);
                    if (!isset($clients->client)) {
                        $api_customerid = false;
                    } elseif (is_array($clients->client)) {
                        // Plusieurs clients trouvés dans Rezomatic : prendre le premier
                        $client = current($clients->client);
                        $api_customerid = $client->num;
                    } else {
                        
                        $client = $clients->client;
                        $api_customerid = $client->num;
                    }
                }
                
                // MISE À JOUR : Si le client existe déjà dans Rezomatic
                if ($api_customerid) {
                    try {
                        $cli = $sc->updateClient(
                            $softwareid,
                            $api_customerid,
                            $mr,
                            $name,
                            $addresse,
                            NULL,         // Complément d'adresse 1
                            NULL,         // Complément d'adresse 2
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
                        $api_customerid = false; // Reset pour tenter une création
                    }
                }
                
                // CRÉATION : Si le client n'existe pas dans Rezomatic
                if (!$api_customerid) {
                    try {
                        // Récupération d'un nouveau numéro client Rezomatic
                        $api_customerid = $sc->getFreeCodeClient($softwareid);
                        $cli = $sc->createClient(
                            $softwareid,
                            $api_customerid,
                            $mr,
                            $name,
                            $addresse,
                            '',           // Complément d'adresse 1
                            '',           // Complément d'adresse 2
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
                            true,       // Fidelite activée par défaut
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
                
                // Sauvegarde de la liaison ID PrestaShop <-> ID Rezomatic
                if ($api_customerid) {
                    $array_data = [
                        'system_customerid' => (int) $id_customer,
                        'api_customerid' => (int) $api_customerid,
                    ];
                    // Insertion avec gestion des doublons (ON DUPLICATE KEY)
                    Db::getInstance()->insert('pfi_customer_apisync', $array_data, false, false, Db::ON_DUPLICATE_KEY, true);
                }
            } catch (SoapFault $exception) {
                $output .= Vccsv::logError($exception);
            }
        }

        return $output;
    }

    /**
     * Recherche fiable d'un client par email (stratégie anti-doublons)
     * 
     * Utilise la méthode native PrestaShop pour éviter les incohérences
     * et garantir l'unicité des emails dans la base
     * 
     * @param string $email Email à rechercher
     * @return int|false ID du client ou false si non trouvé
     * @static
     * @private
     */
    private static function getCustomerIdByEmail($email)
    {
        // Validation préalable de l'email
        if (!Validate::isEmail($email)) {
            return false;
        }
        
        // Utilisation de la méthode native PrestaShop (plus fiable que les requêtes SQL custom)
        $customer_id = Customer::customerExists($email, true, false);
        
        return $customer_id ? (int)$customer_id : false;
    }

    /**
     * Met à jour un client existant avec validation des données
     * 
     * Applique des règles de validation avant mise à jour pour éviter
     * la corruption des données (ex: prénom/nom numériques)
     * 
     * @param int   $customer_id   ID du client à mettre à jour
     * @param array $customerData  Données à mettre à jour
     * @return bool Succès de la mise à jour
     * @static
     * @private
     */
    private static function updateExistingCustomer($customer_id, $customerData)
    {
        try {
            $customer = new Customer($customer_id);
            
            // Mise à jour seulement si les nouvelles valeurs sont valides
            // Évite l'écrasement par des données corrompues
            if (!empty($customerData['firstname']) && !is_numeric($customerData['firstname'])) {
                $customer->firstname = $customerData['firstname'];
            }
            if (!empty($customerData['lastname']) && !is_numeric($customerData['lastname'])) {
                $customer->lastname = $customerData['lastname'];
            }
            // Évite les dates par défaut Unix (01/01/1970)
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
     * Met à jour l'adresse principale d'un client avec validation
     * 
     * Localise et met à jour la première adresse du client en appliquant
     * des règles de validation similaires aux données client
     * 
     * @param int   $customer_id  ID du client
     * @param array $addressData  Données d'adresse à mettre à jour
     * @return bool Succès de la mise à jour
     * @static
     * @private
     */
    private static function updateCustomerAddress($customer_id, $addressData)
    {
        try {
            // Récupération de l'adresse principale du client
            $id_address = Address::getFirstCustomerAddressId($customer_id);
            if (!$id_address) {
                return false; // Pas d'adresse trouvée
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
                // Suppression des espaces dans les numéros de téléphone
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
     * Enregistre un bon de réduction provenant du système de fidélité externe
     *
     * Crée une règle de panier (CartRule) PrestaShop à partir des données
     * de fidélité du système externe. Le bon est valable 1 an.
     *
     * @static
     * @param int    $id_customer ID du client bénéficiaire
     * @param float  $amount      Montant de la réduction
     * @param string $name        Code/nom du bon de réduction
     * @param int    $id_currency ID de la devise (défaut: 0 = devise par défaut)
     * @return bool Succès de la création du bon
     */
    public static function registerDiscountRezomatic($id_customer, $amount, $name, $id_currency = 0)
    {
        $cartRule = new CartRule();
        $cartRule->reduction_amount = (float) $amount;
        $cartRule->reduction_tax = 1; // Réduction TTC
        $cartRule->quantity = 1; // Un seul bon disponible
        $cartRule->quantity_per_user = 1; // Une utilisation par utilisateur
        $cartRule->date_from = date('Y-m-d H:i:s', time());
        $cartRule->date_to = date('Y-m-d H:i:s', time() + 31536000); // Valable 1 an
        $cartRule->code = $name;
        
        // Nom du bon dans toutes les langues disponibles
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
     * Synchronise les points de fidélité et bons de réduction d'un client depuis Rezomatic
     *
     * Récupère depuis Rezomatic tous les bons de réduction disponibles
     * pour un client et les importe dans PrestaShop. Gère également la 
     * désactivation des bons utilisés ou annulés.
     *
     * @static
     * @param int    $id_customer    ID du client PrestaShop
     * @param string $email_customer Email pour la recherche dans Rezomatic
     * @return string Log des opérations effectuées
     */
    public static function loyaltySync($id_customer, $email_customer)
    {
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $output = '';
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        try {
            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            
            // Recherche du client dans Rezomatic par email
            $clients = $sc->getClientsFromEMail($softwareid, $email_customer);
            if (isset($clients->client) && is_object($clients->client)) {
                // Récupération des bons de fidélité du client depuis Rezomatic
                $rm = $sc->getBonsFromClientNum($softwareid, $clients->client->num);
                if (isset($rm->bons)) {
                    // Normalisation : transformation en tableau si un seul bon
                    if (is_array($rm->bons)) {
                        $bons = $rm->bons;
                    } else {
                        $bons = [$rm->bons];
                    }
                    
                    foreach ($bons as $bon) {
                        // Vérification de l'existence du bon dans PrestaShop
                        if (!CartRule::cartRuleExists($bon->codeBon)) {
                            // Création du bon seulement s'il est actif (non validé et non annulé)
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
                            // Désactivation du bon s'il a été utilisé ou annulé dans Rezomatic
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
     * Import en masse des nouveaux clients depuis Rezomatic vers PrestaShop
     * 
     * VERSION AMÉLIORÉE avec stratégie anti-doublons multicouches et statistiques détaillées
     * 
     * Processus d'import sécurisé :
     * 1. Récupération des nouveaux clients depuis Rezomatic (dernière synchronisation)
     * 2. Pour chaque client : recherche anti-doublons par email dans PrestaShop (priorité)
     * 3. Fallback : recherche par liaison API existante PrestaShop ↔ Rezomatic
     * 4. Double-check avant création (sécurité concurrence)
     * 5. Création client + adresse PrestaShop avec validation des données
     * 6. Statistiques complètes pour monitoring
     *
     * Stratégie anti-doublons :
     * - Recherche principale par email dans PrestaShop (méthode native PrestaShop)
     * - Recherche secondaire par table de liaison API PrestaShop ↔ Rezomatic
     * - Validation finale avant création
     * - Gestion des emails invalides/vides
     * 
     * @static
     * @return string Log détaillé des opérations + statistiques
     */
    public static function importCustomer()
    {
        // Configuration et initialisation
        $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
        $allow_customerimport = Configuration::get('PI_ALLOW_CUSTOMERIMPORT');
        $customer_id = '';
        $output = '';
        $softwareid = Configuration::get('PI_SOFTWAREID');
        $timestamp = Configuration::get('PI_LAST_CRON'); // Timestamp de la dernière synchronisation

        // Réinitialisation des compteurs statistiques pour le monitoring
        $clients_processed = 0;
        $clients_created = 0;
        $clients_updated = 0;
        $clients_errors = 0;

        if ($allow_customerimport == 1) {
            try {
                $sc = new SoapClient($feedurl, ['keep_alive' => false]);
                
                // Récupération des nouveaux clients depuis Rezomatic (dernière synchronisation)
                $art = $sc->getNewClients($softwareid, $timestamp);
                
                // Normalisation de la réponse SOAP de Rezomatic (gestion array/object)
                if (empty($art->client)) {
                    $clientsrezo = [];
                } elseif (is_array($art->client)) {
                    $clientsrezo = $art->client;
                } else {
                    $clientsrezo = [$art->client]; // Un seul client -> transformation en array
                }

                // Traitement de chaque client récupéré depuis Rezomatic
                foreach ($clientsrezo as $item) {
                    $clients_processed++;
                    $default_language_id = (int) Configuration::get('PS_LANG_DEFAULT');
                    $customerlist = (array) $item; // Conversion object -> array

                    // === VALIDATION EMAIL (première barrière anti-doublons) ===
                    $email = trim($customerlist['mail']);
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $output .= parent::l('Invalid email') . ' : ' . $email . "\n";
                        $clients_errors++;
                        continue; // Passage au client suivant
                    }

                    // === TRAITEMENT DU GENRE ===
                    // Conversion des civilités texte vers ID PrestaShop
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
                            $gender = 2; // Femme
                            break;
                        default:
                            $gender = 1; // Homme (valeur par défaut)
                            break;
                    }

                    // === TRAITEMENT NOM/PRÉNOM ===
                    // Parsing du champ "noPrn"
                    $arrcust = explode(' ', $customerlist['noPrn']);
                    $lastname = preg_replace('/[^A-Za-z ]/i', ' ', array_shift($arrcust));
                    $firstname = preg_replace('/[^A-Za-z ]/i', ' ', implode(' ', $arrcust));
                    
                    // Valeurs par défaut pour les champs obligatoires
                    $phone = (empty($customerlist['tel'])) ? '0000000000' : $customerlist['tel'];
                    $ann = (empty($customerlist['ann'])) ? '1970-01-01' : $customerlist['ann'];
                    $api_customerid = $customerlist['num']; // ID dans Rezomatic
                    $newsletter = $customerlist['acceptCommCommerciale'];

                    // === STRATÉGIE ANTI-DOUBLONS MULTICOUCHES ===
                    
                    // COUCHE 1 : Recherche par email d'abord (méthode principale)
                    $existing_customer_id = self::getCustomerIdByEmail($email);
                    
                    if ($existing_customer_id) {
                        // === CLIENT EXISTANT TROUVÉ PAR EMAIL ===
                        $customer_id = $existing_customer_id;
                        
                        // Mise à jour des données client avec validation
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
                            
                            // Mise à jour de l'adresse associée
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
                        
                        // Création/mise à jour de la liaison API
                        $array_data = [
                            'system_customerid' => (int) $customer_id,
                            'api_customerid' => (int) $api_customerid,
                        ];
                        Db::getInstance()->insert('pfi_customer_apisync', $array_data, false, false, Db::ON_DUPLICATE_KEY, true);
                        
                    } else {
                        // === COUCHE 2 : Recherche par liaison API (fallback) ===
                        // Cas où l'email a changé mais le client existe via l'API PrestaShop ↔ Rezomatic
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
                            // === COUCHE 3 : Double-check anti-concurrence ===
                            // Vérification finale avant création (sécurité multi-processus)
                            $double_check = self::getCustomerIdByEmail($email);
                            
                            if ($double_check) {
                                // Un autre processus a créé le client pendant notre traitement
                                $customer_id = $double_check;
                                $clients_updated++;
                                $output .= 'Customer found during double-check: ' . $email . ' (ID: ' . $customer_id . ')' . "\n";
                            } else {
                                // === CRÉATION D'UN NOUVEAU CLIENT ===
                                $customer = new Customer();
                                // Protection contre les noms numériques (données corrompues)
                                $customer->firstname = (is_numeric($firstname) ? '-' : $firstname);
                                $customer->lastname = (is_numeric($lastname) ? '-' : $lastname);
                                $customer->passwd = '123456789'; // Mot de passe temporaire
                                $customer->passwd = Tools::hash($customer->passwd); // Hashage sécurisé
                                $customer->email = $email;
                                $customer->birthday = $ann;
                                $customer->active = 1; // Client actif
                                $customer->id_shop = 1; // Boutique par défaut
                                $customer->id_shop_group = 1; // Groupe de boutiques par défaut
                                $customer->id_gender = $gender;
                                $customer->id_lang = $default_language_id;
                                $customer->newsletter = $newsletter;
                                
                                try {
                                    $customer->add();
                                    $customer_id = $customer->id;
                                    $clients_created++;
                                    $output .= 'Customer created: ' . $email . ' (ID: ' . $customer_id . ')' . "\n";
                                    
                                    // === CRÉATION DE L'ADRESSE ASSOCIÉE ===
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
                                    $address->phone = str_replace(' ', '', $phone); // Suppression des espaces
                                    
                                    try {
                                        $address->add();
                                    } catch (Exception $e) {
                                        $output .= 'Address creation error: ' . $e->getMessage() . "\n";
                                    }
                                    
                                } catch (Exception $e) {
                                    $clients_errors++;
                                    $output .= 'Customer creation error: ' . $e->getMessage() . "\n";
                                    continue; // Passage au client suivant
                                }
                            }
                            
                            // === SAUVEGARDE DE LA LIAISON API PRESTASHOP ↔ REZOMATIC ===
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

        // === SAUVEGARDE DES STATISTIQUES POUR LE MONITORING ===
        self::$last_import_stats = [
            'processed' => $clients_processed,
            'created' => $clients_created,
            'updated' => $clients_updated,
            'errors' => $clients_errors
        ];

        return $output;
    }
}