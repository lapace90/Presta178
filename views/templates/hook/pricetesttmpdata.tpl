{*
* 2018 - Definima
*
* DISCLAIMER
*
* @author    Definima <remi@definima.com>
* @copyright 2018 Definima
* @license   https://www.tgm-commerce.fr/
*}
<form action="{$vc_redirect|escape:'htmlall':'UTF-8'}" method="post"  width="100%" >
    <fieldset class="width5" style="float:left;width:600px;margin-top:82px;">
        <p>
        <table width="100%" border="1" style="font-size: small;">
            <tr>
           {$tabledata|escape:'quotes':'UTF-8'}
            <tr><td colspan="10" align="right"> {l s='Totals products' mod='pfproductimporter'} : </td><td>
                    <b>{$a|escape:'htmlall':'UTF-8'}<b></td></tr>
        </table>
        </p>
        <p style="text-align:center;margin-right:10px;" ><input type="hidden" name="fixcategory" value="{$fixcategory|escape:'htmlall':'UTF-8'}" /><input type="hidden" size="100" name="feed_id" value="1" />&nbsp;<input type="button" onclick="history.back();" class="button" value="Retour"  style="margin-top: 10px;"  />&nbsp;<a href="{$base_url|escape:'htmlall':'UTF-8'}modules/pfproductimporter/views/ajax.html" target="_blank" class="button">Importer tous les produits</a>                    
    </fieldset>
</form>
