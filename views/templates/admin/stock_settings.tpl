<!-- Container pour les notifications toast -->
<div class="toast-container" id="toast-container"></div>

<div class="form-section">
    <!-- Import des stocks -->
    <div class="form-group">
        <label class="control-label col-lg-3">Import des stocks</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_ALLOW_STOCKIMPORT" id="PI_ALLOW_STOCKIMPORT_on" value="1"
                    {if $fields_value.PI_ALLOW_STOCKIMPORT}checked{/if}>
                <label for="PI_ALLOW_STOCKIMPORT_on">Oui</label>
                <input type="radio" name="PI_ALLOW_STOCKIMPORT" id="PI_ALLOW_STOCKIMPORT_off" value="0"
                    {if !$fields_value.PI_ALLOW_STOCKIMPORT}checked{/if}>
                <label for="PI_ALLOW_STOCKIMPORT_off">Non</label>
                <a class="slide-button btn"></a>
            </span>
        </div>
    </div>

    <!-- Synchroniser les stocks depuis -->
    <div class="form-group">
        <label class="control-label col-lg-3">Synchroniser les stocks depuis</label>
        <div class="col-lg-9">
            <input type="text" name="SYNC_STOCK_PDV" value="{$fields_value.SYNC_STOCK_PDV}" />
            <p class="help-block">Entrer l'identifiant magasin Rezomatic ou laisser vide pour un stock global.</p>
        </div>
    </div>

</div>
