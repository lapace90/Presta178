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
    <fieldset>
        <p style="text-align: center; margin: 10px;" >
            {l s='Settings are saved. Please click below for starting synchronization. This will takes a few minutes.' mod='pfproductimporter'}
            <br /><br />
            <input type="hidden" size="100" name="feed_id" value="{$feed_id|escape:'htmlall':'UTF-8'}" />&nbsp;
            <input type="hidden" size="100" name="vcfeedurl" value="{$vcfeedurl|escape:'htmlall':'UTF-8'}" />&nbsp;
            <input type="hidden" size="100" name="fixcategory" value="{$fixcategory|escape:'htmlall':'UTF-8'}" />
            <input type="hidden" name="Submitlimit" value="100000" />
            <input type="submit" name="Submitimportprocess" class="button" value="{l s='Start import process' mod='pfproductimporter'}" />
        </p>
    </fieldset>
</form>
