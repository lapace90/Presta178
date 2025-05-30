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
        <legend>{l s='Map the categories' mod='pfproductimporter'}</legend>
        <p style="text-align:center;" ><input type="submit" name="Submitimportprocess" class="button" value="{l s='Start import process' mod='pfproductimporter'}" style="margin-top: 10px;"  /></p>
        <p>
        <table width="100%">
            <tr><td>{l s='Category from FEED' mod='pfproductimporter'}</td><td></td><td>{l s='Category from system' mod='pfproductimporter'}</td></tr>
            {foreach from=$final_products_arr item=val name=products}
                <tr align="left" style="background-color:#ECEADE;"><td>{$val|escape:'htmlall':'UTF-8'}<input type ="hidden" name="cat_map[]" value="{$val|escape:'htmlall':'UTF-8'}" /></td><td>&nbsp;</td><td>
                        {assign var='row' value=Vccsv::getFeedByVal($val)}
                        {if $row}
                            {assign var='systemctid' value=$row.system_catid}
                            {assign var='create_new' value=$row.create_new}
                            {assign var='val1' value='option-'|cat:$systemctid|cat:'-value'}
                            <select name=sel_{$smarty.foreach.products.iteration|intval}>{$options|replace:$val1:' selected=selected'}</select>
                        </td><td>&nbsp;</td><td>
                            {if $create_new == 1}
                                <select name=opt_{$smarty.foreach.products.iteration|intval} >
                                    <option value="1" selected=selected >{l s='Create New' mod='pfproductimporter'}</option><option value="2">{l s='Assign' mod='pfproductimporter'}</option>
                                </select>
                            {else}
                                <select name=opt_{$smarty.foreach.products.iteration|intval} >
                                    <option value="1">{l s='Create New' mod='pfproductimporter'}</option><option value="2" selected=selected >{l s='Assign' mod='pfproductimporter'}</option>
                                </select>
                            {/if}
                        {else}
                            {assign var='val2' value='value'|cat:$systemctid}
                            <select name=sel_{$smarty.foreach.products.iteration|intval}>{$options|replace:val2:''}</select>
                        </td><td>&nbsp;</td><td>
                            <select name=opt_{$smarty.foreach.products.iteration|intval} ><option value="1"  selected=selected>{l s='Create New' mod='pfproductimporter'}</option><option value="2">{l s='Assign' mod='pfproductimporter'}</option></select>
                        {/if}
                    </td>
                </tr>
            {/foreach}
        </table>
        <table width="100%">
            <tr>
                <td align="right">{l s='Select common Category for all Products : ' mod='pfproductimporter'}&nbsp;</td>
                <td align="left"><select name="selfixcategory"><option value="0" >{l s='Select Category' mod='pfproductimporter'}</option>{$cats|escape:'quotes':'UTF-8'}</select></td>
            </tr>
        </table>
        </p>
        <p style="text-align:center;margin-right:10px;" ><input type="hidden" size="100" name="feed_id" value="{$feedid|escape:'htmlall':'UTF-8'}" />&nbsp;<input type="button" onclick="history.back();" class="button" value="{l s='Back' mod='pfproductimporter'}"  style="margin-top: 10px;"  />&nbsp;<input type="submit" name="Submitimportpreview" class="button" value="{l s='Save' mod='pfproductimporter'}" style="margin-top: 10px;"  /></p>
</form>
