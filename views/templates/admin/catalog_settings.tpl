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
                    <p class="help-block">
                        Déclencher manuellement l'import de produits. Cela peut prendre quelques minutes.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="control-label col-lg-4">Export manuel</label>
                <div class="col-lg-8">
                    <form action="" method="post">
                        <input type="submit" name="exportallproduct" class="button btn btn-primary"
                            value="Démarrer le processus d'export" />
                    </form>
                    <p class="help-block">
                        Déclencher manuellement l'export de produits vers Rezomatic.
                    </p>
                </div>
            </div>
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

<style>
    .semi-titre {
        font-weight: bold !important;
        font-size: 1.2rem !important;
        padding: 1.4rem;
        color: #959595 !important;
    }
</style>