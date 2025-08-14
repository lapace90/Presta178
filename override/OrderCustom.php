<?php
class OrderCustom extends OrderCore
{
    /**
     * Override to fetch invoices from Rezomatic webservice instead of PrestaShop
     * 
     * This method integrates with your existing Rezomatic SOAP infrastructure
     * following the same patterns used in CustomerVccsv and OrderVccsv classes.
     */
    public function getInvoicesCollection()
    {
            die("La méthode getInvoicesCollection est appelée !"); // Arrête l'exécution ici
        $allow_rezomatic_invoices = true; // Intégration Rezomatic forcée

        try {
            $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
            $softwareid = Configuration::get('PI_SOFTWAREID');

            PrestaShopLogger::addLog("OrderCustom::getInvoicesCollection - feedurl: " . $feedurl, 1);
            PrestaShopLogger::addLog("OrderCustom::getInvoicesCollection - softwareid: " . $softwareid, 1);

            if (!$feedurl || !$softwareid) {
                PrestaShopLogger::addLog("OrderCustom::getInvoicesCollection - Configuration manquante", 3);
                return parent::getInvoicesCollection();
            }

            $api_orderid = Db::getInstance()->getValue(
                'SELECT api_orderid FROM ' . _DB_PREFIX_ . 'pfi_order_apisync WHERE system_orderid = ' . (int) $this->id
            );

            PrestaShopLogger::addLog("OrderCustom::getInvoicesCollection - api_orderid: " . $api_orderid, 1);

            if (!$api_orderid) {
                PrestaShopLogger::addLog("OrderCustom::getInvoicesCollection - api_orderid non trouvé pour la commande " . $this->id, 3);
                return parent::getInvoicesCollection();
            }

            $sc = new SoapClient($feedurl, ['keep_alive' => false]);
            $rezomatic_invoices = $sc->getInvoicesForOrder($softwareid, $api_orderid);

            PrestaShopLogger::addLog("OrderCustom::getInvoicesCollection - Réponse Rezomatic: " . print_r($rezomatic_invoices, true), 1);

            return $this->transformRezomaticInvoicesToPrestaShop($rezomatic_invoices);
        } catch (SoapFault $exception) {
            PrestaShopLogger::addLog("OrderCustom::getInvoicesCollection - Erreur SOAP: " . $exception->getMessage(), 3);
            return parent::getInvoicesCollection();
        } catch (Exception $e) {
            PrestaShopLogger::addLog("OrderCustom::getInvoicesCollection - Erreur générale: " . $e->getMessage(), 3);
            return parent::getInvoicesCollection();
        }
    }


    /**
     * Transform Rezomatic invoice data to PrestaShop invoice collection format
     * 
     * @param mixed $rezomatic_invoices Response from Rezomatic SOAP service
     * @return PrestaShopCollection|array Collection of invoices in PrestaShop format
     */
    private function transformRezomaticInvoicesToPrestaShop($rezomatic_invoices)
    {
        PrestaShopLogger::addLog("transformRezomaticInvoicesToPrestaShop - Données brutes: " . print_r($rezomatic_invoices, true), 1);

        $invoices_collection = new PrestaShopCollection('OrderInvoice');

        if (empty($rezomatic_invoices)) {
            PrestaShopLogger::addLog("transformRezomaticInvoicesToPrestaShop - Aucune facture trouvée", 1);
            return $invoices_collection;
        }

        // Normalise la réponse
        if (isset($rezomatic_invoices->invoice)) {
            if (is_array($rezomatic_invoices->invoice)) {
                $invoices_data = $rezomatic_invoices->invoice;
            } else {
                $invoices_data = [$rezomatic_invoices->invoice]; // Cas d'une seule facture
            }
        } else {
            // Si la structure est différente, par exemple un tableau direct
            $invoices_data = is_array($rezomatic_invoices) ? $rezomatic_invoices : [$rezomatic_invoices];
        }

        PrestaShopLogger::addLog("transformRezomaticInvoicesToPrestaShop - Données normalisées: " . print_r($invoices_data, true), 1);

        foreach ($invoices_data as $rezomatic_invoice) {
            $invoice_data = (array) $rezomatic_invoice;
            PrestaShopLogger::addLog("transformRezomaticInvoicesToPrestaShop - Facture en cours: " . print_r($invoice_data, true), 1);

            $order_invoice = new OrderInvoice();
            $order_invoice->id_order = $this->id;
            $order_invoice->number = isset($invoice_data['numero']) ? $invoice_data['numero'] : '';
            $order_invoice->delivery_number = isset($invoice_data['numero_livraison']) ? $invoice_data['numero_livraison'] : '';
            $order_invoice->delivery_date = isset($invoice_data['date_livraison']) ? $invoice_data['date_livraison'] : '0000-00-00 00:00:00';
            $order_invoice->total_paid_tax_excl = isset($invoice_data['total_ht']) ? (float) $invoice_data['total_ht'] : 0;
            $order_invoice->total_paid_tax_incl = isset($invoice_data['total_ttc']) ? (float) $invoice_data['total_ttc'] : 0;
            $order_invoice->total_products = isset($invoice_data['total_produits']) ? (float) $invoice_data['total_produits'] : 0;
            $order_invoice->total_products_wt = isset($invoice_data['total_produits_ttc']) ? (float) $invoice_data['total_produits_ttc'] : 0;
            $order_invoice->total_shipping_tax_excl = isset($invoice_data['frais_port_ht']) ? (float) $invoice_data['frais_port_ht'] : 0;
            $order_invoice->total_shipping_tax_incl = isset($invoice_data['frais_port_ttc']) ? (float) $invoice_data['frais_port_ttc'] : 0;
            $order_invoice->shipping_tax_computation_method = (int) $this->getTaxComputationMethod();
            $order_invoice->total_wrapping_tax_excl = 0;
            $order_invoice->total_wrapping_tax_incl = 0;
            $order_invoice->shop_address = null;
            $order_invoice->invoice_address = null;
            $order_invoice->delivery_address = null;
            $order_invoice->note = isset($invoice_data['note']) ? $invoice_data['note'] : '';
            $order_invoice->date_add = isset($invoice_data['date_creation']) ? $invoice_data['date_creation'] : date('Y-m-d H:i:s');

            $invoices_collection[] = $order_invoice;
        }

        return $invoices_collection;
    }


    /**
     * Override getDocuments if you also want to fetch documents from Rezomatic
     */
    public function getDocuments()
    {
        // Check if Rezomatic document integration is enabled
        $allow_rezomatic_documents = Configuration::get('PI_ALLOW_REZOMATIC_DOCUMENTS');

        if (!$allow_rezomatic_documents) {
            return parent::getDocuments();
        }

        try {
            $feedurl = Configuration::get('SYNC_CSV_FEEDURL');
            $softwareid = Configuration::get('PI_SOFTWAREID');

            if (!$feedurl || !$softwareid) {
                return parent::getDocuments();
            }

            // Get the Rezomatic API order ID
            $api_orderid = Db::getInstance()->getValue(
                'SELECT api_orderid FROM ' . _DB_PREFIX_ . 'pfi_order_apisync 
                 WHERE system_orderid = ' . (int) $this->id
            );

            if (!$api_orderid) {
                return parent::getDocuments();
            }

            $sc = new SoapClient($feedurl, ['keep_alive' => false]);

            // Get documents from Rezomatic
            // Adjust method name based on Rezomatic API documentation
            $rezomatic_documents = $sc->getDocumentsForOrder($softwareid, $api_orderid);

            // Combine Rezomatic documents with default PrestaShop documents
            $default_documents = parent::getDocuments();
            $rezomatic_transformed = $this->transformRezomaticDocuments($rezomatic_documents);

            return array_merge($default_documents, $rezomatic_transformed);
        } catch (Exception $e) {
            // Log error and fall back to default
            PrestaShopLogger::addLog(
                'OrderCustom::getDocuments error: ' . $e->getMessage(),
                3,
                null,
                'Order',
                (int) $this->id
            );

            return parent::getDocuments();
        }
    }

    /**
     * Transform Rezomatic documents to PrestaShop format
     */
    private function transformRezomaticDocuments($rezomatic_documents)
    {
        if (empty($rezomatic_documents)) {
            return [];
        }

        $documents = [];

        // Handle document structure (adjust based on Rezomatic response format)
        $docs_data = isset($rezomatic_documents->document) ? $rezomatic_documents->document : $rezomatic_documents;
        if (!is_array($docs_data)) {
            $docs_data = [$docs_data];
        }

        foreach ($docs_data as $doc) {
            $doc_data = (array) $doc;

            // Create document array in PrestaShop format
            $documents[] = [
                'type' => isset($doc_data['type']) ? $doc_data['type'] : 'invoice',
                'filename' => isset($doc_data['nom_fichier']) ? $doc_data['nom_fichier'] : 'rezomatic_document.pdf',
                'name' => isset($doc_data['nom']) ? $doc_data['nom'] : 'Document Rezomatic',
                'url' => isset($doc_data['url']) ? $doc_data['url'] : '',
                'date_add' => isset($doc_data['date_creation']) ? $doc_data['date_creation'] : date('Y-m-d H:i:s'),
            ];
        }

        return $documents;
    }
}
