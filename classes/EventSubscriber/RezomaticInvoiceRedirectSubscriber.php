<?php

namespace PfProductImporter\EventSubscriber;

// Import conditionnel selon version Symfony
if (class_exists('Symfony\Component\HttpKernel\Event\RequestEvent')) {
    // PS 8+ (Symfony 4.4+)
    class_alias('Symfony\Component\HttpKernel\Event\RequestEvent', 'KernelEventAlias');
} else {
    // PS 1.7.8 (Symfony 3.4)
    class_alias('Symfony\Component\HttpKernel\Event\GetResponseEvent', 'KernelEventAlias');
}
use KernelEventAlias as KernelEvent;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class RezomaticInvoiceRedirectSubscriber implements EventSubscriberInterface
{
    /** @var \Doctrine\DBAL\Connection */
    private $conn;
    /** @var \PrestaShop\PrestaShop\Adapter\Configuration */
    private $config;

    public function __construct($conn, $config)
    {
        $this->conn = $conn;
        $this->config = $config;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(KernelEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo(); // ex: /adminXXX/index.php/sell/orders/12/generate-invoice-pdf

        /**
         * (A) LEGACY BO : AdminPdf -> generateInvoicePDF
         * URL type:
         *   /adminXXX/index.php?controller=AdminPdf&submitAction=generateInvoicePDF&id_order_invoice=7
         * Ou parfois: id_order_invoice[] = [7, ...]
         * On ouvre FO pdf-invoice en _blank via une mini page HTML, puis on revient sur la page BO.
         */
        $controller   = (string)$request->query->get('controller', '');
        $submitAction = (string)$request->query->get('submitAction', '');
        if (strcasecmp($controller, 'AdminPdf') === 0 && strcasecmp($submitAction, 'generateInvoicePDF') === 0) {

            // 1) Récup id_order
            $orderId = (int)$request->query->get('id_order', 0);
            if ($orderId <= 0) {
                // Peut être un scalaire ou un tableau
                $idInv = $request->query->get('id_order_invoice', 0);
                if (is_array($idInv)) {
                    $idInv = (int)reset($idInv);
                } else {
                    $idInv = (int)$idInv;
                }
                if ($idInv > 0) {
                    $orderId = (int)$this->conn->fetchColumn(
                        'SELECT id_order FROM ' . _DB_PREFIX_ . 'order_invoice WHERE id_order_invoice = ?',
                        [$idInv]
                    );
                }
            }

            if ($orderId > 0) {
                // 2) Construire l’URL FO (ton FO gère déjà la redirection Rezomatic)
                $foUrl = \Tools::getShopDomainSsl(true) . __PS_BASE_URI__
                    . 'index.php?controller=pdf-invoice&id_order=' . (int)$orderId;

                // 3) Calculer la page de retour (référent ou vue commande)
                $host    = $request->getSchemeAndHttpHost();
                $baseUrl = rtrim($request->getBaseUrl(), '/');
                $back    = $request->headers->get('referer') ?: ($host . $baseUrl . '/sell/orders/' . (int)$orderId . '/view');

                // 4) Mini page qui ouvre en _blank et revient
                $invoice = htmlspecialchars($foUrl, ENT_QUOTES, 'UTF-8');
                $backUrl = htmlspecialchars($back,  ENT_QUOTES, 'UTF-8');

                $html = '<!doctype html><html><head><meta charset="utf-8"><title>Ouverture de la facture…</title></head><body>'
                    . '<a id="rz" href="' . $invoice . '" target="_blank" rel="noopener">Ouverture de la facture…</a>'
                    . '<script>try{document.getElementById("rz").click();}catch(e){}'
                    . 'setTimeout(function(){location.replace("' . $backUrl . '");},200);</script>'
                    . '<noscript><p><a href="' . $invoice . '" target="_blank" rel="noopener">Ouvrir la facture</a></p>'
                    . '<p>Ensuite revenez sur la page <a href="' . $backUrl . '">commande</a>.</p></noscript>'
                    . '</body></html>';

                $event->setResponse(new Response($html));
                return;
            }

            // Pas d'id => laisser Presta gérer son PDF natif
            return;
        }

        /**
         * (B) ROUTE SYMFONY : /sell/orders/{id}/generate-invoice-pdf
         * Tu avais déjà ce bloc : on garde EXACTEMENT le même principe (mini page _blank)
         * sauf qu’ici tu as déjà le SOAP + l’URL Rezomatic => on ouvre directement l’URL Rezomatic en _blank
         */
        if (!preg_match('#/sell/orders/(\d+)/generate-invoice-pdf#', $path, $m)) {
            return;
        }

        // Récup id commande (attribut ou regex)
        $orderId = (int)($request->attributes->get('orderId') ?? 0);
        if ($orderId <= 0) {
            $orderId = (int)$m[1];
        }
        if ($orderId <= 0) {
            return;
        }

        try {
            // 1) lier PS->Rezomatic
            $rezomaticId = $this->conn->fetchColumn(
                'SELECT api_orderid FROM ' . _DB_PREFIX_ . 'pfi_order_apisync WHERE system_orderid = ?',
                [$orderId]
            );
            if (!$rezomaticId) {
                return;
            }

            // 2) config
            $feedurl    = (string)$this->config->get('SYNC_CSV_FEEDURL');
            $softwareId = (string)$this->config->get('PI_SOFTWAREID');
            if (!$feedurl || !$softwareId) {
                return;
            }

            // 3) SOAP
            $sc = new \SoapClient($feedurl, [
                'connection_timeout' => 10,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'stream_context' => stream_context_create(['http' => ['timeout' => 15]]),
            ]);
            $result = $sc->getCommandesStatuts($softwareId, (int)$rezomaticId);

            // 4) normaliser ->commandeState
            $states = [];
            if (is_object($result) && isset($result->commandeState)) {
                $states = is_array($result->commandeState) ? $result->commandeState : [$result->commandeState];
            } elseif (is_array($result)) {
                $states = $result;
            }
            if (!$states) {
                return;
            }

            // 5) trier par date (desc) et rediriger si statut=2 + urlInvoice
            usort($states, function ($a, $b) {
                $ta = isset($a->timestamp) ? strtotime($a->timestamp) : 0;
                $tb = isset($b->timestamp) ? strtotime($b->timestamp) : 0;
                return $tb <=> $ta;
            });

            foreach ($states as $st) {
                $s  = isset($st->statut) ? (int)$st->statut : 0;
                $ui = isset($st->urlInvoice) ? (string)$st->urlInvoice : '';
                if ($s === 2 && $ui !== '') {
                    // ouvrir dans un nouvel onglet + revenir sur la page commande
                    $host    = $request->getSchemeAndHttpHost();
                    $baseUrl = rtrim($request->getBaseUrl(), '/');
                    $back    = $request->headers->get('referer') ?: ($host . $baseUrl . '/sell/orders/' . $orderId . '/view');

                    $invoice = htmlspecialchars($ui, ENT_QUOTES, 'UTF-8');
                    $backUrl = htmlspecialchars($back,  ENT_QUOTES, 'UTF-8');

                    $html = '<!doctype html><html><head><meta charset="utf-8"><title>Ouverture de la facture…</title></head><body>'
                        . '<a id="rz" href="' . $invoice . '" target="_blank" rel="noopener">Ouverture de la facture…</a>'
                        . '<script>try{document.getElementById("rz").click();}catch(e){}'
                        . 'setTimeout(function(){location.replace("' . $backUrl . '");},200);</script>'
                        . '<noscript><p><a href="' . $invoice . '" target="_blank" rel="noopener">Ouvrir la facture</a></p>'
                        . '<p>Ensuite revenez sur la page <a href="' . $backUrl . '">commande</a>.</p></noscript>'
                        . '</body></html>';

                    $event->setResponse(new Response($html));
                    return;
                }
            }
            // sinon fallback vers le PDF natif (message “facture non disponible”)
        } catch (\Throwable $e) {
            // on laisse Presta continuer en cas d'erreur (aucun fatal)
            return;
        }
    }
}
