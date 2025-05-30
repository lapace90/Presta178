<div class="form-section">
    <h3>{l s='Customer Settings' mod='pfproductimporter'}</h3>

    <!-- Enable customer export -->
    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Enable customer export' mod='pfproductimporter'}</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_ALLOW_CUSTOMEREXPORT" id="PI_ALLOW_CUSTOMEREXPORT_on" value="1" {if $fields_value.PI_ALLOW_CUSTOMEREXPORT}checked{/if}>
                <label for="PI_ALLOW_CUSTOMEREXPORT_on">{l s='Yes' mod='pfproductimporter'}</label>
                <input type="radio" name="PI_ALLOW_CUSTOMEREXPORT" id="PI_ALLOW_CUSTOMEREXPORT_off" value="0" {if !$fields_value.PI_ALLOW_CUSTOMEREXPORT}checked{/if}>
                <label for="PI_ALLOW_CUSTOMEREXPORT_off">{l s='No' mod='pfproductimporter'}</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">{l s='Export customers from Prestashop to Rezomatic.' mod='pfproductimporter'}</p>
        </div>
    </div>

    <!-- Enable customer import -->
    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Enable customer import' mod='pfproductimporter'}</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_ALLOW_CUSTOMERIMPORT" id="PI_ALLOW_CUSTOMERIMPORT_on" value="1" {if $fields_value.PI_ALLOW_CUSTOMERIMPORT}checked{/if}>
                <label for="PI_ALLOW_CUSTOMERIMPORT_on">{l s='Yes' mod='pfproductimporter'}</label>
                <input type="radio" name="PI_ALLOW_CUSTOMERIMPORT" id="PI_ALLOW_CUSTOMERIMPORT_off" value="0" {if !$fields_value.PI_ALLOW_CUSTOMERIMPORT}checked{/if}>
                <label for="PI_ALLOW_CUSTOMERIMPORT_off">{l s='No' mod='pfproductimporter'}</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">{l s='Import customers from Rezomatic into Prestashop.' mod='pfproductimporter'}</p>
        </div>
    </div>
</div>
