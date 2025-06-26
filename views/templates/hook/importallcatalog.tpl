{*
* 2025 - Ilaria (TGM)
*
* DISCLAIMER
*
* @license   https://www.tgm-commerce.fr/
*}

<!-- Bouton pour ouvrir la modale -->
<input type="button" id="openModalBtn" name="Submitimportprocess" class="button btn btn-primary"
    {if !$fields_value.PI_ALLOW_PRODUCTIMPORT}disabled="disabled" {/if}
    value="{l s='Start import process' mod='pfproductimporter'}" />

<!-- La modale -->
<div id="confirmModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>{l s='Confirm catalog import' mod='pfproductimporter'}</h2>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <p>{l s='First step completed. The product creation process will start and may take several minutes.' mod='pfproductimporter'}</p>
            <p><strong>{l s='Please do not interrupt the process once started.' mod='pfproductimporter'}</strong></p>
            <p>{l s='Do you want to continue?' mod='pfproductimporter'}</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-cancel modal-cancel">{l s='Cancel' mod='pfproductimporter'}</button>
            <button type="button" class="btn btn-primary" onclick="startDirectImport()">Import</button>
        </div>
    </div>
</div>

<!-- Zone de statut -->
<div class="import-status-container" style="text-align: center; margin: 20px; display: none;">
    <p class="import-status"></p>
    <div class="progress-bar-container" style="display: none;">
        <div class="progress-bar">
            <div class="progress-bar-fill"></div>
        </div>
        <span class="progress-text">0%</span>
    </div>
</div>

<script src="//code.jquery.com/jquery-2.1.4.min.js" type="text/javascript"></script>
<script type="text/javascript">
    // Gestion de la modale
    $(document).ready(function() {
        var modal = $('#confirmModal');
        var openBtn = $('#openModalBtn');
        var closeBtn = $('.modal-close');
        var cancelBtn = $('.modal-cancel');

        // Ouvrir la modale
        openBtn.click(function() {
            modal.fadeIn();
        });

        // Fermer la modale
        closeBtn.click(function() {
            modal.fadeOut();
        });

        cancelBtn.click(function() {
            modal.fadeOut();
        });

        // Fermer si on clique en dehors
        $(window).click(function(event) {
            if (event.target == modal[0]) {
                modal.fadeOut();
            }
        });
    });

    function startDirectImport() {
        $('#confirmModal').fadeOut();
        $('.import-status-container').show();
        $('.progress-bar-container').show();
        $('.import-status').text('Import en cours...');
        
        // Animation de la barre de progression
        var progress = 0;
        var progressInterval = setInterval(function() {
            progress += 2;
            if (progress <= 100) {
                $('.progress-bar-fill').css('width', progress + '%');
                $('.progress-text').text(progress + '%');
            }
        }, 100);

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { direct_import_now: 1 },
            success: function(response) {
                clearInterval(progressInterval);
                $('.progress-bar-fill').css('width', '100%');
                $('.progress-text').text('100%');
                $('.import-status').html('<strong style="color: green;">Import terminé avec succès!</strong>');
            },
            error: function() {
                clearInterval(progressInterval);
                $('.import-status').html('<strong style="color: red;">Erreur pendant l\'import</strong>');
            }
        });
    }
</script>

<style>
    /* Styles pour la modale */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .modal-content {
        background-color: #fff;
        padding: 0;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        max-width: 500px;
        width: 90%;
        position: relative;
    }

    .modal-header {
        padding: 20px;
        border-bottom: 1px solid #e5e5e5;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 20px;
        color: #333;
    }

    .modal-close {
        font-size: 28px;
        font-weight: bold;
        color: #aaa;
        cursor: pointer;
        line-height: 20px;
    }

    .modal-close:hover,
    .modal-close:focus {
        color: #000;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-body p {
        margin: 10px 0;
        color: #555;
    }

    .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #e5e5e5;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.3s;
    }

    .btn-primary {
        background-color: #007bff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0056b3;
    }

    .btn-cancel {
        background-color: #6c757d;
        color: white;
    }

    .btn-cancel:hover {
        background-color: #545b62;
    }

    /* Barre de progression */
    .progress-bar-container {
        margin-top: 20px;
    }

    .progress-bar {
        width: 100%;
        height: 20px;
        background-color: #f0f0f0;
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        background-color: #28a745;
        width: 0%;
        transition: width 0.3s ease;
    }

    .progress-text {
        display: block;
        margin-top: 10px;
        font-weight: bold;
    }
</style>