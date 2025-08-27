<?php
/**
 * Fichier : /modules/pfproductimporter/invoice_redirect.php
 * Intercepte les demandes de facture BO/FO et redirige vers la facture Rezomatic si dispo.
 */

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';

function rz_log($msg) {
    @file_put_contents(_PS_ROOT_DIR_.'/rezomatic_debug.log',
        '['.date('Y-m-d H:i:s')."] INV_REDIRECT ".$msg."\n", FILE_APPEND);
}

/** Détection robuste de l'id de commande depuis l'URI / les paramètres */
function resolve_order_id(): int {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $id  = 0;

    // BO Symfony (avec ou sans index.php) : /sell/orders/{id}/generate-invoice-pdf
    if (!$id && preg_match('#/(?:index\.php/)?sell/orders/(\d+)/(?:generate-invoice-pdf|generate-invoice)(?:\?.*)?$#i', $uri, $m)) {
        $id = (int)$m[1];
    }
    // Fallback : toute occurrence /orders/{id}/
    if (!$id && preg_match('#/orders/(\d+)(?:/|$)#i', $uri, $m)) {
        $id = (int)$m[1];
    }
    // FO legacy
    if (!$id && isset($_GET['id_order'])) {
        $id = (int)$_GET['id_order'];
    }
    // Si on nous passe une URL en param
    if (!$id && isset($_GET['url']) && preg_match('#/orders/(\d+)(?:/|$)#i', (string)$_GET['url'], $m)) {
        $id = (int)$m[1];
    }

    return $id;
}

/** Récupère l'URL de facture Rezomatic si statut=2 trouvé, sinon '' */
function get_rezomatic_invoice_url(int $orderId): string {
    if ($orderId <= 0) return '';

    // 1) trouver l'id rezomatic
    $rezomaticId = (int)Db::getInstance()->getValue(
        'SELECT api_orderid FROM '._DB_PREFIX_.'pfi_order_apisync WHERE system_orderid='.(int)$orderId
    );
    rz_log("order={$orderId} api_orderid=".($rezomaticId ?: 'AUCUN'));
    if (!$rezomaticId) return '';

    // 2) config
    $feedurl    = (string)Configuration::get('SYNC_CSV_FEEDURL');
    $softwareId = (string)Configuration::get('PI_SOFTWAREID');
    if (!$feedurl || !$softwareId) {
        rz_log("CONFIG manquante FEEDURL/PI_SOFTWAREID");
        return '';
    }

    // 3) SOAP call
    try {
        $sc = new SoapClient($feedurl, [
            'connection_timeout' => 10,
            'exceptions'         => true,
            'cache_wsdl'         => WSDL_CACHE_NONE,
            'stream_context'     => stream_context_create(['http' => ['timeout' => 15]]),
        ]);
        $result = $sc->getCommandesStatuts($softwareId, $rezomaticId);
    } catch (Exception $e) {
        rz_log('SOAP ERROR: '.$e->getMessage());
        return '';
    }

    // 4) Normaliser la réponse -> commandeState
    $states = [];
    if (is_object($result) && isset($result->commandeState)) {
        $states = is_array($result->commandeState) ? $result->commandeState : [$result->commandeState];
    } elseif (is_array($result)) {
        $states = $result;
    }

    if (!$states) {
        rz_log('AUCUN commandeState');
        return '';
    }

    // 5) Trier par timestamp DESC si dispo
    usort($states, function($a,$b){
        $ta = isset($a->timestamp) ? strtotime($a->timestamp) : 0;
        $tb = isset($b->timestamp) ? strtotime($b->timestamp) : 0;
        return $tb <=> $ta;
    });

    foreach ($states as $st) {
        $s  = isset($st->statut) ? (int)$st->statut : 0;
        $ui = isset($st->urlInvoice) ? (string)$st->urlInvoice : '';
        $ts = isset($st->timestamp) ? (string)$st->timestamp : '';
        rz_log("state statut={$s} ts={$ts} urlInvoice=".($ui ?: 'vide'));
        if ($s === 2 && $ui !== '') {
            return $ui;
        }
    }
    return '';
}

/** =========== MAIN =========== */
$id_order = resolve_order_id();
$invoiceUrl = '';

if ($id_order > 0) {
    $invoiceUrl = get_rezomatic_invoice_url($id_order);
}

if ($invoiceUrl) {
    rz_log("REDIRECT -> {$invoiceUrl}");
    header('Location: '.$invoiceUrl, true, 302);
    exit;
}

// Pas de facture => page d'info
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture non disponible</title>
    <style>
        body { font-family: Arial, sans-serif; display:flex; justify-content:center; align-items:center; height:100vh; margin:0; background:#f5f5f5; }
        .error-box { background:#fff; padding:40px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.1); text-align:center; max-width:560px; }
        h1 { color:#c62828; margin:0 0 10px; }
        p { color:#555; margin:10px 0; }
        button { background:#1976D2; color:#fff; border:0; padding:10px 24px; border-radius:6px; cursor:pointer; font-size:15px; }
        button:hover { background:#1565C0; }
        .muted { color:#888; font-size:13px; }
    </style>
</head>
<body>
  <div class="error-box">
    <h1>⚠️ Facture non disponible</h1>
    <p>Cette commande n'a pas encore été facturée dans Rezomatic.</p>
    <p class="muted">Commande #<?php echo (int)$id_order; ?></p>
    <button onclick="history.back()">Retour</button>
  </div>
</body>
</html>
