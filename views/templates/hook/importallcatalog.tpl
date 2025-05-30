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
        <p class="import-status" style="text-align: center; margin: 10px;" >
            {l s='First step completed. Click below for starting product creation. This will takes a few minutes. Please do not interrupt.' mod='pfproductimporter'}
            <br /><br />
            <button class="import-btn">{l s='Start product creation' mod='pfproductimporter'}</button>
        </p>
    </fieldset>
</form>
<script src="//code.jquery.com/jquery-2.1.4.min.js" type="text/javascript"></script>
<script type="text/javascript">
var limit = 100;
var total = 0;
var loops = 0;
var iterations = 0;
var uri = "{$base_url|escape:'htmlall':'UTF-8'}modules/pfproductimporter/ajax.php?secure_key={$secure_key|escape:'htmlall':'UTF-8'}";
$('.import-btn').click(get_total_products);
function get_total_products() {
    $('.import-status').text("{l s='Processing...' mod='pfproductimporter'}");
    $.ajax(uri, {
        'complete': function (jqXHR, textStatus) {
            if (textStatus != 'success') {
                $('.import-status').text("{l s='Catalog import completed.' mod='pfproductimporter'}");
            } else {
                total = parseInt(jqXHR.responseText);
                loops = 1;
                import_products();
            }
        },
        'data': {
        'action': 'count',
    },
    'method': 'POST',
    });
}
function import_products() {
    $('.import-status').text("{l s='Processing...' mod='pfproductimporter'}");
    $.ajax(uri, {
        'complete': function (jqXHR, textStatus) {
        iterations++;
        if (textStatus != 'success') {
            $('.import-status').text("{l s='Catalog import completed.' mod='pfproductimporter'}");
        } else {
            if (iterations < loops) {
                text_res = import_products();
                $(text_res).appendTo('.import-status');
            } else {
                $('.import-status').text("{l s='Catalog import completed.' mod='pfproductimporter'}");
            return;
            }
        }
},
'data': {
'action': 'import',
},
'method': 'POST',
});
}
</script>
