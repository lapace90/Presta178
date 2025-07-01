<div class="form-section">
    <h3>Paramètres généraux</h3>
    
    <!-- URL du flux -->
    <div class="form-group">
        <label class="control-label col-lg-3">URL du flux</label>
        <div class="col-lg-9">
            <input type="text" name="SYNC_CSV_FEEDURL" value="{$fields_value.SYNC_CSV_FEEDURL}" class="lg" required />
        </div>
    </div>
    
    <!-- ID logiciel -->
    <div class="form-group">
        <label class="control-label col-lg-3">ID logiciel</label>
        <div class="col-lg-9">
            <input type="text" name="PI_SOFTWAREID" value="{$fields_value.PI_SOFTWAREID}" class="lg" required />
        </div>
    </div>
    
    <!-- Synchroniser les quantités depuis -->
    <div class="form-group">
        <label class="control-label col-lg-3">Synchroniser les quantités depuis</label>
        <div class="col-lg-9">
            <input type="text" name="SYNC_STOCK_PDV" value="{$fields_value.SYNC_STOCK_PDV}" />
            <p class="help-block">Laisser vide pour les quantités globales.</p>
        </div>
    </div>
    
    <!-- Activer la mise à jour périodique -->
    <div class="form-group">
        <label class="control-label col-lg-3">Activer la mise à jour périodique</label>
        <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="PI_CRON_TASK" id="PI_CRON_TASK_on" value="1" {if $fields_value.PI_CRON_TASK}checked{/if}>
                <label for="PI_CRON_TASK_on">Oui</label>
                <input type="radio" name="PI_CRON_TASK" id="PI_CRON_TASK_off" value="0" {if !$fields_value.PI_CRON_TASK}checked{/if}>
                <label for="PI_CRON_TASK_off">Non</label>
                <a class="slide-button btn"></a>
            </span>
            <p class="help-block">
                Dernière mise à jour : {Tools::displayDate(Configuration::get('PI_LAST_CRON'), null, true)}
            </p>
        </div>
    </div>
</div>