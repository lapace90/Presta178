<div class="form-section">
    <h3>Paramètres de clients</h3>
    
    <!-- Activer l'export de clients -->
    <div class="form-group">
        <label class="control-label col-lg-3">Activer l'export de clients</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_ALLOW_CUSTOMEREXPORT" id="PI_ALLOW_CUSTOMEREXPORT_on" value="1" {if $fields_value.PI_ALLOW_CUSTOMEREXPORT}checked{/if}>
                <label for="PI_ALLOW_CUSTOMEREXPORT_on">Oui</label>
                <input type="radio" name="PI_ALLOW_CUSTOMEREXPORT" id="PI_ALLOW_CUSTOMEREXPORT_off" value="0" {if !$fields_value.PI_ALLOW_CUSTOMEREXPORT}checked{/if}>
                <label for="PI_ALLOW_CUSTOMEREXPORT_off">Non</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">Exporter les clients de PrestaShop vers Rezomatic.</p>
        </div>
    </div>
    
    <!-- Activer l'import de clients -->
    <div class="form-group">
        <label class="control-label col-lg-3">Activer l'import de clients</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_ALLOW_CUSTOMERIMPORT" id="PI_ALLOW_CUSTOMERIMPORT_on" value="1" {if $fields_value.PI_ALLOW_CUSTOMERIMPORT}checked{/if}>
                <label for="PI_ALLOW_CUSTOMERIMPORT_on">Oui</label>
                <input type="radio" name="PI_ALLOW_CUSTOMERIMPORT" id="PI_ALLOW_CUSTOMERIMPORT_off" value="0" {if !$fields_value.PI_ALLOW_CUSTOMERIMPORT}checked{/if}>
                <label for="PI_ALLOW_CUSTOMERIMPORT_off">Non</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">Importer les clients de Rezomatic vers PrestaShop.</p>
        </div>
    </div>
</div>
