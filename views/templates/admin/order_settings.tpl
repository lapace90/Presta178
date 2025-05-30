<div class="form-section">
    <h3>{l s='Order Settings' mod='pfproductimporter'}</h3>

    <!-- Enable order export -->
    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Enable order export' mod='pfproductimporter'}</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_ALLOW_ORDEREXPORT" id="PI_ALLOW_ORDEREXPORT_on" value="1"
                    {if $fields_value.PI_ALLOW_ORDEREXPORT}checked{/if}>
                <label for="PI_ALLOW_ORDEREXPORT_on">{l s='Yes' mod='pfproductimporter'}</label>
                <input type="radio" name="PI_ALLOW_ORDEREXPORT" id="PI_ALLOW_ORDEREXPORT_off" value="0"
                    {if !$fields_value.PI_ALLOW_ORDEREXPORT}checked{/if}>
                <label for="PI_ALLOW_ORDEREXPORT_off">{l s='No' mod='pfproductimporter'}</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">{l s='Export orders from Prestashop to Rezomatic.' mod='pfproductimporter'}</p>
        </div>
    </div>

    <!-- Valid order only -->
    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Valid order only' mod='pfproductimporter'}</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_VALID_ORDER_ONLY" id="PI_VALID_ORDER_ONLY_on" value="1"
                    {if $fields_value.PI_VALID_ORDER_ONLY}checked{/if}>
                <label for="PI_VALID_ORDER_ONLY_on">{l s='Yes' mod='pfproductimporter'}</label>
                <input type="radio" name="PI_VALID_ORDER_ONLY" id="PI_VALID_ORDER_ONLY_off" value="0"
                    {if !$fields_value.PI_VALID_ORDER_ONLY}checked{/if}>
                <label for="PI_VALID_ORDER_ONLY_off">{l s='No' mod='pfproductimporter'}</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">
                {l s='Export only valid (paid) orders from Prestashop to Rezomatic.' mod='pfproductimporter'}</p>
        </div>
    </div>

    <!-- Update order status -->
    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Update order status' mod='pfproductimporter'}</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_UPDATE_ORDER_STATUS" id="PI_UPDATE_ORDER_STATUS_on" value="1"
                    {if $fields_value.PI_UPDATE_ORDER_STATUS}checked{/if}>
                <label for="PI_UPDATE_ORDER_STATUS_on">{l s='Yes' mod='pfproductimporter'}</label>
                <input type="radio" name="PI_UPDATE_ORDER_STATUS" id="PI_UPDATE_ORDER_STATUS_off" value="0"
                    {if !$fields_value.PI_UPDATE_ORDER_STATUS}checked{/if}>
                <label for="PI_UPDATE_ORDER_STATUS_off">{l s='No' mod='pfproductimporter'}</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">{l s='Update order status from Rezomatic.' mod='pfproductimporter'}</p>
        </div>
    </div>

    <hr>

</div>