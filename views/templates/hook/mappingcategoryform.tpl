<div class="import-form">
    <h2 class="form-title">
        {l s='Map Categories' mod='pfproductimporter'}
    </h2>

    <form action="" method="post" id="category-mapping-form">
        <input type="hidden" size="100" name="feedurl" value="{$feedurl|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" name="feedid" value="{$feedid|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}" />

        <div class="mapping-stats">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-number">{count($final_products_arr)}</div>
                        <div class="stat-label">{l s='Categories Found' mod='pfproductimporter'}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-number" id="hierarchy-count">0</div>
                        <div class="stat-label">{l s='With Hierarchy' mod='pfproductimporter'}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-number" id="mapped-count">0</div>
                        <div class="stat-label">{l s='Already Mapped' mod='pfproductimporter'}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="category-table table table-striped table-hover">
                <thead class="thead-primary">
                    <tr>
                        <th width="50%">
                            <i class="icon-tags"></i>
                            {l s='Rezomatic Category' mod='pfproductimporter'}
                        </th>
                        <th width="40%">
                            <i class="icon-folder-open"></i>
                            {l s='PrestaShop Category' mod='pfproductimporter'}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {if !empty($final_products_arr)}
                        {foreach from=$final_products_arr item=category_name}
                            {assign var='row' value=null}
                            {if isset($mappedCategories[$category_name])}
                                {assign var='row' value=$mappedCategories[$category_name]}
                            {/if}

                            <tr class="category-row" data-category="{$category_name|escape:'htmlall':'UTF-8'}">
                                <td>
                                    <div class="category-display">
                                        {* <div class="hierarchy-path">
                                            <i class="icon-sitemap text-success"></i>
                                            <strong>{$category_name|escape:'htmlall':'UTF-8'}</strong>
                                        </div> *}

                                        <div class="hierarchy-parts">
                                            {assign var="parts" value=" > "|explode:$category_name}

                                            <div class="hierarchy-parts">
                                                {if $parts|@count >= 1 && $parts[0]|trim != ''}
                                                    <span class="badge badge-info">
                                                        {$parts[0]|escape:'htmlall':'UTF-8'}</span>
                                                {/if}
                                                {if $parts|@count >= 2 && $parts[1]|trim != ''}
                                                    <span class="badge badge-success">
                                                        {$parts[1]|escape:'htmlall':'UTF-8'}</span>
                                                {/if}
                                                {if $parts|@count >= 3 && $parts[2]|trim != ''}
                                                    <span class="badge badge-warning">
                                                        {$parts[2]|escape:'htmlall':'UTF-8'}</span>
                                                {/if}
                                            </div>

                                        </div>
                                    </div>
                                    <input type="hidden" name="cat_map[]" value="{$category_name|escape:'htmlall':'UTF-8'}" />
                                </td>
                                <td>
                                    <select class="select-category form-control" name="system_cat[]"
                                        onchange="updateMappingStats()">
                                        <option value="0">{l s='-- Select PrestaShop Category --' mod='pfproductimporter'}
                                        </option>
                                        {foreach from=$categoryOptionsArray item=option}
                                            <option value="{$option.id_category|intval}"
                                                {if $row && isset($row.system_catid) && $row.system_catid == $option.id_category}selected="selected"
                                                {/if}>
                                                {str_repeat('&nbsp;&nbsp;&nbsp;', $option.depth)}{$option.name|escape:'htmlall':'UTF-8'}
                                            </option>
                                        {/foreach}
                                    </select>
                                </td>

                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="3" class="text-center alert alert-warning">
                                <i class="icon-warning"></i>
                                {l s='No categories found. Please check your feed connection.' mod='pfproductimporter'}
                            </td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>

        <div class="button-group">
            <div class="pull-left">
                <button type="button" class="btn btn-default" onclick="clearAllMappings()">
                    <i class="icon-eraser"></i>
                    {l s='Clear All' mod='pfproductimporter'}
                </button>
                <button type="button" class="btn btn-info" onclick="autoSuggestMappings()">
                    <i class="icon-magic"></i>
                    {l s='Auto-suggest' mod='pfproductimporter'}
                </button>
            </div>
            <div class="pull-right">
                <button type="submit" name="Submitimportpreview" class="btn btn-primary">

                    {l s='Save Category Mapping' mod='pfproductimporter'}
                </button>
            </div>
            <div class="clearfix"></div>
        </div>
    </form>
</div>

<style>
    .close-btn {
        color: #aaa;
        float: right;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
    }

    .close-btn:hover {
        color: #000;
    }


    .thead-primary {

        background-color: #2eacce;
        color: #FFF;
        font-size: large;
    }


    .import-form {
        margin: 20px 0;
        background-color: #fff;
        border-radius: 8px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .form-title {
        text-align: center;
        margin-bottom: 25px;
        color: #333;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 15px;
    }

    .mapping-stats {
        margin-bottom: 25px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 6px;
    }

    .stat-box {
        text-align: center;
        padding: 15px;
        background: white;
        border-radius: 6px;
        border: 1px solid #e9ecef;
        margin-bottom: 10px;
    }

    .stat-number {
        font-size: 2em;
        font-weight: bold;
        color: #2c3e50;
        line-height: 1;
    }

    .stat-label {
        font-size: 0.9em;
        color: #7f8c8d;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 5px;
    }

    .category-table {
        background: white;
    }

    .category-table th {
        font-weight: 600;
        padding: 15px 10px;
    }

    .category-row:hover {
        background-color: #f8f9fa;
    }

    .hierarchy-path {
        margin-bottom: 8px;
        font-size: 1.1em;
    }

    .hierarchy-parts {
        margin-top: 5px;
    }

    .hierarchy-parts .badge {
        margin-right: 5px;
        margin-bottom: 3px;
        font-size: 0.8rem;
        padding: 3px 6px;
    }

    .simple-category {
        padding: 5px 0;
    }

    .select-category {
        width: 100%;
        padding: 8px;
    }

    .button-group {
        margin-top: 20px;
    }

    @media (max-width: 768px) {
        .stat-number {
            font-size: 1.5em;
        }

        .hierarchy-parts .badge {
            display: block;
            margin-bottom: 5px;
            width: fit-content;
        }

        .category-table th:nth-child(3),
        .category-table td:nth-child(3) {
            display: none;
        }
    }
</style>

<script>
    function updateMappingStats() {
        var hierarchyCount = 0;
        var mappedCount = 0;

        document.querySelectorAll('.category-row').forEach(function(row) {
            var categoryName = row.dataset.category;
            var select = row.querySelector('select');

            // Compter les hiérarchies
            if (categoryName.indexOf(' > ') !== -1) {
                hierarchyCount++;
            }

            // Compter les mappages
            if (select.value !== '0') {
                mappedCount++;
            }
        });

        document.getElementById('hierarchy-count').textContent = hierarchyCount;
        document.getElementById('mapped-count').textContent = mappedCount;
    }

    function clearAllMappings() {
        if (confirm('{l s="Clear all mappings?" mod="pfproductimporter" js=1}')) {
        document.querySelectorAll('.select-category').forEach(function(select) {
            select.selectedIndex = 0;
        });
        updateMappingStats();
    }
    }

    function autoSuggestMappings() {
        // Logique d'auto-suggestion basée sur les noms
        document.querySelectorAll('.category-row').forEach(function(row) {
            var categoryName = row.dataset.category.toLowerCase();
            var select = row.querySelector('select');

            if (select.value === '0') {
                // Chercher une correspondance approximative
                for (var i = 1; i < select.options.length; i++) {
                    var optionText = select.options[i].text.toLowerCase();
                    if (categoryName.indexOf(optionText.trim()) !== -1 ||
                        optionText.indexOf(categoryName.split(' > ').pop().trim()) !== -1) {
                        select.selectedIndex = i;
                        break;
                    }
                }
            }
        });
        updateMappingStats();
    }

    // Initialiser les stats au chargement
    document.addEventListener('DOMContentLoaded', function() {
        updateMappingStats();

        // Ajouter les événements onChange
        document.querySelectorAll('.select-category').forEach(function(select) {
            select.addEventListener('change', updateMappingStats);
        });
    });
</script>