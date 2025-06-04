{*
*
*  PrestaShop Product Feed Importer
* 2025 - TGM Ilaria
* Template personnalisé pour le formulaire de mappage des champs
*
*}

<div class="import-form">
    <h2 class="form-title">{l s='Map the FEED and System fields' mod='pfproductimporter'}</h2>

    <form method="post">
        <input type="hidden" size="100" name="vcfeedurl" value="{$feedurl|escape:'htmlall':'UTF-8'}" />

        <div class="note">
            {l s='Note : for fields "taille" and "couleur" you must choose which attribute group to map (used for combinations)' mod='pfproductimporter'}
        </div>

        <table class="category-table table striped">
            <thead>
                <tr>
                    <th width="50%">{l s='Fields from feed' mod='pfproductimporter'}</th>
                    <th width="50%">{l s='Fields from system' mod='pfproductimporter'}</th>
                </tr>
            </thead>
            <tbody>
                {assign var=counter value=0}
                {foreach from=$raw_products_arr item=val key=key name=products}
                    {if in_array($key, ['codeArt', 'taille', 'couleur', 'dArr', 'description'])}
                        {assign var=counter value=$counter+1}
                        <tr>
                            <td>
                                {$val|escape:'htmlall':'UTF-8'}
                                <input type="hidden" name="fld_map[]" value="{$key|escape:'htmlall':'UTF-8'}" />
                            </td>
                            <td>
                                {if $key == 'taille' || $key == 'couleur'}
                                    <select class="select-category" name="sel_{$counter}">
                                        {foreach from=$attrgrp key=pr item=vr}
                                            {assign var='row' value=Vccsv::getfields($key)}
                                            {assign var='sel' value=''}
                                            {if $row && $row.system_field == "{$key}_{$pr}"}
                                                {assign var='sel' value='selected=selected'}
                                            {/if}
                                            <option value="{$key}_{$pr|escape:'htmlall':'UTF-8'}" {$sel|escape:'htmlall':'UTF-8'}>
                                                Attribut : {$vr|escape:'htmlall':'UTF-8'}</option>
                                        {/foreach}
                                    </select>
                                {elseif $key == 'codeArt'}
                                    <select class="select-category" name="sel_{$counter}">
                                        {assign var='row' value=Vccsv::getfields($key)}
                                        <option value="reference" {if $row && $row.system_field == 'reference'}selected{/if}>
                                            reference</option>
                                        <option value="ean13" {if $row && $row.system_field == 'ean13'}selected{/if}>ean13</option>
                                        <option value="upc" {if $row && $row.system_field == 'upc'}selected{/if}>upc</option>
                                    </select>
                                {elseif $key == 'dArr'}
                                    {assign var='row' value=Vccsv::getfields($key)}
                                    <select class="select-category" name="sel_{$counter}">
                                        <option value="ignore_field" {if $row && $row.system_field == 'ignore_field'}selected{/if}>
                                            {l s='Ignore field' mod='pfproductimporter'}</option>
                                        <option value="available_date"
                                            {if $row && $row.system_field == 'available_date'}selected{/if}>
                                            {l s='Available date' mod='pfproductimporter'}</option>
                                    </select>
                                {elseif $key == 'description'}
                                    {assign var='row' value=Vccsv::getfields($key)}
                                    <select class="select-category" name="sel_{$counter}">
                                        <option value="ignore_field" {if $row && $row.system_field == 'ignore_field'}selected{/if}>
                                            {l s='Ignore field' mod='pfproductimporter'}</option>
                                        <option value="description" {if $row && $row.system_field == 'description'}selected{/if}>
                                            {l s='Description' mod='pfproductimporter'}</option>
                                        <option value="description_short"
                                            {if $row && $row.system_field == 'description_short'}selected{/if}>
                                            {l s='Short description' mod='pfproductimporter'}</option>
                                    </select>
                                {/if}
                            </td>
                        </tr>
                    {/if}
                {/foreach}
            </tbody>
        </table>

        <div class="button-group">
            <input type="submit" name="SubmitSaveFields" class="button" value="{l s='Save' mod='pfproductimporter'}" />
        </div>
    </form>
</div>