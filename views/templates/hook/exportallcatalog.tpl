<!-- Bouton pour ouvrir la modale d'export -->
<input type="button" id="openExportModalBtn" name="exportallproduct"
    class="button btn btn-primary{if !$fields_value.PI_ALLOW_PRODUCTEXPORT} disabled{/if}"
    {if !$fields_value.PI_ALLOW_PRODUCTEXPORT}disabled="disabled" style="opacity: 0.5; cursor: not-allowed;" {/if}
    value="Démarrer le processus d'export" />
<p class="help-block">
    Déclencher manuellement l'export de produits vers Rezomatic.
</p>

<!-- Modale de confirmation d'export -->
<div id="confirmExportModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirmer l'export du catalogue</h2>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <p>Le processus d'export des produits vers Rezomatic va commencer et peut prendre plusieurs minutes.</p>
            <p><strong>Veuillez ne pas interrompre le processus une fois démarré.</strong></p>
            <p>Voulez-vous continuer ?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-cancel modal-cancel">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="startDirectExport()">Exporter</button>
        </div>
    </div>
</div>

<!-- Barre de progression -->
<div class="row" id="export-progress" style="display:none; margin-top:10px;">
    <div class="col-lg-12">
        <div class="progress">
            <div id="export-progressbar" class="progress-bar progress-bar-info" role="progressbar" style="width:0%">0%</div>
        </div>
        <small id="export-text" class="text-muted">En attente…</small>
        <div id="export-done" style="display:none;margin-top:8px;">
            <button id="export-finish" class="btn btn-success" onclick="location.reload();">Terminer</button>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var modal = $('#confirmExportModal');
        var openBtn = $('#openExportModalBtn');
        var closeBtn = $('.modal-close');
        var cancelBtn = $('.modal-cancel');

        // Ouvrir la modale
        openBtn.click(function() {
            if (!$(this).is(':disabled')) {
                modal.fadeIn();
            }
        });

        // Fermer la modale
        closeBtn.click(function() {
            modal.fadeOut();
        });
        cancelBtn.click(function() {
            modal.fadeOut();
        });
        $(window).click(function(event) {
            if (event.target == modal[0]) {
                modal.fadeOut();
            }
        });
    });

</script>
