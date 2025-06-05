<div class="import-form">
    <h2 class="form-title">{l s='Map the categories' mod='pfproductimporter'}</h2>

    <form action="" method="post" id="category-mapping-form">
        <input type="hidden" size="100" name="feedurl" value="{$feedurl|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" name="feedid" value="{$feedid|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}" />

        <table class="category-table">
            <thead>
                <tr>
                    <th width="46%">{l s='Category from FEED' mod='pfproductimporter'}</th>
                    <th width="46%">{l s='Category from system' mod='pfproductimporter'}</th>
                    <th width="8%" class="action-cell">{l s='' mod='pfproductimporter'}</th>
                </tr>
            </thead>
            <tbody>
                {if !empty($final_products_arr)}
                    {foreach from=$final_products_arr item=category_name name=products}
                        {assign var='row' value=Vccsv::getFeedByVal($category_name, $feedid)}
                        <tr>
                            <td>
                                {$category_name|escape:'htmlall':'UTF-8'}
                                <input type="hidden" name="cat_map[]" value="{$category_name|escape:'htmlall':'UTF-8'}" />
                            </td>
                            <td>
                                <select class="select-category" name="system_cat[]">
                                    <option value="0">{l s='-- Select --' mod='pfproductimporter'}</option>
                                    {if isset($cats) && is_array($cats) && !empty($cats)}
                                        {foreach from=$cats item=category_group}
                                            {if is_array($category_group)}
                                                {foreach from=$category_group item=category}
                                                    {if isset($category.infos)}
                                                        <option value="{$category.infos.id_category|intval}"
                                                            {if $row && isset($row.system_catid) && $row.system_catid == $category.infos.id_category}selected="selected"
                                                            {/if}>
                                                            {str_repeat('&nbsp;&nbsp;', $category.infos.level_depth)}{$category.infos.name|escape:'htmlall':'UTF-8'}
                                                        </option>
                                                    {/if}
                                                {/foreach}
                                            {/if}
                                        {/foreach}
                                    {/if}
                                </select>
                            </td>
                            <td class="action-cell">
                                <span class="delete-icon" onclick="removeCategory(this)"
                                    title="{l s='Delete this category' mod='pfproductimporter'}">×</span>
                                {if !$row}
                                    <input type="hidden" name="is_new[]" value="1" />
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                {else}
                    <tr>
                        <td colspan="3" class="text-center">
                            {l s='No categories found in the feed' mod='pfproductimporter'}
                        </td>
                    </tr>
                {/if}
            </tbody>
        </table>

        <div class="common-category">
            <label>{l s='Select common Category for all Products:' mod='pfproductimporter'}</label>
            <select class="select-category"  name="default_category">
                <option value="0">{l s='-- Select --' mod='pfproductimporter'}</option>
                {if isset($cats) && is_array($cats) && !empty($cats)}
                    {foreach from=$cats item=category_group}
                        {if is_array($category_group)}
                            {foreach from=$category_group item=category}
                                {if isset($category.infos)}
                                    <option value="{$category.infos.id_category|intval}"
                                        {if $row && isset($row.system_catid) && $row.system_catid == $category.infos.id_category}selected="selected"
                                        {/if}>
                                        {str_repeat('&nbsp;&nbsp;', $category.infos.level_depth)}{$category.infos.name|escape:'htmlall':'UTF-8'}
                                    </option>
                                {/if}
                            {/foreach}
                        {/if}
                    {/foreach}
                {/if}
            </select>
        </div>

        <div class="button-group">
            <input type="submit" name="Submitimportpreview" class="button btn-default pull-right"
                value="{l s='Save' mod='pfproductimporter'}" />
        </div>
    </form>
</div>

<script type="text/javascript">
    function removeCategory(element) {
        var row = element.closest('tr');
        if (row) {
            row.remove();
        }
    }
</script>
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

    .action-cell {
        text-align: center;
    }

    .delete-icon {
        cursor: pointer;
        color: red;
    }

    .common-category {
        margin-top: 20px;
    }

    .common-category label {
        display: block;
        margin-bottom: 10px;
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