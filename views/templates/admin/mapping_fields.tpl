{*
*
*  PrestaShop Product Feed Importer
* 2025 - TGM Ilaria
* Template personnalisé pour le formulaire de mappage des champs
*
*}

<div class="import-form">
    <form method="post">
        <input type="hidden" size="100" name="vcfeedurl" value="{$feedurl|escape:'htmlall':'UTF-8'}" />

        <div class="note">
            Note : pour les champs "taille" et "couleur" vous devez choisir quel groupe d'attributs associer (utilisé
                pour les déclinaisons)
            </div>

            <table class="category-table table striped">
                <thead class="thead-primary">
                    <tr>
                        <th width="50%">Champs Rezomatic</th>
                        <th width="50%">Champs Prestashop</th>
                    </tr>
                </thead>
                <tbody>
                    {assign var=counter value=0}


                {foreach from=$raw_products_arr item=val key=key name=products}


                    {if in_array($key, ['codeArt', 'taille', 'couleur', 'dArr', 'description'])}


                        {assign var=counter value=$counter+1}
                            <tr>
                                <td>
                                    {$val|escape:'htmlall':'UTF-8'}
                                    <input type="hidden" name="fld_map[]" value="{$key|escape:'htmlall':'UTF-8'}" />
                                </td>
                                <td>


                        {if $key == 'taille' || $key == 'couleur'}
                                        <select class="select-category" name="sel_{$counter}">


                            {foreach from=$attrgrp key=pr item=vr}


                                {assign var='row' value=Vccsv::getfields($key)}


                                {assign var='sel' value=''}


                                {if $row && $row.system_field == "{$key}_{$pr}"}


                                    {assign var='sel' value='selected=selected'}


                                {/if}
                                                <option value="{$key}_{$pr|escape:'htmlall':'UTF-8'}" {$sel|escape:'htmlall':'UTF-8'}>
                                                    Attribut : {$vr|escape:'htmlall':'UTF-8'}</option>


                            {/foreach}
                                        </select>


                        {elseif $key == 'codeArt'}
                                        <select class="select-category" name="sel_{$counter}">


                            {assign var='row' value=Vccsv::getfields($key)}
                                            <option value="reference" 
                            {if $row && $row.system_field == 'reference'}selected 
                            {/if}>
                                                référence</option>
                                            <option value="ean13" 
                            {if $row && $row.system_field == 'ean13'}selected 
                            {/if}>ean13</option>
                                            <option value="upc" 
                            {if $row && $row.system_field == 'upc'}selected 
                            {/if}>upc</option>
                                        </select>


                        {elseif $key == 'dArr'}


                            {assign var='row' value=Vccsv::getfields($key)}
                                        <select class="select-category" name="sel_{$counter}">
                                            <option value="ignore_field" 
                            {if $row && $row.system_field == 'ignore_field'}selected 
                            {/if}>
                                                Ignorer ce champ</option>
                                            <option value="available_date" 
                            {if $row && $row.system_field == 'available_date'}selected

                            {/if}>
                                                Date de disponibilité</option>
                                        </select>


                        {elseif $key == 'description'}


                            {assign var='row' value=Vccsv::getfields($key)}
                                        <select class="select-category" name="sel_{$counter}">
                                            <option value="ignore_field" 
                            {if $row && $row.system_field == 'ignore_field'}selected 
                            {/if}>
                                                Ignorer ce champ</option>
                                            <option value="description" 
                            {if $row && $row.system_field == 'description'}selected 
                            {/if}>
                                                Description</option>
                                            <option value="description_short"

                            {if $row && $row.system_field == 'description_short'}selected 
                            {/if}>
                                                Description courte</option>
                                        </select>


                        {/if}
                                </td>
                            </tr>


                    {/if}


                {/foreach}
                </tbody>
            </table>

            <div class="button-group text-right">
                <input type="submit" name="SubmitSaveFields" class="btn btn-primary" value="Sauvegarder" />
            </div>
            <hr>
        </form>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#mapping-fields-form');

            if (form) {
                form.addEventListener('submit', function(event) {
                        event.preventDefault();

                        const formData = new FormData(this);
                        formData.append('ajax', '1');

                        const submitBtn = this.querySelector('input[type="submit"]');
                        const originalText = submitBtn.value;
                        submitBtn.value = 'Sauvegarde...';
                        submitBtn.disabled = true;

                        fetch('{$smarty.server.REQUEST_URI|escape:"javascript":"UTF-8"}', {
                        method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                    })
                    .then(response => response.json())
                    .then(data => {
                        submitBtn.value = originalText;
                        submitBtn.disabled = false;

                        if (data.success) {
                            showSuccessMessage('Catégories associées avec succès !');
                        } else {
                            showErrorMessage(data.message || 'Une erreur est survenue');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        submitBtn.value = originalText;
                        submitBtn.disabled = false;
                        showErrorMessage('Erreur de connexion');
                    });
            });
        }
        });

        function showSuccessMessage(message) {
            let messageDiv = document.querySelector('.ajax-message');
            if (!messageDiv) {
                messageDiv = document.createElement('div');
                messageDiv.className = 'ajax-message';
                document.querySelector('.import-form').insertBefore(messageDiv, document.querySelector('.import-form')
                    .firstChild);
            }
            messageDiv.innerHTML = '<div class="alert alert-success">' + message + '</div>';

            setTimeout(() => {
                messageDiv.innerHTML = '';
            }, 3000);
        }

        function showErrorMessage(message) {
            let messageDiv = document.querySelector('.ajax-message');
            if (!messageDiv) {
                messageDiv = document.createElement('div');
                messageDiv.className = 'ajax-message';
                document.querySelector('.import-form').insertBefore(messageDiv, document.querySelector('.import-form')
                    .firstChild);
            }
            messageDiv.innerHTML = '<div class="alert alert-danger">' + message + '</div>';
        }
    </script>

    <style>
        .note {
            text-align: center;
            color: #666;
            margin: 15px;
        }

        .button-group {
            text-align: center;
        }
    </style>
