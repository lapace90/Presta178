<div class="form-section">
    <h3>Paramètres de commandes</h3>
    
    <!-- Activer l'export de commandes -->
    <div class="form-group">
        <label class="control-label col-lg-3">Activer l'export de commandes</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_ALLOW_ORDEREXPORT" id="PI_ALLOW_ORDEREXPORT_on" value="1"
                    {if $fields_value.PI_ALLOW_ORDEREXPORT}checked{/if}>
                <label for="PI_ALLOW_ORDEREXPORT_on">Oui</label>
                <input type="radio" name="PI_ALLOW_ORDEREXPORT" id="PI_ALLOW_ORDEREXPORT_off" value="0"
                    {if !$fields_value.PI_ALLOW_ORDEREXPORT}checked{/if}>
                <label for="PI_ALLOW_ORDEREXPORT_off">Non</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">Exporter les commandes de PrestaShop vers Rezomatic.</p>
        </div>
    </div>
    
    <!-- Commandes valides uniquement -->
    <div class="form-group">
        <label class="control-label col-lg-3">Commandes valides uniquement</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_VALID_ORDER_ONLY" id="PI_VALID_ORDER_ONLY_on" value="1"
                    {if $fields_value.PI_VALID_ORDER_ONLY}checked{/if}>
                <label for="PI_VALID_ORDER_ONLY_on">Oui</label>
                <input type="radio" name="PI_VALID_ORDER_ONLY" id="PI_VALID_ORDER_ONLY_off" value="0"
                    {if !$fields_value.PI_VALID_ORDER_ONLY}checked{/if}>
                <label for="PI_VALID_ORDER_ONLY_off">Non</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">
                Exporter uniquement les commandes valides (payées) de PrestaShop vers Rezomatic.
            </p>
        </div>
    </div>
    
    <!-- Mettre à jour le statut des commandes -->
    <div class="form-group">
        <label class="control-label col-lg-3">Mettre à jour le statut des commandes</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_UPDATE_ORDER_STATUS" id="PI_UPDATE_ORDER_STATUS_on" value="1"
                    {if $fields_value.PI_UPDATE_ORDER_STATUS}checked{/if}>
                <label for="PI_UPDATE_ORDER_STATUS_on">Oui</label>
                <input type="radio" name="PI_UPDATE_ORDER_STATUS" id="PI_UPDATE_ORDER_STATUS_off" value="0"
                    {if !$fields_value.PI_UPDATE_ORDER_STATUS}checked{/if}>
                <label for="PI_UPDATE_ORDER_STATUS_off">Non</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">Mettre à jour le statut des commandes depuis Rezomatic.</p>
        </div>
    </div>
    <hr>
</div>