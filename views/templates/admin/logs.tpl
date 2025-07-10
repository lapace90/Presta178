<div class="panel">
    <div class="panel-heading">
        <i class="icon-file-text"></i>
        Logs Rezomatic
    </div>

    <div class="panel-body">
        <!-- Log du jour -->
        {if $logs_today_exists}
            <div class="alert alert-success flex-between">
                <p><strong>Log du jour ({$logs_today_date_formatted})</strong> - Taille : {$logs_today_size} KB</p>
                <a href="{$logs_today_url}" target="_blank" class="btn btn-primary">
                    Voir le log du jour
                </a>
            </div>
        {else}
            <div class="alert alert-warning">
                Aucun log disponible pour aujourd'hui. Le fichier sera créé lors de la prochaine tâche cron.
                </div>
            {/if}

            <!-- Recherche par date -->
            <div class="search-section">
                <h4><i class="icon-search"></i> Rechercher des logs</h4>
                <form method="post" class="search-form">
                    <input type="hidden" name="configure" value="{$smarty.get.configure}">
                    <input type="hidden" name="active_tab" value="logs">
                    <input type="hidden" name="token" value="{$token}">

                    <div class="search-row">
                        <div class="search-field">
                            <label for="search_month">Mois :</label>
                            <select name="search_month" id="search_month" class="form-control">
                                <option value="">Tous les mois</option>


                {foreach from=$months_names item=month key=key}
                                    <option value="{$key}" 
                    {if $search_month == $key}selected 
                    {/if}>{$month}</option>


                {/foreach}
                            </select>
                        </div>

                        <div class="search-field">
                            <label for="search_year">Année :</label>
                            <select name="search_year" id="search_year" class="form-control">
                                <option value="">Toutes les années</option>


                {for $year=2024 to $smarty.now|date_format:'%Y'}
                                    <option value="{$year}" 
                    {if $search_year == $year}selected 
                    {/if}>{$year}</option>


                {/for}
                            </select>
                        </div>

                        <div class="search-buttons">
                            <button type="submit" class="btn btn-info">
                                <i class="icon-search"></i> Rechercher
                            </button>


                {if $search_month || $search_year}
                                <button type="submit" name="clear_filter" value="1" class="btn btn-default">
                                    <i class="icon-remove"></i> Effacer
                                </button>

                {/if}
                        </div>
                    </div>
                </form>
            </div>

            <!-- Logs précédents -->

                {if $available_logs}
                <h4>
                    Logs précédents

                    {if $search_month || $search_year}
                        <small>
                            - Filtré par

                        {if $search_month}{$months_names[$search_month]}



                        {/if} {$search_year}
                            ({$total_logs_found} résultat



                        {if $total_logs_found > 1}s

                        {/if})
                        </small>

                    {else}
                        <small>({$total_logs_found} fichier


                        {if $total_logs_found > 1}s


                        {/if} au total)</small>


                    {/if}
                </h4>

                <div class="logs-table-container">
                    <table class="table table-striped logs-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Taille</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>


                    {foreach $available_logs as $log}
                                <tr>
                                    <td class="date-cell">{$log.date_formatted}</td>
                                    <td class="size-cell">
                                        <span class="size-badge">{$log.size_kb} KB</span>
                                    </td>
                                    <td class="action-cell">
                                        <a href="{$log.url}" target="_blank" class="btn btn-sm btn-default">
                                            <i class="icon-external-link"></i>
                                            Voir
                                        </a>
                                    </td>
                                </tr>


                    {/foreach}
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->

                    {if $total_logs_found > $logs_per_page}
                    <div class="pagination-container">
                        <p class="pagination-info">
                            Affichage de {$logs_displayed} sur {$total_logs_found} log


                        {if $total_logs_found > 1}s


                        {/if}
                        </p>
                        <div class="pagination-buttons">


                        {if $current_page > 1}
                                <a href="?configure={$smarty.get.configure}&active_tab=logs&token={$token}&page={$current_page-1}&search_month={$search_month}&search_year={$search_year}"
                                    class="btn btn-default">
                                    <i class="icon-chevron-left"></i> Précédent
                                </a>


                        {/if}
                            <span class="btn btn-info disabled current-page">
                                Page {$current_page} sur {$total_pages}
                            </span>


                        {if $current_page < $total_pages}
                                <a href="?configure={$smarty.get.configure}&active_tab=logs&token={$token}&page={$current_page+1}&search_month={$search_month}&search_year={$search_year}"
                                    class="btn btn-default">
                                    Suivant <i class="icon-chevron-right"></i>
                                </a>


                        {/if}
                        </div>
                    </div>


                    {/if}


                {else}
                <h4>Logs précédents</h4>


                    {if $search_month || $search_year}
                    <div class="alert alert-info">
                        <i class="icon-info"></i>
                        Aucun log trouvé pour


                        {if $search_month}{$months_names[$search_month]}


                        {/if} {$search_year}.
                    </div>


                    {else}
                    <div class="alert alert-info">
                        <i class="icon-info"></i>
                        Aucun log précédent trouvé.
                    </div>


                    {/if}


                {/if}

            <!-- Informations complémentaires -->
            <hr>
            <h4>Informations</h4>
            <ul>
                <li><strong>URL du log d'aujourd'hui :</strong> <code>{$logs_today_url}</code></li>
                <li><strong>Dernière tâche cron :</strong> {if $last_cron}{$last_cron}{else}Jamais exécutée{/if}</li>
                <li><strong>Répertoire des logs :</strong> <code>/modules/pfproductimporter/</code></li>
            </ul>
        </div>
    </div>

    <style>
        /* Titres */
        .panel-body h4 {
            margin-top: 20px;
            margin-bottom: 15px;
            color: #2eacce;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }

        .panel-body h4:first-child {
            margin-top: 0;
        }

        /* Alert du jour */
        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        /* Section de recherche */
        .search-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }

        .search-form {
            margin-top: 15px;
        }

        .search-row {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 20px;
        }

        .search-field {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }

        .search-field label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .search-field .form-control {
            min-width: 150px;
        }

        .search-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Table des logs */
        .logs-table-container {
            margin: 20px 0;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .logs-table {
            margin: 0;
            width: 100%;
            background-color: white;
        }

        .logs-table thead th {
            background-color: #2eacce;
            color: white;
            text-align: center;
            font-weight: 600;
            padding: 12px;
            border: none;
        }

        .logs-table tbody td {
            text-align: center;
            vertical-align: middle;
            padding: 12px;
            border-color: #e9ecef;
        }

        .logs-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Cellules spécifiques */
        .date-cell {
            font-weight: 500;
        }

        .size-cell {
            text-align: center;
        }

        .action-cell {
            text-align: center;
        }

        /* Badge de taille */
        .size-badge {
            background-color: #cbf2d4;
            color: #368c4a;
            padding: 5px 10px;
            border: solid 1px #53d572;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        /* Pagination */
        .pagination-container {
            margin: 30px 0 20px 0;
            text-align: center;
        }

        .pagination-info {
            color: #6c757d;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .pagination-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .current-page {
            background-color: #2eacce !important;
            border-color: #2eacce !important;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .search-row {
                flex-direction: column;
                align-items: stretch;
            }

            .search-field {
                min-width: 100%;
            }

            .search-buttons {
                justify-content: center;
                margin-top: 15px;
            }

            .flex-between {
                flex-direction: column;
                text-align: center;
            }

            .pagination-buttons {
                flex-direction: column;
            }

            .logs-table-container {
                overflow-x: auto;
            }
        }

        @media (max-width: 480px) {
            .search-section {
                padding: 15px;
            }

            .logs-table thead th,
            .logs-table tbody td {
                padding: 8px 4px;
                font-size: 12px;
            }

            .size-badge {
                padding: 4px 8px;
                font-size: 10px;
            }
        }
    </style>