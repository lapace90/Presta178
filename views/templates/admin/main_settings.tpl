{*
* 2025 - TGM Ilaria
* Template personnalisé pour les réglages d'import/export
*}

<div class="custom-import-export-settings panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i> Paramètres
    </div>

    {if !isset($form_action)}
        {assign var="form_action" value=$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}
    {/if}

    {if !isset($active_tab)}
        {assign var="active_tab" value="general"}
    {/if}
    {* <pre>
    form_action: {$form_action|@print_r}
    token: {$token|@print_r}
    raw_products_arr: {$raw_products_arr|@print_r}
    final_products_arr: {$final_products_arr|@print_r}
    </pre> *}

    <form action="{$form_action}" method="post" class="form-horizontal">
        <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">

        <!-- Onglets -->
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="{if $active_tab == 'general'}active{/if}">
                <a href="#general_tab" aria-controls="general" role="tab" data-toggle="tab">
                    Général
                </a>
            </li>
            <li role="presentation" class="{if $active_tab == 'mapping'}active{/if}">
                <a href="#mapping_tab" aria-controls="mapping" role="tab" data-toggle="tab">
                    Correspondances
                </a>
            </li>
            <li role="presentation" class="{if $active_tab == 'catalog'}active{/if}">
                <a href="#catalog_tab" aria-controls="catalog" role="tab" data-toggle="tab">
                    Catalogue
                </a>
            </li>
            <li role="presentation" class="{if $active_tab == 'stock'}active{/if}">
                <a href="#stock_tab" aria-controls="stock" role="tab" data-toggle="tab">
                    Stock
                </a>
            </li>
            <li role="presentation" class="{if $active_tab == 'customer'}active{/if}">
                <a href="#customer_tab" aria-controls="customer" role="tab" data-toggle="tab">
                    Clients
                </a>
            </li>
            <li role="presentation" class="{if $active_tab == 'order'}active{/if}">
                <a href="#order_tab" aria-controls="order" role="tab" data-toggle="tab">
                    Commandes
                </a>
            </li>
            <li role="presentation" class="{if $active_tab == 'cron'}active{/if}">
                <a href="#cron_tab" aria-controls="stock" role="tab" data-toggle="tab">
                    CRON
                </a>
            </li>
            <li role="presentation" class="{if $active_tab == 'logs'}active{/if}">
                <a href="#logs_tab" aria-controls="logs" role="tab" data-toggle="tab">
                    Logs
                </a>
            </li>
        </ul>

        <!-- Contenu des onglets -->
        <div class="tab-content">
            <!-- Onglet General -->
            <div role="tabpanel" class="tab-pane {if $active_tab == 'general'}active{/if}" id="general_tab">
                {include file="module:pfproductimporter/views/templates/admin/general_settings.tpl"}
                <div class="panel-footer">
                    <button type="submit" name="SubmitSaveMainSettings" class="btn btn-default pull-right">
                        <i class="process-icon-save"></i> Sauvegarder les paramètres généraux
                    </button>
                </div>
            </div>

            <!-- Onglet Catalog -->
            <div role="tabpanel" class="tab-pane {if $active_tab == 'catalog'}active{/if}" id="catalog_tab">
                {include file="module:pfproductimporter/views/templates/admin/catalog_settings.tpl"}
                <div class="panel-footer">
                    <button type="submit" name="SubmitSaveMainSettings" class="btn btn-default pull-right">
                        <i class="process-icon-save"></i> Sauvegarder les paramètres
                    </button>
                </div>
            </div>

            <!-- Onglet Stock -->
            <div role="tabpanel" class="tab-pane {if $active_tab == 'stock'}active{/if}" id="stock_tab">
                {include file="module:pfproductimporter/views/templates/admin/stock_settings.tpl"}
                <div class="panel-footer">
                    <button type="submit" name="SubmitSaveMainSettings" class="btn btn-default pull-right">
                        <i class="process-icon-save"></i> Sauvegarder les paramètres
                    </button>
                </div>
            </div>

            <!-- Onglet Mapping -->
            <div role="tabpanel" class="tab-pane {if $active_tab == 'mapping'}active{/if}" id="mapping_tab">
                <!-- Sous-onglets -->
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation" class="active">
                        <a href="#fields_mapping" aria-controls="fields" role="tab" data-toggle="tab">
                            Champs produits
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#category_mapping" aria-controls="category" role="tab" data-toggle="tab">
                            Catégories
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#states_mapping" aria-controls="states-order" role="tab" data-toggle="tab">
                            états des commandes
                        </a>
                    </li>

                    <li role="presentation" class="{if $active_tab == 'payment'}active{/if}">
                        <a href="#payment_tab" aria-controls="payment" role="tab" data-toggle="tab">
                            Moyens de Paiements
                        </a>
                    </li>

                </ul>

                <!-- Contenu des sous-onglets -->
                <div class="tab-content">
                    <!-- Sous-onglet Fields Mapping -->
                    <div role="tabpanel" class="tab-pane active" id="fields_mapping">
                        <div class="form-section">
                            {if isset($raw_products_arr) && is_array($raw_products_arr)}
                                {include file="module:pfproductimporter/views/templates/admin/mapping_fields.tpl"}
                            {else}
                                <p>Aucun champ disponible.</p>
                            {/if}
                        </div>
                    </div>

                    <!-- Sous-onglet Category Mapping -->
                    <div role="tabpanel" class="tab-pane" id="category_mapping">
                        <div class="form-section">
                            {if isset($final_products_arr) && is_array($final_products_arr)}
                                {include file="module:pfproductimporter/views/templates/admin/mapping_category.tpl"}
                            {else}
                                <p>Aucune catégorie disponible.</p>
                            {/if}
                        </div>
                    </div>

                    <!-- Sous-onglet States Mapping -->
                    <div role="tabpanel" class="tab-pane" id="states_mapping">
                        <div class="form-section">
                            {include file="module:pfproductimporter/views/templates/admin/mapping_states.tpl"}
                        </div>
                    </div>

                    <!-- Sous-onglet Payment -->
                    <div role="tabpanel" class="tab-pane {if $active_tab == 'payment'}active{/if}" id="payment_tab">
                        <div class="form-section">
                            {include file="module:pfproductimporter/views/templates/admin/mapping_payment.tpl"}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet Customers -->
            <div role="tabpanel" class="tab-pane {if $active_tab == 'customer'}active{/if}" id="customer_tab">
                {include file="module:pfproductimporter/views/templates/admin/customer_settings.tpl"}
                <div class="panel-footer">
                    <button type="submit" name="SubmitSaveMainSettings" class="btn btn-default pull-right">
                        <i class="process-icon-save"></i> Sauvegarder les paramètres
                    </button>
                </div>
            </div>

            <!-- Onglet Orders -->
            <div role="tabpanel" class="tab-pane {if $active_tab == 'order'}active{/if}" id="order_tab">
                {include file="module:pfproductimporter/views/templates/admin/order_settings.tpl"}
                <div class="panel-footer">
                    <button type="submit" name="SubmitExportorder" class="btn btn-default pull-right">
                        <i class="process-icon-save"></i> Sauvegarder les paramètres
                    </button>
                </div>
            </div>

            <!-- Onglet CRON -->
            <div role="tabpanel" class="tab-pane {if $active_tab == 'cron'}active{/if}" id="cron_tab">
                <div class="form-section">
                    {include file="module:pfproductimporter/views/templates/admin/cron.tpl"}
                    <div class="panel-footer">
                        <button type="submit" name="SubmitSaveMainSettings" class="btn btn-default pull-right">
                            <i class="process-icon-save"></i> Sauvegarder les paramètres
                        </button>
                    </div>
                </div>
            </div>

            <!-- Onglet Logs -->
            <div role="tabpanel" class="tab-pane {if $active_tab == 'logs'}active{/if}" id="logs_tab">
                <div class="form-section">
                    {include file="module:pfproductimporter/views/templates/admin/logs.tpl"}
                </div>
            </div>
    </form>
</div>

<style>
    .custom-import-export-settings {
        max-width: 1200px;
        margin: 20px auto;
        font-family: Arial, sans-serif;
        padding: 20px;
        background-color: #fff;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    .custom-import-export-settings .panel-heading {
        font-size: 1.5em;
        color: #333;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }

    .custom-import-export-settings .nav-tabs {
        border-bottom: 2px solid #2eacce;
    }

    .custom-import-export-settings .nav-tabs>li.active>a,
    .custom-import-export-settings .nav-tabs>li.active>a:hover,
    .custom-import-export-settings .nav-tabs>li.active>a:focus {
        background-color: #2eacce;
        color: white;
        border: 1px solid #2eacce;
    }

    .custom-import-export-settings .tab-content {
        padding: 20px 0;
    }

    .custom-import-export-settings .form-section {
        margin: 20px 0;
        padding: 15px;
        background-color: #f9f9f9;
        border-radius: 4px;
    }

    .custom-import-export-settings .form-section h3 {
        margin-top: 0;
        color: #2eacce;
        background-color: white !important;
        padding: 10px;
    }

    .custom-import-export-settings .panel-footer {
        background-color: #f5f5f5;
        border-top: 1px solid #ddd;
        margin-top: 20px;
    }
</style>
