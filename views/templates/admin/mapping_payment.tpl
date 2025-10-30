<div class="form-section">
    <!-- Formulaire d'ajout -->
    <div class="well">
        <div class="row">
            <!-- Colonne PrestaShop -->
            <div class="col-lg-5">
                <label>Règlement PrestaShop</label>
                <select id="prestashop_method_select" class="form-control">
                    <option value="">-- Sélectionner --</option>

                    {if isset($active_payment_modules) && count($active_payment_modules) > 0}
                        <optgroup label="Modules installés sur votre boutique">
                            {foreach from=$active_payment_modules item=module}
                                <option value="{$module.technical_name}">
                                    {$module.display_name}
                                </option>
                            {/foreach}
                        </optgroup>
                    {/if}

                    <optgroup label="Autre">
                        <option value="__AUTRE__">+ Saisie manuelle...</option>
                    </optgroup>
                </select>

                <!-- Input manuel (caché par défaut) -->
                <input type="text" id="prestashop_method_manual" class="form-control"
                    placeholder="Saisir le nom du module"
                    style="margin-top: 10px; display: none; text-transform: uppercase;" />
            </div>

            <!-- Flèche -->
            <div class="col-lg-1 text-center" style="padding-top: 30px;">
                <i class="icon-arrow-right" style="font-size: 24px;"></i>
            </div>

            <!-- Colonne Rezomatic -->
            <div class="col-lg-5">
                <label>Règlement Rezomatic</label>
                <select id="rezomatic_mode" class="form-control">
                    <option value="">-- Sélectionner --</option>
                    <optgroup label="Modes standards">
                        <option value="ESP">ESP - Espèces</option>
                        <option value="CHQ">CHQ - Chèque</option>
                        <option value="CB">CB - Carte Bancaire</option>
                        <option value="AVOIR">AVOIR - Avoir</option>
                        <option value="DIFF">DIFF - Différé</option>
                    </optgroup>
                    <optgroup label="Modes paramétrables (à configurer dans Rezomatic)">
                        <option value="RG1">RG1 - Règlement paramétrable 1</option>
                        <option value="RG2">RG2 - Règlement paramétrable 2</option>
                        <option value="RG3">RG3 - Règlement paramétrable 3</option>
                        <option value="RG4">RG4 - Règlement paramétrable 4</option>
                        <option value="RG5">RG5 - Règlement paramétrable 5</option>
                        <option value="RG6">RG6 - Règlement paramétrable 6</option>
                        <option value="RG7">RG7 - Règlement paramétrable 7</option>
                        <option value="RG8">RG8 - Règlement paramétrable 8</option>
                        <option value="RG9">RG9 - Règlement paramétrable 9</option>
                        <option value="RG10">RG10 - Règlement paramétrable 10</option>
                    </optgroup>
                </select>
            </div>

            <!-- Bouton -->
            <div class="col-lg-1" style="padding-top: 25px;">
                <button type="button" id="btn_add_mapping" class="btn btn-success btn-block">
                    <i class="icon-plus"></i> Ajouter
                </button>
            </div>
        </div>
    </div>

    <!-- Liste des mappings existants -->
    <h4>Correspondances configurées</h4>
    <table class="table table-striped table-bordered" id="mappings_table">
        <thead>
            <tr>
                <th style="width: 45%">Méthode PrestaShop</th>
                <th style="width: 45%">Mode Rezomatic</th>
                <th style="width: 10%" class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody id="mappings_list">
            {if isset($payment_mappings) && count($payment_mappings) > 0}
                {foreach from=$payment_mappings key=idx item=mapping}
                    <tr data-index="{$idx}">
                        <td><strong>{if isset($mapping.display_name)}{$mapping.display_name}{else}{$mapping.prestashop}{/if}</strong>
                        </td>
                        <td><span class="badge badge-info">{$mapping.rezomatic}</span></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm btn-delete" data-index="{$idx}">
                                <i class="icon-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <input type="hidden" name="payment_mappings[{$idx}][prestashop]" value="{$mapping.prestashop}" />
                    {if isset($mapping.display_name)}
                        <input type="hidden" name="payment_mappings[{$idx}][display_name]" value="{$mapping.display_name}" />
                    {/if}
                    <input type="hidden" name="payment_mappings[{$idx}][rezomatic]" value="{$mapping.rezomatic}" />
                {/foreach}
            {else}
                <tr id="no_mappings">
                    <td colspan="3" class="text-center text-muted">
                        <i class="icon-info-sign"></i> Aucune correspondance configurée
                    </td>
                </tr>
            {/if}
        </tbody>
    </table>
    <div class="button-group text-right">
        <button type="submit" name="SubmitSavePaymentMappings" class="btn btn-primary pull-right">
            Sauvegarder
        </button>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var mappingIndex = {if isset($payment_mappings)}{count($payment_mappings)}{else}0{/if};

        // Afficher/masquer l'input manuel selon la sélection
        $('#prestashop_method_select').change(function() {
            if ($(this).val() === '__AUTRE__') {
                $('#prestashop_method_manual').slideDown(200).focus();
            } else {
                $('#prestashop_method_manual').slideUp(200).val('');
            }
        });

        // Ajouter un mapping
        $('#btn_add_mapping').click(function() {
            var prestashop = '';
            var prestashopDisplay = ''; 

            // Si "AUTRE" est sélectionné, prendre l'input manuel
            if ($('#prestashop_method_select').val() === '__AUTRE__') {
                prestashop = $('#prestashop_method_manual').val().trim().toUpperCase();
                prestashopDisplay = prestashop; 
            } else {
                prestashop = $('#prestashop_method_select').val();
                prestashopDisplay = $('#prestashop_method_select option:selected')
                    .text().trim(); 
            }

            var rezomatic = $('#rezomatic_mode').val();

            // Validation
            if (!prestashop) {
                alert('Veuillez sélectionner ou saisir une méthode PrestaShop');
                return;
            }

            if (!rezomatic) {
                alert('Veuillez sélectionner un mode Rezomatic');
                return;
            }

            // Vérifier si existe déjà
            var exists = false;
            $('input[name^="payment_mappings"][name$="[prestashop]"]').each(function() {
                if ($(this).val().toUpperCase() === prestashop.toUpperCase()) {
                    exists = true;
                    return false;
                }
            });

            if (exists) {
                alert('Cette méthode PrestaShop est déjà mappée');
                return;
            }

            // Supprimer la ligne "aucune correspondance"
            $('#no_mappings').remove();

            // Ajouter la ligne - MODIFIÉ pour afficher prestashopDisplay
            var row = '<tr data-index="' + mappingIndex + '">' +
                '<td><strong>' + prestashopDisplay + '</strong></td>' + // CHANGÉ ICI
                '<td><span class="badge badge-info">' + rezomatic + '</span></td>' +
                '<td class="text-center">' +
                '<button type="button" class="btn btn-danger btn-sm btn-delete" data-index="' +
                mappingIndex + '">' +
                '<i class="icon-trash"></i>' +
                '</button>' +
                '</td>' +
                '</tr>';

            var hidden = '<input type="hidden" name="payment_mappings[' + mappingIndex +
                '][prestashop]" value="' + prestashop + '" />' +
                '<input type="hidden" name="payment_mappings[' + mappingIndex +
                '][display_name]" value="' + prestashopDisplay + '" />' + // NOUVEAU
                '<input type="hidden" name="payment_mappings[' + mappingIndex +
                '][rezomatic]" value="' + rezomatic + '" />';

            $('#mappings_list').append(row + hidden);

            // Reset
            $('#prestashop_method_select').val('');
            $('#prestashop_method_manual').val('').hide();
            $('#rezomatic_mode').val('');

            // Message de confirmation
            showSuccessMessage('Correspondance ajoutée');

            mappingIndex++;
        });

        // Supprimer un mapping
        $(document).on('click', '.btn-delete', function() {
            if (!confirm('Supprimer cette correspondance ?')) {
                return;
            }

            var index = $(this).data('index');
            $('tr[data-index="' + index + '"]').remove();
            $('input[name="payment_mappings[' + index + '][prestashop]"]').remove();
            $('input[name="payment_mappings[' + index + '][rezomatic]"]').remove();

            // Si plus aucune ligne
            if ($('#mappings_list tr').length === 0) {
                $('#mappings_list').html(
                    '<tr id="no_mappings"><td colspan="3" class="text-center text-muted"><i class="icon-info-sign"></i> Aucune correspondance configurée</td></tr>'
                );
            }

            showSuccessMessage('Correspondance supprimée');
        });

        // Message de succès
        function showSuccessMessage(msg) {
            var div = $(
                '<div class="alert alert-success" style="position: fixed; top: 100px; right: 20px; z-index: 9999;">' +
                '<i class="icon-ok"></i> ' + msg +
                '</div>');
            $('body').append(div);
            setTimeout(function() {
                div.fadeOut(function() { $(this).remove(); });
            }, 2000);
        }
    });
</script>

<style>
    .badge-info {
        background-color: #3498db;
        font-size: 13px;
        padding: 5px 10px;
    }
</style>
