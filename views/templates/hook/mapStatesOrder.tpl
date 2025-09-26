{*
* 2025 - TGM Ilaria
* Template personnalisé pour les associations des états des commandes
*}
<div class="import-form">
    <h2 class="form-title">
        Association des états des commandes
    </h2>

    <form action="" method="post" id="state-order-form">
        <input type="hidden" size="100" name="feedurl" value="{$feedurl|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" name="feedid" value="{$feedid|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}" />
        <div role="tabpanel" class="tab-pane" id="states-order">

            <p>Associez les états des commandes de votre catalogue source aux états des commandes de PrestaShop. Cela
                permet de
                garantir que les commandes importées reflètent correctement leur statut dans votre boutique.</p>
            <form method="post" action="{$_SERVER['REQUEST_URI']}">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>État source</th>
                            <th>État PrestaShop</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$source_states item=state}
                            <tr>
                                <td>{$state}</td>
                                <td>
                                    <select name="state_mapping[{$state}]" class="form-control">
                                        <option value="0">-- Sélectionner un état --</option>
                                        {foreach from=$prestashop_states item=ps_state}
                                            <option value="{$ps_state.id_state}"
                                                {if isset($fields_value.state_mapping[$state]) && $fields_value.state_mapping[$state] == $ps_state.id_state}selected{/if}>
                                                {$ps_state.name}</option>
                                        {/foreach}
                                    </select>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
                <button type="submit" name="submitStateMapping" class="btn btn-primary">Enregistrer les
                    associations</button>
            </form>
            {if isset($state_mapping_success) && $state_mapping_success}
                <div class="alert alert-success" style="margin-top:10px;">
                    Les associations des états des commandes ont été enregistrées avec succès.
                </div>
            {/if}
            {if isset($state_mapping_error) && $state_mapping_error}
                <div class="alert alert-danger" style="margin-top:10px;">
                    Une erreur est survenue lors de l'enregistrement des associations des états des commandes. Veuillez
                    réessayer.
                </div>
            {/if}
        </div>
    </form>
</div>
<script type="text/javascript">
    // Vous pouvez ajouter des scripts JavaScript ici si nécessaire
</script>