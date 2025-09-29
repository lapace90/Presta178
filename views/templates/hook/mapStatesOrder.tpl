{*
* 2025 - TGM Ilaria
* Template personnalisé pour les associations des états des commandes
*}
<div class="import-form">
    <h2 class="form-title">
        Association des états des commandes
    </h2>

    <p>Associez les états des commandes Rezomatic aux états des commandes de PrestaShop. Cela permet de garantir que les commandes importées reflètent correctement leur statut dans votre boutique.</p>
    
    <div class="alert alert-info">
        <strong>Note :</strong> Laissez "-- Aucun changement --" si vous ne souhaitez pas modifier l'état PrestaShop pour cet état Rezomatic.
    </div>

    {* Messages d'état *}
    {if isset($state_mapping_success) && $state_mapping_success}
        <div class="alert alert-success">
            <i class="icon-check"></i> Les associations des états des commandes ont été enregistrées avec succès.
        </div>
    {/if}
    {if isset($state_mapping_error) && $state_mapping_error}
        <div class="alert alert-danger">
            <i class="icon-remove"></i> Une erreur est survenue lors de l'enregistrement des associations des états des commandes. Veuillez réessayer.
        </div>
    {/if}

    {* <form method="post" action="{$smarty.server.REQUEST_URI}" id="state-order-form"> *}
        <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}" />
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="thead-primary">
                    <tr>
                        <th style="width: 40%;">
                            <i class="icon-reorder"></i> État Rezomatic
                        </th>
                        <th style="width: 60%;">
                            <i class="icon-shopping-cart"></i> État PrestaShop associé
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {if isset($source_states) && $source_states}
                        {foreach from=$source_states item=state}
                            <tr>
                                <td class="state-rezomatic">
                                    <strong>{$state|escape:'htmlall':'UTF-8'}</strong>
                                </td>
                                <td>
                                    <select name="state_mapping[{$state|escape:'htmlall':'UTF-8'}]" class="form-control">
                                        <option value="0">-- Aucun changement --</option>
                                        {if isset($prestashop_states) && $prestashop_states}
                                            {foreach from=$prestashop_states item=ps_state}
                                                <option value="{$ps_state.id_state|intval}"
                                                    {if isset($fields_value.state_mapping[$state]) && $fields_value.state_mapping[$state] == $ps_state.id_state}selected="selected"{/if}>
                                                    {$ps_state.name|escape:'htmlall':'UTF-8'}
                                                </option>
                                            {/foreach}
                                        {else}
                                            <option value="0" disabled>Aucun état disponible</option>
                                        {/if}
                                    </select>
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="2" class="text-center text-muted">
                                <i class="icon-warning-sign"></i>
                                Aucun état source disponible
                            </td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>

        <div class="form-actions text-right">
            <button type="button" class="btn btn-default" onclick="resetMappings()">
                <i class="icon-eraser"></i>
                Réinitialiser
            </button>
            <button type="submit" name="submitStateMapping" class="btn btn-primary">
                <i class="icon-save" style="padding-right: 4px;"></i>
                Enregistrer les associations
            </button>
        </div>
    {* </form> *}
</div>

<script type="text/javascript">
function resetMappings() {
    if (confirm('Êtes-vous sûr de vouloir réinitialiser toutes les associations ?')) {
        var selects = document.querySelectorAll('#state-order-form select');
        selects.forEach(function(select) {
            select.selectedIndex = 0;
        });
    }
}

// Validation côté client
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('state-order-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            var selects = form.querySelectorAll('select');
            var hasMapping = false;
            
            selects.forEach(function(select) {
                if (select.value !== '0') {
                    hasMapping = true;
                }
            });
            
            if (!hasMapping) {
                if (!confirm('Aucune association n\'a été définie. Continuer ?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
});
</script>

<style>
.state-rezomatic {
    background-color: #f8f9fa;
    font-family: 'Courier New', monospace;
}

.thead-primary {
    background-color: #2eacce;
    color: #FFF;
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

.form-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}
</style>