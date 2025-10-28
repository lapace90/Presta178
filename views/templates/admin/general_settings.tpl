<!-- Container pour les notifications toast -->
<div class="toast-container" id="toast-container"></div>

<!-- Formulaire de configuration générale pour le module PFProductImporter -->
<div class="form-section">
    <h3>Paramètres généraux</h3>
    <p>Merci de contacter TGMultimedia au 04 92 09 02 03 pour obtenir les informations nécessaires à la configuration du module.</p><br />
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
</div>
