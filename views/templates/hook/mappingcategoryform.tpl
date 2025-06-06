<div class="import-form">
    <h2 class="form-title">{l s='Map the categories' mod='pfproductimporter'}</h2>

    <form action="" method="post" id="category-mapping-form">
        <input type="hidden" size="100" name="feedurl" value="{$feedurl|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" name="feedid" value="{$feedid|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}" />

        <table class="category-table">
            <thead>
                <tr>
                    <th width="50%">{l s='Category from FEED' mod='pfproductimporter'}</th>
                    <th width="50%">{l s='Category from system' mod='pfproductimporter'}</th>
                </tr>
            </thead>
            <tbody>
                {if !empty($final_products_arr)}
                    {foreach from=$final_products_arr item=category_name}
                        {assign var='row' value=$mappedCategories[$category_name]}
                        <tr>
                            <td>
                                {$category_name|escape:'htmlall':'UTF-8'}
                                <input type="hidden" name="cat_map[]" value="{$category_name|escape:'htmlall':'UTF-8'}" />
                            </td>
                            <td>
                                <select class="select-category" name="system_cat[]">
                                    <option value="0">{l s='-- Select --' mod='pfproductimporter'}</option>
                                    {foreach from=$categoryOptionsArray item=option}
                                        <option value="{$option.id_category|intval}"
                                            {if $row && isset($row.system_catid) && $row.system_catid == $option.id_category}selected="selected"
                                            {/if}>
                                            {str_repeat('&nbsp;&nbsp;', $option.depth)}{$option.name|escape:'htmlall':'UTF-8'}
                                        </option>
                                    {/foreach}
                                </select>
                            </td>
                        </tr>
                    {/foreach}
                {else}
                    <tr>
                        <td colspan="2" class="text-center">
                            {l s='No categories found in the feed' mod='pfproductimporter'}
                        </td>
                    </tr>
                {/if}
            </tbody>
        </table>

        <div class="button-group">
            <input type="submit" name="Submitimportpreview" class="button btn-default pull-right"
                value="{l s='Save' mod='pfproductimporter'}" />
        </div>
    </form>
</div>

<style>
    .import-form {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        background-color: #f9f9f9;
        border-radius: 8px;
    }

    .form-title {
        text-align: center;
        margin-bottom: 20px;
    }

    .category-table {
        width: 100%;
        border-collapse: collapse;
    }

    .category-table th,
    .category-table td {
        padding: 10px;
        border: 1px solid #ddd;
    }

    .category-table th {
        background-color: #f2f2f2;
    }

    .select-category {
        width: 100%;
        padding: 8px;
        border-radius: 4px;
        border: 1px solid #ccc;
    }

    .button-group {
        text-align: center;
        margin-top: 20px;
    }

    .button-group .button {
        padding: 10px 20px;
        background-color: #2eacce;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .button-group .button:hover {
        background-color: #2eacce;
    }

    .text-center {
        text-align: center;
        color: #999;
    }
</style>