<div class="form-section">
    <h3>Import/Export de produits</h3>
    <div class="row">
        <!-- Colonne Import -->
        <div class="col-lg-6">
            <h4 class="semi-titre">Paramètres d'import</h4>

            <!-- Activer l'import de produits -->
            <div class="form-group">
                <label class="control-label col-lg-4">Activer l'import de produits</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_ALLOW_PRODUCTIMPORT" id="PI_ALLOW_PRODUCTIMPORT_on" value="1"
                            {if $fields_value.PI_ALLOW_PRODUCTIMPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTIMPORT_on">Oui</label>
                        <input type="radio" name="PI_ALLOW_PRODUCTIMPORT" id="PI_ALLOW_PRODUCTIMPORT_off" value="0"
                            {if !$fields_value.PI_ALLOW_PRODUCTIMPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTIMPORT_off">Non</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        Importer les produits de Rezomatic vers PrestaShop.
                    </p>
                </div>
            </div>

            <!-- Activer l'import d'images -->
            <div class="form-group">
                <label class="control-label col-lg-4">Activer l'import d'images produit</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_ALLOW_PRODUCTIMAGEIMPORT" id="PI_ALLOW_PRODUCTIMAGEIMPORT_on"
                            value="1" {if $fields_value.PI_ALLOW_PRODUCTIMAGEIMPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTIMAGEIMPORT_on">Oui</label>
                        <input type="radio" name="PI_ALLOW_PRODUCTIMAGEIMPORT" id="PI_ALLOW_PRODUCTIMAGEIMPORT_off"
                            value="0" {if !$fields_value.PI_ALLOW_PRODUCTIMAGEIMPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTIMAGEIMPORT_off">Non</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>

            <!-- Activer la mise à jour des désignations -->
            <div class="form-group">
                <label class="control-label col-lg-4">Activer la mise à jour des désignations</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_UPDATE_DESIGNATION" id="PI_UPDATE_DESIGNATION_on" value="1"
                            {if $fields_value.PI_UPDATE_DESIGNATION}checked{/if}>
                        <label for="PI_UPDATE_DESIGNATION_on">Oui</label>
                        <input type="radio" name="PI_UPDATE_DESIGNATION" id="PI_UPDATE_DESIGNATION_off" value="0"
                            {if !$fields_value.PI_UPDATE_DESIGNATION}checked{/if}>
                        <label for="PI_UPDATE_DESIGNATION_off">Non</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        Mettre à jour les désignations d'articles de Rezomatic vers PrestaShop.
                    </p>
                </div>
            </div>

            <!-- Activer les produits importés -->
            <div class="form-group">
                <label class="control-label col-lg-4">Activer les produits importés</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_ACTIVE" id="PI_ACTIVE_on" value="1"
                            {if $fields_value.PI_ACTIVE}checked{/if}>
                        <label for="PI_ACTIVE_on">Oui</label>
                        <input type="radio" name="PI_ACTIVE" id="PI_ACTIVE_off" value="0"
                            {if !$fields_value.PI_ACTIVE}checked{/if}>
                        <label for="PI_ACTIVE_off">Non</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>
        </div>

        <!-- Colonne Export -->
        <div class="col-lg-6">
            <h4 class="semi-titre">Paramètres d'export</h4>

            <!-- Activer l'export de produits -->
            <div class="form-group">
                <label class="control-label col-lg-4">Activer l'export de produits</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_ALLOW_PRODUCTEXPORT" id="PI_ALLOW_PRODUCTEXPORT_on" value="1"
                            {if $fields_value.PI_ALLOW_PRODUCTEXPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTEXPORT_on">Oui</label>
                        <input type="radio" name="PI_ALLOW_PRODUCTEXPORT" id="PI_ALLOW_PRODUCTEXPORT_off" value="0"
                            {if !$fields_value.PI_ALLOW_PRODUCTEXPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTEXPORT_off">Non</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">Exporter les produits de PrestaShop vers Rezomatic.</p>
                </div>
            </div>

            <!-- Activer l'export de catégories -->
            <div class="form-group">
                <label class="control-label col-lg-4">Activer l'export de catégories</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_ALLOW_CATEGORYEXPORT" id="PI_ALLOW_CATEGORYEXPORT_on" value="1"
                            {if $fields_value.PI_ALLOW_CATEGORYEXPORT}checked{/if}>
                        <label for="PI_ALLOW_CATEGORYEXPORT_on">Oui</label>
                        <input type="radio" name="PI_ALLOW_CATEGORYEXPORT" id="PI_ALLOW_CATEGORYEXPORT_off" value="0"
                            {if !$fields_value.PI_ALLOW_CATEGORYEXPORT}checked{/if}>
                        <label for="PI_ALLOW_CATEGORYEXPORT_off">Non</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        Exporter les catégories de PrestaShop vers Rezomatic.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons alignés -->
    <div class="row" style="margin-top: 3rem;">
        <div class="col-lg-6">
            <div class="form-group">
                <label class="control-label col-lg-4">Import manuel</label>
                <div class="col-lg-8">
                    <form action="" method="post">
                        <input type="hidden" name="feedid" value="{$feedid|escape:'htmlall':'UTF-8'}" />
                        <input type="hidden" name="feedurl" value="{$feedurl|escape:'htmlall':'UTF-8'}" />
                        <input type="hidden" name="fixcategory" value="{$fixcategory|escape:'htmlall':'UTF-8'}" />
                        <input type="hidden" name="Submitlimit" value="100000" />
                    </form>
                    {include file="module:pfproductimporter/views/templates/hook/importallcatalog.tpl"}

                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="control-label col-lg-4">Export manuel</label>
                <div class="col-lg-8">
                    <form action="" method="post">
                        {* <input type="submit" name="exportallproduct" {if !$fields_value.PI_ALLOW_PRODUCTEXPORT}disabled="disabled" {/if} class="button btn btn-primary"
                            value="Démarrer le processus d'export" /> *}
                    </form>
                    {include file="module:pfproductimporter/views/templates/hook/exportallcatalog.tpl"}

                </div>
            </div>
        </div>

    </div>
    <!-- Div dédiée aux barres de progression (pleine largeur) -->
    <div id="progress-bars-container" style="display: none; margin-top: 20px; width: 100%;">
        <!-- Barre de progression pour l'import -->
        <div id="import-progress-section" class="progress-section" style="display: none;">
            <h4>Progression de l'import</h4>
            <div class="progress-bar-fullwidth">
                <div id="import-progress" class="progress-bar-fill-tgm" style="width: 0%;"></div>
            </div>
            <p id="import-status" class="progress-text">En attente...</p>
        </div>

        <!-- Barre de progression pour l'export -->
        <div id="export-progress-section" class="progress-section" style="display: none;">
            <h4>Progression de l'export</h4>
            <div class="progress-bar-fullwidth">
                <div id="export-progress" class="progress-bar-fill-tgm" style="width: 0%;"></div>
            </div>
            <p id="export-status" class="progress-text">En attente...</p>
        </div>
    </div>
</div>

<hr>

<!-- Activer l'import des soldes -->
<div class="form-group">
    <label class="control-label col-lg-3">Activer l'import des soldes</label>
    <div class="col-lg-8">
        <span class="switch prestashop-switch fixed-width-lg">
            <input type="radio" name="PI_ALLOW_PRODUCTSALESIMPORT" id="PI_ALLOW_PRODUCTSALESIMPORT_on" value="1"
                {if $fields_value.PI_ALLOW_PRODUCTSALESIMPORT}checked{/if}>
            <label for="PI_ALLOW_PRODUCTSALESIMPORT_on">Oui</label>
            <input type="radio" name="PI_ALLOW_PRODUCTSALESIMPORT" id="PI_ALLOW_PRODUCTSALESIMPORT_off" value="0"
                {if !$fields_value.PI_ALLOW_PRODUCTSALESIMPORT}checked{/if}>
            <label for="PI_ALLOW_PRODUCTSALESIMPORT_off">Non</label>
            <a class="slide-button btn"></a>
        </span>
        <p class="help-block">Importer les soldes de Rezomatic vers PrestaShop par produit</p>
    </div>
</div>

<!-- Synchroniser les soldes depuis -->
<div class="form-group">
    <label class="control-label col-lg-3">Synchroniser les soldes depuis</label>
    <div class="col-lg-6">
        <input type="text" name="SYNC_STOCK_PDV" value="{$fields_value.SYNC_STOCK_PDV}" />
        <p class="help-block">Laisser vide pour les soldes globaux.</p>
    </div>
</div>

<!-- Champ de référence produit -->
<div class="form-group">
    <label class="control-label col-lg-3">Champ de référence produit</label>
    <div class="col-lg-6">
        <select name="PI_PRODUCT_REFERENCE">
            <option value="reference" {if $fields_value.PI_PRODUCT_REFERENCE == 'reference'}selected{/if}>
                Référence</option>
            <option value="ean13" {if $fields_value.PI_PRODUCT_REFERENCE == 'ean13'}selected{/if}>
                EAN13</option>
            <option value="upc" {if $fields_value.PI_PRODUCT_REFERENCE == 'upc'}selected{/if}>
                UPC</option>
        </select>
    </div>
</div>

<script>
    function startDirectImport() {
        $('#confirmModal').fadeOut();
        $('#progress-bars-container').show();
        $('#import-progress-section').show();
        $('#import-status').text('Import en cours...');

        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                'direct_import_now': 1,
                'token': $('input[name="token"]').val()
            },
            // Supprimez temporairement dataType: 'json'
            success: function(data) {
                console.log('Réponse brute:', data);
                console.log('Type de la réponse:', typeof data);

                // Tentez de parser manuellement
                try {
                    var jsonData = JSON.parse(data);
                    console.log('JSON parsé:', jsonData);

                    if (jsonData.success) {
                        $('#import-progress').css('width', '100%');
                        $('#import-status').html(
                            '<strong>✅ Import terminé !</strong><br>' +
                            jsonData.stats.produits_crees + ' produits créés<br>' +
                            jsonData.stats.declinaisons_creees + ' déclinaisons créées<br>' +
                            'Temps : ' + jsonData.stats.temps_execution
                        );
                    } else {
                        $('#import-status').html('❌ ' + jsonData.message);
                    }
                } catch (e) {
                    console.error('Erreur de parsing JSON:', e);
                    $('#import-status').html('Import terminé');
                    $('#import-progress').css('width', '100%');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur AJAX:', status, error);
                console.log('Réponse complète:', xhr.responseText);
                $('#import-status').text('❌ Erreur: ' + status);
            }
        });
    }
</script>

<style>
    /* ===== STYLES GÉNÉRAUX ===== */
    .semi-titre {
        font-weight: bold !important;
        font-size: 1.2rem !important;
        padding: 1.4rem;
        color: #959595 !important;
    }

    /* ===== STYLES POUR BOUTONS DÉSACTIVÉS ===== */
    .btn-disabled,
    .btn:disabled,
    input[type="button"]:disabled {
        opacity: 0.5 !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
        background-color: #6c757d !important;
    }

    /* ===== STYLES POUR MODALES ===== */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background-color: white;
        border-radius: 8px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #e5e5e5;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 20px;
        color: #333;
    }

    .modal-close {
        font-size: 28px;
        font-weight: bold;
        color: #aaa;
        cursor: pointer;
        line-height: 20px;
    }

    .modal-close:hover,
    .modal-close:focus {
        color: #000;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-body p {
        margin: 10px 0;
        color: #555;
    }

    .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #e5e5e5;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.3s;
    }

    .btn-primary {
        background-color: #007bff;
        color: white;
    }

    .btn-primary:hover:not(:disabled) {
        background-color: #0056b3;
    }

    .btn-cancel {
        background-color: #6c757d;
        color: white;
    }

    .btn-cancel:hover {
        background-color: #545b62;
    }

    /* ===== STYLES POUR BARRES DE PROGRESSION ===== */
    #progress-bars-container {
        width: 100%;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 15px;
        margin-top: 20px;
    }

    .progress-section {
        width: 100%;
        margin-bottom: 20px;
    }

    .progress-section h4 {
        margin-top: 0;
        margin-bottom: 10px;
        color: #495057;
    }

    /* Barre de progression */
    .progress-bar-fullwidth {
        width: 100%;
        height: 25px;
        background-color: #e9ecef;
        border-radius: 5px;
        overflow: hidden;
        margin-bottom: 10px;
    }

    /* Remplissage de la barre de progression */
    .progress-bar-fill-tgm {
        display: block;
        height: 100%;
        background: #28a745 !important;
        width: 0%;
        transition: width 0.3s ease !important;
        border-radius: 5px !important;
    }

    .progress-text {
        margin: 0;
        font-size: 14px;
        color: #495057;
        text-align: center;
    }

    .progress-text.success {
        color: #28a745;
    }
</style>