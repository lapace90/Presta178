<div class="form-section">
    <h3>{l s='Product Import/Export' mod='pfproductimporter'}</h3>
    <div class="row">
        <!-- Import Column -->
        <div class="col-lg-6">
            <h4>{l s='Import Settings' mod='pfproductimporter'}</h4>

            <!-- Enable product import -->
            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Enable product import' mod='pfproductimporter'}</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_ALLOW_PRODUCTIMPORT" id="PI_ALLOW_PRODUCTIMPORT_on" value="1"
                            {if $fields_value.PI_ALLOW_PRODUCTIMPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTIMPORT_on">{l s='Yes' mod='pfproductimporter'}</label>
                        <input type="radio" name="PI_ALLOW_PRODUCTIMPORT" id="PI_ALLOW_PRODUCTIMPORT_off" value="0"
                            {if !$fields_value.PI_ALLOW_PRODUCTIMPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTIMPORT_off">{l s='No' mod='pfproductimporter'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Import products from Rezomatic into Prestashop.' mod='pfproductimporter'}</p>
                </div>
            </div>

            <!-- Enable product image import -->
            <div class="form-group">
                <label
                    class="control-label col-lg-4">{l s='Enable product image import' mod='pfproductimporter'}</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_ALLOW_PRODUCTIMAGEIMPORT" id="PI_ALLOW_PRODUCTIMAGEIMPORT_on"
                            value="1" {if $fields_value.PI_ALLOW_PRODUCTIMAGEIMPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTIMAGEIMPORT_on">{l s='Yes' mod='pfproductimporter'}</label>
                        <input type="radio" name="PI_ALLOW_PRODUCTIMAGEIMPORT" id="PI_ALLOW_PRODUCTIMAGEIMPORT_off"
                            value="0" {if !$fields_value.PI_ALLOW_PRODUCTIMAGEIMPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTIMAGEIMPORT_off">{l s='No' mod='pfproductimporter'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>
            <!-- Enable designation update -->
            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Enable designation update' mod='pfproductimporter'}</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_UPDATE_DESIGNATION" id="PI_UPDATE_DESIGNATION_on" value="1"
                            {if $fields_value.PI_UPDATE_DESIGNATION}checked{/if}>
                        <label for="PI_UPDATE_DESIGNATION_on">{l s='Yes' mod='pfproductimporter'}</label>
                        <input type="radio" name="PI_UPDATE_DESIGNATION" id="PI_UPDATE_DESIGNATION_off" value="0"
                            {if !$fields_value.PI_UPDATE_DESIGNATION}checked{/if}>
                        <label for="PI_UPDATE_DESIGNATION_off">{l s='No' mod='pfproductimporter'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Update article designation from Rezomatic to Prestashop.' mod='pfproductimporter'}</p>
                </div>
            </div>
            <!-- Enable imported products -->
            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Enable imported products' mod='pfproductimporter'}</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_ACTIVE" id="PI_ACTIVE_on" value="1"
                            {if $fields_value.PI_ACTIVE}checked{/if}>
                        <label for="PI_ACTIVE_on">{l s='Yes' mod='pfproductimporter'}</label>
                        <input type="radio" name="PI_ACTIVE" id="PI_ACTIVE_off" value="0"
                            {if !$fields_value.PI_ACTIVE}checked{/if}>
                        <label for="PI_ACTIVE_off">{l s='No' mod='pfproductimporter'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>
        </div>

        <!-- Export Column -->
        <div class="col-lg-6">
            <h4>{l s='Export Settings' mod='pfproductimporter'}</h4>

            <!-- Enable product export -->
            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Enable product export' mod='pfproductimporter'}</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_ALLOW_PRODUCTEXPORT" id="PI_ALLOW_PRODUCTEXPORT_on" value="1"
                            {if $fields_value.PI_ALLOW_PRODUCTEXPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTEXPORT_on">{l s='Yes' mod='pfproductimporter'}</label>
                        <input type="radio" name="PI_ALLOW_PRODUCTEXPORT" id="PI_ALLOW_PRODUCTEXPORT_off" value="0"
                            {if !$fields_value.PI_ALLOW_PRODUCTEXPORT}checked{/if}>
                        <label for="PI_ALLOW_PRODUCTEXPORT_off">{l s='No' mod='pfproductimporter'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">{l s='Export products from Prestashop to Rezomatic.' mod='pfproductimporter'}
                    </p>
                </div>
            </div>

            <!-- Enable category export -->
            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Enable categories export' mod='pfproductimporter'}</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="PI_ALLOW_CATEGORYEXPORT" id="PI_ALLOW_CATEGORYEXPORT_on" value="1"
                            {if $fields_value.PI_ALLOW_CATEGORYEXPORT}checked{/if}>
                        <label for="PI_ALLOW_CATEGORYEXPORT_on">{l s='Yes' mod='pfproductimporter'}</label>
                        <input type="radio" name="PI_ALLOW_CATEGORYEXPORT" id="PI_ALLOW_CATEGORYEXPORT_off" value="0"
                            {if !$fields_value.PI_ALLOW_CATEGORYEXPORT}checked{/if}>
                        <label for="PI_ALLOW_CATEGORYEXPORT_off">{l s='No' mod='pfproductimporter'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Export categories from Prestashop to Rezomatic.' mod='pfproductimporter'}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons alignés -->
    <div class="row" style="margin-top: 3rem;">
        <div class="col-lg-6">
            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Manual import' mod='pfproductimporter'}</label>
                <div class="col-lg-8">
                    <form action="" method="post">
                        <input type="hidden" name="feedid" value="{$feedid|escape:'htmlall':'UTF-8'}" />
                        <input type="hidden" name="feedurl" value="{$feedurl|escape:'htmlall':'UTF-8'}" />
                        <input type="hidden" name="fixcategory" value="{$fixcategory|escape:'htmlall':'UTF-8'}" />
                        <input type="hidden" name="Submitlimit" value="100000" />
                    </form>
                    {include file="module:pfproductimporter/views/templates/hook/importallcatalog.tpl"}
                    <p class="help-block">
                        {l s='Manually trigger product import. This may take a few minutes.' mod='pfproductimporter'}
                    </p>

                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Manual export' mod='pfproductimporter'}</label>
                <div class="col-lg-8">
                    <form action="" method="post">
                        <input type="submit" name="exportallproduct" class="button btn btn-primary"
                            value="{l s='Start export process' mod='pfproductimporter'}" />
                    </form>
                    <p class="help-block">
                        {l s='Manually trigger product export to Rezomatic.' mod='pfproductimporter'}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>

<!-- Enable sales import -->
<div class="form-group">
    <label class="control-label col-lg-3">{l s='Enable sales import' mod='pfproductimporter'}</label>
    <div class="col-lg-8">
        <span class="switch prestashop-switch fixed-width-lg">
            <input type="radio" name="PI_ALLOW_PRODUCTSALESIMPORT" id="PI_ALLOW_PRODUCTSALESIMPORT_on" value="1"
                {if $fields_value.PI_ALLOW_PRODUCTSALESIMPORT}checked{/if}>
            <label for="PI_ALLOW_PRODUCTSALESIMPORT_on">{l s='Yes' mod='pfproductimporter'}</label>
            <input type="radio" name="PI_ALLOW_PRODUCTSALESIMPORT" id="PI_ALLOW_PRODUCTSALESIMPORT_off" value="0"
                {if !$fields_value.PI_ALLOW_PRODUCTSALESIMPORT}checked{/if}>
            <label for="PI_ALLOW_PRODUCTSALESIMPORT_off">{l s='No' mod='pfproductimporter'}</label>
            <a class="slide-button btn"></a>
        </span>
        <p class="help-block">{l s='Import sales from Rezomatic to Prestashop per product' mod='pfproductimporter'}
        </p>
    </div>
</div>

<!-- Sync sales from -->
<div class="form-group">
    <label class="control-label col-lg-3">{l s='Sync sales from' mod='pfproductimporter'}</label>
    <div class="col-lg-6">
        <input type="text" name="SYNC_STOCK_PDV" value="{$fields_value.SYNC_STOCK_PDV}" />
        <p class="help-block">{l s='Leave empty for global sales.' mod='pfproductimporter'}</p>
    </div>
</div>
<!-- Product reference field -->
<div class="form-group">
    <label class="control-label col-lg-3">{l s='Product reference field' mod='pfproductimporter'}</label>
    <div class="col-lg-6">
        <select name="PI_PRODUCT_REFERENCE">
            <option value="reference" {if $fields_value.PI_PRODUCT_REFERENCE == 'reference'}selected{/if}>
                {l s='Reference' mod='pfproductimporter'}</option>
            <option value="ean13" {if $fields_value.PI_PRODUCT_REFERENCE == 'ean13'}selected{/if}>
                {l s='EAN13' mod='pfproductimporter'}</option>
            <option value="upc" {if $fields_value.PI_PRODUCT_REFERENCE == 'upc'}selected{/if}>
                {l s='UPC' mod='pfproductimporter'}</option>
        </select>
    </div>
</div>