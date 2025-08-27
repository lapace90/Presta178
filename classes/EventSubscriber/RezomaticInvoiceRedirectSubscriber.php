<?php

namespace PfProductImporter\EventSubscriber;

use Symfony\Component\HttpKernel\Event\GetResponseEvent; // PS 1.7.8 (Symfony 3.4)
use Symfony\Component\HttpFoundation\Response;
// PS 8.x, pense à migrer GetResponseEvent/FilterResponseEvent → RequestEvent/ResponseEvent
// use Symfony\Component\HttpKernel\Event\RequestEvent; // PS 1.7.9+ (Symfony 4.4)

class RezomaticInvoiceRedirectSubscriber
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

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo(); // ex: /adminXXX/index.php/sell/orders/12/generate-invoice-pdf

        // Cibler la route BO de génération de facture
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
                    $request = $event->getRequest();
                    $host    = $request->getSchemeAndHttpHost();
                    $baseUrl = rtrim($request->getBaseUrl(), '/');
                    $back    = $request->headers->get('referer') ?: ($host . $baseUrl . '/sell/orders/' . $orderId);

                    // mini page qui ouvre la facture en _blank puis revient sur la page précédente
                    $invoice = htmlspecialchars($ui, ENT_QUOTES, 'UTF-8');
                    $backUrl = htmlspecialchars($back, ENT_QUOTES, 'UTF-8');

                    $html = '<!doctype html><html><head><meta charset="utf-8"><title>Ouverture de la facture…</title></head><body>'
                        . '<a id="rz" href="' . $invoice . '" target="_blank" rel="noopener">Ouverture de la facture...</a>'
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
