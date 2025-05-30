{*
* 2018 - Definima
*
* DISCLAIMER
*
* @author    Definima <remi@definima.com>
* @copyright 2018 Definima
* @license   https://www.tgm-commerce.fr/
*}
<form action="{$vc_redirect|escape:'htmlall':'UTF-8'}" method="post">
    <input type="hidden" size="100" name="vcfeedurl" value="{$feedurl|escape:'htmlall':'UTF-8'}" />
    <fieldset>
        <legend>{l s='Map the FEED and System fields' mod='pfproductimporter'}</legend>
        <p><em>{l s='Note : for fields "taille" and "couleur" you must choose which attribute group to map (used for combinations)' mod='pfproductimporter'}</em></p>
        <p>
        <table width="100%">
            <tr><td>{l s='Fields from feed' mod='pfproductimporter'}</td><td></td><td>{l s='Fields from system' mod='pfproductimporter'}</td></tr>
            {foreach from=$raw_products_arr item=val key=key name=products}
                <tr align="left" style="background-color:#ECEADE;">
                    <td>
                        {$key|escape:'htmlall':'UTF-8'}
                        <input type="hidden" name="fld_map[]" value="{$key|escape:'htmlall':'UTF-8'}" />
                    </td>
                    <td>&nbsp;</td>
                    <td>
                        {**
                         * @edit Definima
                         * Pour les attributs des déclinaisons on ne choisit pas un champ d'un produit mais un attribut existant.
                         * Il sera enregistré par exemple "taille_3" où taille est l'attribut et 3 correspond au id_attribute_group réel
                         *}
                        {if $key == 'taille' || $key == 'couleur'}
                            <select name=sel_{$smarty.foreach.products.iteration|intval} >
                                {foreach from=$attrgrp key=pr item=vr}
                                    {assign var='row' value=Vccsv::getfields($key)}
                                    {assign var='sel' value=''}
                                    {if $row.system_field == "$key{'_'}$pr"}
                                        {assign var='sel' value='  selected=selected '}
                                    {/if}
                                    <option style="float:left;" value="{$key|escape:'htmlall':'UTF-8'}_{$pr|escape:'htmlall':'UTF-8'}"  {$sel|escape:'htmlall':'UTF-8'}  >Attribut : {$vr|escape:'htmlall':'UTF-8'}</option>
                                {/foreach}
                            </select>
                        {else}
                            <select name=sel_{$smarty.foreach.products.iteration|intval} >
                                {foreach from=$newproductfields item=pr}
                                    {assign var='row' value=Vccsv::getfields($key)}
                                    {assign var='sel' value=''}
                                    {if $row.system_field == $pr}
                                        {assign var='sel' value='  selected=selected '}
                                    {/if}
                                    <option style="float:left;" value="{$pr|escape:'htmlall':'UTF-8'}"  {$sel|escape:'htmlall':'UTF-8'}  >{$pr|escape:'htmlall':'UTF-8'}</option>
                                {/foreach}
                            </select>
                        {/if}
                    </td>
                </tr>
                {/foreach}
        </table>
        </p>
        <p style="text-align:center;margin-right:10px;" ><input type="button" onclick="history.back();" class="button" value="{l s='Back' mod='pfproductimporter'}"  style="margin-top: 10px;"  />&nbsp;<input type="submit" name="Submitmapcategory" class="button" value="{l s='Save' mod='pfproductimporter'}" style="margin-top: 10px;"  /></p>
    </fieldset>
</form>
