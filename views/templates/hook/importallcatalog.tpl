<!-- Bouton pour ouvrir la modale -->
<input type="button" id="openModalBtn" name="Submitimportprocess" class="button btn btn-primary"
    {if !$fields_value.PI_ALLOW_PRODUCTIMPORT}disabled="disabled" {/if} value="Démarrer le processus d'import" />
<p class="help-block">
    Déclencher manuellement l'import de produits. Cela peut prendre quelques minutes.
</p>

<!-- La modale -->
<div id="confirmModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirmer l'import du catalogue</h2>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <p>Première étape terminée. Le processus de création des produits va commencer et peut prendre plusieurs minutes.</p>
            <p><strong>Veuillez ne pas interrompre le processus une fois démarré.</strong></p>
            <p>Voulez-vous continuer ?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-cancel modal-cancel">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="startDirectImport()">Importer</button>
        </div>
    </div>
</div>

<!-- Barre de progression -->
<div class="row" id="import-row" style="display:none;margin-top:10px;">
    <div class="col-lg-12">
        <div class="progress">
            <div id="import-bar" class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width:0%">0%</div>
        </div>
        <small id="import-text" class="text-muted">En attente…</small>
        <div id="import-done" style="display:none;margin-top:8px;">
            <button id="import-finish" class="btn btn-success" onclick="location.reload();">Terminer</button>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var modal = $('#confirmModal');
        var openBtn = $('#openModalBtn');
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
