<!-- Container pour les notifications toast -->
<div class="toast-container" id="toast-container"></div>

<div class="form-section">
    <p>La synchronisation des stocks, du catalogue, des clients et du statut des commandes nécessite la mise en place d'une tâche CRON.</p>
    <p>Pour la mettre en place, voici la procédure :</p>
    <ol>
        <li>Copier l'URL suivante : <span><a href="/modules/pfproductimporter/cron_crontab.php?secure_key={$fields_value.PI_SOFTWAREID}" class="rouge" target="_blank">https://{Configuration::get('PS_SHOP_DOMAIN')}/modules/pfproductimporter/cron_crontab.php?secure_key={$fields_value.PI_SOFTWAREID}</a></span></li>
        <li>Coller l'URL dans votre système de tâches planifiées (CRON), elle doit être appelée via <b>cUrl</b> ou <b>wget</b> toutes les heures.</li>
    </ol>
    <p>Vous pouvez ouvrir cette URL dans un navigateur pour déclencher une mise à jour et visualiser le résultat.</p>
    <p>Si tout fonctionne bien, alors vous pouvez ajouter votre tache CRON sur le serveur qui va appeler cette URL, toutes les heures, tous les jours.</p>
    <p>Pour vous assurer que la tâche CRON s'exécute bien, vous avez les logs du module dans l'onglet Logs.</p>
    <p>Pour vérifier la bonne execution de la tache planifiée, ouvrez le log du jour et recherchez le terme "CRON" qui doit apparaitre chaque heure.</p>
    <hr />
    <!-- Activer la mise à jour périodique -->
    <div class="form-group">
        <label class="control-label col-lg-3">Activer la mise à jour périodique</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_CRON_TASK" id="PI_CRON_TASK_on" value="1"
                    {if $fields_value.PI_CRON_TASK}checked{/if}>
                <label for="PI_CRON_TASK_on">Oui</label>
                <input type="radio" name="PI_CRON_TASK" id="PI_CRON_TASK_off" value="0"
                    {if !$fields_value.PI_CRON_TASK}checked{/if}>
                <label for="PI_CRON_TASK_off">Non</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">
                Dernière mise à jour : <span id="datetime">
                    {Tools::displayDate(Configuration::get('PI_LAST_CRON'), true)}</span>
            </p>
        </div>
    </div>
    <!-- Lancer la tâche CRON manuellement -->
    <div class="form-group">
        <label class="control-label col-lg-3">Lancer la tâche CRON manuellement</label>
        <div class="col-lg-9">
            <a href="{$cron_url}" target="_blank" type="button" class="btn btn-primary" id="run-cron-task">Lancer</a>
            <p class="help-block">
                Vous pouvez lancer la tâche CRON manuellement pour mettre à jour les produits immédiatement.
            </p>
        </div>
    </div>
</div>


<script>
    document.getElementById('run-cron-task').addEventListener('click', function() {

        // Mettre à jour la date de la dernière exécution
        var lastUpdate = new Date().toLocaleDateString('fr-FR') + ' ' + new Date().toLocaleTimeString('fr-FR');
        var helpBlock = document.getElementById('datetime');
        helpBlock.textContent = lastUpdate;
    });

</script>
