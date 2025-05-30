<div class="form-section">
    <h3>{l s='General Settings' mod='pfproductimporter'}</h3>

    <!-- Feed Url -->
    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Feed Url' mod='pfproductimporter'}</label>
        <div class="col-lg-9">
            <input type="text" name="SYNC_CSV_FEEDURL" value="{$fields_value.SYNC_CSV_FEEDURL}" class="lg" required />
        </div>
    </div>

    <!-- Software ID -->
    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Software ID' mod='pfproductimporter'}</label>
        <div class="col-lg-9">
            <input type="text" name="PI_SOFTWAREID" value="{$fields_value.PI_SOFTWAREID}" class="lg" required />
        </div>
    </div>

    <!-- Sync quantities from -->
    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Sync quantities from' mod='pfproductimporter'}</label>
        <div class="col-lg-9">
            <input type="text" name="SYNC_STOCK_PDV" value="{$fields_value.SYNC_STOCK_PDV}" />
            <p class="help-block">{l s='Leave empty for global quantities.' mod='pfproductimporter'}</p>
        </div>
    </div>

    <!-- Enable periodic update -->
    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Enable periodic update' mod='pfproductimporter'}</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_CRON_TASK" id="PI_CRON_TASK_on" value="1" {if $fields_value.PI_CRON_TASK}checked{/if}>
                <label for="PI_CRON_TASK_on">{l s='Yes' mod='pfproductimporter'}</label>
                <input type="radio" name="PI_CRON_TASK" id="PI_CRON_TASK_off" value="0" {if !$fields_value.PI_CRON_TASK}checked{/if}>
                <label for="PI_CRON_TASK_off">{l s='No' mod='pfproductimporter'}</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">
                {l s='Last update' mod='pfproductimporter'} {Tools::displayDate(Configuration::get('PI_LAST_CRON'), null, true)}
            </p>
        </div>
    </div>
</div>
