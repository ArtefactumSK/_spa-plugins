<?php
/**
 * Plugin Name: SPA Import
 * Description: CSV import pre SPA (DB-first kompatibilny): create + update rezim, TEST MODE.
 * Version: 1.0.0
 * Author: SPA System
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'spa_csv_import_menu');

function spa_csv_import_menu(): void
{
    add_management_page(
        'SPA Import',
        'SPA Import',
        'manage_options',
        'spa-import',
        'spa_csv_import_page'
    );
}

function spa_csv_import_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Nemate opravnenie.');
    }

    $result = null;
    if (isset($_POST['spa_import_submit']) && check_admin_referer('spa_import', 'spa_import_nonce')) {
        $result = spa_csv_import_handle_submission();
    }

    $programs = get_posts([
        'post_type' => 'spa_group',
        'posts_per_page' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
    ]);

    ?>
    <div class="wrap">
        <h1>SPA Import (DB-first)</h1>
        <?php if (is_array($result)) : ?>
            <div class="notice notice-<?php echo esc_attr($result['status']); ?>">
                <p><strong><?php echo esc_html($result['message']); ?></strong></p>
                <?php if (!empty($result['details'])) : ?>
                    <details>
                        <summary>Detaily</summary>
                        <pre><?php echo esc_html($result['details']); ?></pre>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('spa_import', 'spa_import_nonce'); ?>
            <table class="form-table">
            <tr>
                    <th><label for="import_file">CSV subor</label></th>
                    <td>
                    <div id="spa-dropzone" class="spa-dropzone">
                            <p class="spa-dropzone-label">Pretiahni CSV súbor sem alebo <span class="spa-dropzone-link">klikni na výber</span></p>
                            <p id="spa-dropzone-filename" class="spa-dropzone-filename" style="display:none;"></p>
                            <input type="file" name="import_file" id="import_file" accept=".csv" style="display:none;">
                        </div>
                        <p class="description" style="margin-top:6px;">Alebo vlož CSV obsah priamo nižšie:</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="spa_import_csv_text">CSV text (alternatíva)</label></th>
                    <td>
                        <textarea name="spa_import_csv_text" id="spa_import_csv_text" rows="10" style="width:100%;font-family:monospace;" placeholder="Vlož CSV obsah sem (alternatíva k uploadu)"></textarea>
                        <p class="description">Ak vložíš CSV obsah sem, súbor sa ignoruje.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="program_id">Program</label></th>
                    <td>
                        <select required name="program_id" id="program_id">
                            <option value="">- Vyber program -</option>
                            <?php foreach ($programs as $program) : ?>
                                <option value="<?php echo (int) $program->ID; ?>"><?php echo esc_html(spa_csv_import_format_program_option_label((int) $program->ID, (string) $program->post_title)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="spa_price_type">Typ ceny / frekvencia</label></th>
                    <td>
                        <select name="spa_price_type" id="spa_price_type">
                            <option value="spa_price_1x_weekly" selected>1× týždenne</option>
                            <option value="spa_price_2x_weekly">2× týždenne</option>
                            <option value="spa_price_monthly">Mesačný paušál</option>
                            <option value="spa_price_semester">Cena za celý program</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="delimiter">Oddelovac</label></th>
                    <td>
                        <select name="delimiter" id="delimiter">
                            <option value=";">Bodkociarka (;)</option>
                            <option value=",">Ciarka (,)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Moznosti</th>
                    <td>
                        <label><input type="checkbox" name="skip_existing" value="1" checked> Preskocit existujucich</label><br>
                        <label><input type="checkbox" name="dry_run" value="1"> <strong>TEST MODE</strong> (bez zapisu)</label><br>
                        <input type="hidden" name="marketing_consent_present" value="1">
                        <label><input type="checkbox" name="marketing_consent" value="1"> Súhlas so zasielaním marketingových informácií</label><br>
                        <label for="batch_limit">Batch limit:</label>
                        <input type="number" min="1" max="1000" name="batch_limit" id="batch_limit" value="200"><br>
                        <label for="time_limit">Time limit (sekundy):</label>
                        <input type="number" min="5" max="120" name="time_limit" id="time_limit" value="20">
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="spa_import_submit" class="button button-primary">Spustit import</button>
            </p>
        </form>
        </div>
    <style>
    .spa-dropzone {
        border: 2px dashed #ccc;
        border-radius: 4px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        background: #fafafa;
        transition: border-color 0.2s, background 0.2s;
        max-width: 500px;
    }
    .spa-dropzone.dragover {
        border-color: #0073aa;
        background: #f0f8ff;
    }
    .spa-dropzone.has-file {
        border-color: #46b450;
        background: #f0fff0;
    }
    .spa-dropzone-label {
        margin: 0;
        color: #555;
        pointer-events: none;
    }
    .spa-dropzone-link {
        color: #0073aa;
        text-decoration: underline;
        pointer-events: none;
    }
    .spa-dropzone-filename {
        margin: 8px 0 0;
        font-weight: 600;
        color: #46b450;
        pointer-events: none;
    }
    </style>
    <script>
    (function () {
        var zone  = document.getElementById('spa-dropzone');
        var input = document.getElementById('import_file');
        var label = document.getElementById('spa-dropzone-filename');

        if (!zone || !input) return;

        function setFile(file) {
            if (!file) return;
            // Priradenie cez DataTransfer — štandardná cesta pre programatické nastavenie input.files
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;

            label.textContent = file.name;
            label.style.display = 'block';
            zone.classList.remove('dragover');
            zone.classList.add('has-file');

            // Trigger change pre prípadné ďalšie listenery
            input.dispatchEvent(new Event('change', { bubbles: true }));

            console.log('[SPA_IMPORT] IMPORT SOURCE: file (drag&drop) ->', file.name);
        }

        // Klik na dropzone → otvorí file dialog
        zone.addEventListener('click', function (e) {
            // Ak klik prišiel priamo z inputu, neprerušujeme
            if (e.target === input) return;
            input.click();
        });

        // Výber cez dialog → aktualizuj label
        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                label.textContent = input.files[0].name;
                label.style.display = 'block';
                zone.classList.add('has-file');
                console.log('[SPA_IMPORT] IMPORT SOURCE: file (dialog) ->', input.files[0].name);
            }
        });

        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('dragover');
        });

        zone.addEventListener('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('dragover');
        });

        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var files = e.dataTransfer && e.dataTransfer.files;
            if (files && files[0]) {
                setFile(files[0]);
            }
        });
    })();
    </script>
    <?php
}

function spa_csv_import_format_program_option_label(int $programId, string $programTitle): string
{
    $title = trim($programTitle);

    $ageFromRaw = get_post_meta($programId, 'spa_age_from', true);
    $ageToRaw = get_post_meta($programId, 'spa_age_to', true);
    $ageFrom = $ageFromRaw !== '' ? (string) $ageFromRaw : '';
    $ageTo = $ageToRaw !== '' ? (string) $ageToRaw : '';
    $ageLabel = '';

    // Rovnaky princip ako spa-selector: vek na zaciatku, rozsah alebo plus.
    if ($ageFrom !== '' && $ageTo !== '') {
        $ageLabel = str_replace('.', ',', $ageFrom) . '–' . str_replace('.', ',', $ageTo) . 'r.';
    } elseif ($ageFrom !== '') {
        $ageLabel = str_replace('.', ',', $ageFrom) . '+r.';
    }

    $cityLabel = '';
    $placeId = (int) get_post_meta($programId, 'spa_place_id', true);
    if ($placeId > 0) {
        $cityLabel = trim((string) get_post_meta($placeId, 'spa_place_city', true));
    }

    $parts = [];
    if ($ageLabel !== '') {
        $parts[] = $ageLabel;
    }
    if ($title !== '') {
        $parts[] = $title;
    }
    if ($cityLabel !== '') {
        $parts[] = $cityLabel;
    }

    return !empty($parts) ? implode(' - ', $parts) : $title;
}

function spa_csv_import_handle_submission(): array
{
    $csvText = isset($_POST['spa_import_csv_text']) ? trim((string) $_POST['spa_import_csv_text']) : '';
    $hasTextarea = $csvText !== '';
    $hasFile = isset($_FILES['import_file']) && (int) $_FILES['import_file']['error'] === UPLOAD_ERR_OK;

    if (!$hasTextarea && !$hasFile) {
        return ['status' => 'error', 'message' => 'Chyba: vlož CSV obsah do textarea alebo nahraj súbor.', 'details' => ''];
    }

    $programId = isset($_POST['program_id']) ? (int) $_POST['program_id'] : 0;
    $delimiter = isset($_POST['delimiter']) ? (string) $_POST['delimiter'] : ';';
    $dryRun = isset($_POST['dry_run']);
    $skipExisting = isset($_POST['skip_existing']);
    $batchLimit = isset($_POST['batch_limit']) ? max(1, min(1000, (int) $_POST['batch_limit'])) : 200;
    $timeLimit = isset($_POST['time_limit']) ? max(5, min(120, (int) $_POST['time_limit'])) : 20;
    $allowedPriceTypes = [
        'spa_price_1x_weekly',
        'spa_price_2x_weekly',
        'spa_price_monthly',
        'spa_price_semester',
    ];
    $postedPriceType = isset($_POST['spa_price_type']) ? sanitize_key((string) $_POST['spa_price_type']) : '';
    $priceType = in_array($postedPriceType, $allowedPriceTypes, true) ? $postedPriceType : 'spa_price_1x_weekly';
    $marketingConsentProvided = isset($_POST['marketing_consent_present']);
    $marketingConsent = isset($_POST['marketing_consent']) ? 1 : 0;

    if ($programId <= 0) {
        return ['status' => 'error', 'message' => 'Vyber program.', 'details' => ''];
    }

    if ($hasTextarea) {
        error_log('[SPA_IMPORT] IMPORT SOURCE: textarea');
        $parsed = spa_csv_import_parse_csv_string($csvText, $delimiter);
    } else {
        error_log('[SPA_IMPORT] IMPORT SOURCE: file');
        $parsed = spa_csv_import_parse_csv_file((string) $_FILES['import_file']['tmp_name'], $delimiter);
    }
    if (!$parsed['ok']) {
        return ['status' => 'error', 'message' => $parsed['message'], 'details' => ''];
    }

    $rows = (array) $parsed['rows'];
    $stats = [
        'create' => 0,
        'update' => 0,
        'skip' => 0,
        'error' => 0,
        'processed' => 0,
    ];
    $logs = [];
    $startTs = microtime(true);
    $seenSignatures = [];

    foreach ($rows as $index => $rawRow) {
        $rowNum = (int) $index + 2;

        if ($stats['processed'] >= $batchLimit) {
            $logs[] = "STOP: batch limit {$batchLimit} dosiahnuty.";
            break;
        }
        if ((microtime(true) - $startTs) >= $timeLimit) {
            $logs[] = "STOP: time limit {$timeLimit}s dosiahnuty.";
            break;
        }

        $dto = spa_csv_import_parse_row($rawRow, $programId, $rowNum);
        if (!$dto['ok']) {
            $stats['error']++;
            $stats['processed']++;
            $logs[] = "Riadok {$rowNum}: CHYBA - {$dto['error']}";
            continue;
        }

        $normalized = $dto['dto'];
        $warnings = isset($dto['warnings']) && is_array($dto['warnings']) ? $dto['warnings'] : [];
        foreach ($warnings as $warning) {
            $logs[] = "Riadok {$rowNum}: WARNING - " . (string) $warning;
        }
        $signature = md5(wp_json_encode([
            $normalized['child']['rc'],
            $normalized['child']['first_name'],
            $normalized['child']['last_name'],
            $normalized['child']['birth_date'],
            $normalized['registration']['program_id'],
        ]));
        if (isset($seenSignatures[$signature])) {
            $stats['skip']++;
            $stats['processed']++;
            $logs[] = "Riadok {$rowNum}: DUPLIKAT v rame CSV - preskocene.";
            continue;
        }
        $seenSignatures[$signature] = true;

        $result = spa_csv_import_import_row($normalized, [
            'dry_run' => $dryRun,
            'skip_existing' => $skipExisting,
            'price_type' => $priceType,
            'marketing_consent_provided' => $marketingConsentProvided,
            'marketing_consent' => $marketingConsent,
        ]);

        $stats['processed']++;
        if (!$result['ok']) {
            $stats['error']++;
            $logs[] = "Riadok {$rowNum}: CHYBA - {$result['error']}";
            continue;
        }

        $action = (string) $result['action'];
        if ($action === 'create') {
            $stats['create']++;
        } elseif ($action === 'update') {
            $stats['update']++;
        } else {
            $stats['skip']++;
        }
        $logs[] = "Riadok {$rowNum}: " . strtoupper($action) . " - " . (string) $result['message'];
    }

    $message = sprintf(
        '%sHotovo. processed=%d, create=%d, update=%d, skip=%d, error=%d',
        $dryRun ? '[TEST MODE] ' : '',
        $stats['processed'],
        $stats['create'],
        $stats['update'],
        $stats['skip'],
        $stats['error']
    );

    return [
        'status' => $stats['error'] > 0 ? 'warning' : 'success',
        'message' => $message,
        'details' => implode("\n", $logs),
    ];
}
function spa_csv_import_parse_csv_string(string $csvContent, string $delimiter): array
{
    $csvContent = str_replace("\r\n", "\n", $csvContent);
    $csvContent = str_replace("\r", "\n", $csvContent);

    $handle = fopen('php://memory', 'r+');
    if (!$handle) {
        return ['ok' => false, 'message' => 'Nepodarilo sa spracovať CSV text.', 'rows' => []];
    }
    fwrite($handle, $csvContent);
    rewind($handle);

    $headers = fgetcsv($handle, 0, $delimiter);
    if (!is_array($headers)) {
        fclose($handle);
        return ['ok' => false, 'message' => 'CSV text je prázdny alebo neplatný.', 'rows' => []];
    }

    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (empty(array_filter((array) $row, static function ($v): bool {
            return trim((string) $v) !== '';
        }))) {
            continue;
        }
        $rows[] = array_values((array) $row);
    }
    fclose($handle);

    return ['ok' => true, 'message' => '', 'rows' => $rows];
}
function spa_csv_import_parse_csv_file(string $filePath, string $delimiter): array
{
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return ['ok' => false, 'message' => 'Subor sa nepodarilo otvorit.', 'rows' => []];
    }

    $headers = fgetcsv($handle, 0, $delimiter);
    if (!is_array($headers)) {
        fclose($handle);
        return ['ok' => false, 'message' => 'CSV je prazdne.', 'rows' => []];
    }

    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (empty(array_filter((array) $row, static function ($v): bool {
            return trim((string) $v) !== '';
        }))) {
            continue;
        }
        $rows[] = array_values((array) $row);
    }
    fclose($handle);

    return ['ok' => true, 'message' => '', 'rows' => $rows];
}

function spa_csv_import_parse_row(array $row, int $programId, int $rowNum): array
{
    if (count($row) < 2) {
        return ['ok' => false, 'error' => 'rozbity CSV riadok', 'dto' => []];
    }

    $get = static function (int $index) use ($row): string {
        return isset($row[$index]) ? trim((string) $row[$index]) : '';
    };

    $memberFirst = $get(0);
    $memberLast = $get(1);
    $birthDate = spa_csv_import_normalize_date($get(4));
    $rc = preg_replace('/[^0-9]/', '', $get(5));

    $parentEmail = sanitize_email($get(30));
    $parentFirst = $get(31);
    $parentLast = $get(32);
    $parentPhone = spa_csv_import_normalize_phone($get(33));
    $addressStreet = $get(35);
    $addressZip = preg_replace('/\s+/', '', $get(36));
    $addressCity = $get(37);

    if ($memberFirst === '' || $memberLast === '') {
        return ['ok' => false, 'error' => 'chyba meno/priezvisko dietata', 'dto' => []];
    }
    if ($parentEmail === '') {
        return ['ok' => false, 'error' => 'chyba parent_email', 'dto' => []];
    }

    $warnings = [];
    if ($birthDate === '' && $rc === '') {
        $warnings[] = 'chýba dátum narodenia a rodné číslo (použitý fallback)';
    } elseif ($birthDate === '') {
        $warnings[] = 'chýba dátum narodenia (použitý fallback)';
    } elseif ($rc === '') {
        $warnings[] = 'chýba rodné číslo (použitý fallback)';
    }

    $dto = [
        'row_num' => $rowNum,
        'child' => [
            'first_name' => sanitize_text_field($memberFirst),
            'last_name' => sanitize_text_field($memberLast),
            'birth_date' => $birthDate,
            'rc' => $rc,
        ],
        'parent' => [
            'email' => $parentEmail,
            'first_name' => sanitize_text_field($parentFirst),
            'last_name' => sanitize_text_field($parentLast),
            'phone' => sanitize_text_field($parentPhone),
            'address' => trim($addressStreet . ', ' . $addressZip . ' ' . $addressCity, ', '),
            'address_street' => sanitize_text_field($addressStreet),
            'address_zip' => sanitize_text_field($addressZip),
            'address_city' => sanitize_text_field($addressCity),
        ],
        'registration' => [
            'program_id' => $programId,
            'vs' => preg_replace('/[^0-9]/', '', $get(8)),
            'created_at' => spa_csv_import_normalize_datetime($get(7)),
            'status' => spa_csv_import_map_status($get(29)),
        ],
    ];

    return ['ok' => true, 'error' => '', 'dto' => $dto, 'warnings' => $warnings];
}

function spa_csv_import_normalize_phone(string $raw): string
{
    $value = trim($raw);
    if ($value === '') {
        return '';
    }
    if (preg_match('/[Ee]\+/', $value)) {
        $value = str_replace(',', '.', $value);
        $value = number_format((float) $value, 0, '', '');
    }
    if (strpos($value, '+') === 0) {
        return '+' . preg_replace('/[^0-9]/', '', substr($value, 1));
    }
    $value = preg_replace('/[^0-9]/', '', $value);
    if (preg_match('/^09[0-9]{8}$/', $value)) {
        return '+421' . substr($value, 1);
    }
    return $value;
}

function spa_csv_import_normalize_date(string $input): string
{
    $input = trim($input);
    if ($input === '') {
        return '';
    }
    $formats = ['d.m.Y', 'd/m/Y', 'Y-m-d', 'd-m-Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $input);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }
    }
    return '';
}

function spa_csv_import_normalize_datetime(string $input): string
{
    $date = spa_csv_import_normalize_date($input);
    if ($date === '') {
        return '';
    }
    return $date . ' 00:00:00';
}

function spa_csv_import_map_status(string $csvStatus): string
{
    $value = mb_strtolower(trim($csvStatus));
    if (strpos($value, 'deaktiv') !== false) {
        return 'inactive';
    }
    if (strpos($value, 'presunut') !== false) {
        return 'transferred';
    }
    if (strpos($value, 'aktiv') !== false) {
        return 'active';
    }
    return 'inactive';
}

function spa_csv_import_import_row(array $dto, array $options): array
{
    $dryRun = !empty($options['dry_run']);
    $skipExisting = !empty($options['skip_existing']);
    $priceType = isset($options['price_type']) ? (string) $options['price_type'] : 'spa_price_1x_weekly';
    $marketingConsentProvided = !empty($options['marketing_consent_provided']);
    $marketingConsent = isset($options['marketing_consent']) ? (int) $options['marketing_consent'] : 0;

    $parent = spa_csv_import_resolve_parent($dto['parent']);
    $parentIdForResolver = (int) ($parent['id'] ?? 0);
    $child = spa_csv_import_resolve_child($dto['child'], $parentIdForResolver);
    $programId = (int) $dto['registration']['program_id'];
    $existingReg = ($child['id'] > 0) ? spa_csv_import_find_registration($child['id'], $programId) : null;
    $csvVs = isset($dto['registration']['vs']) ? preg_replace('/[^0-9]/', '', (string) $dto['registration']['vs']) : '';
    $dto['registration']['vs'] = $csvVs;

    if ($dryRun) {
        $payloadPreview = spa_csv_import_build_registration_payload($dto, max(0, (int) $parent['id']), max(0, (int) $child['id']));
        $childEmailPreview = spa_csv_import_generate_child_email_preview(
            (string) ($dto['child']['first_name'] ?? ''),
            (string) ($dto['child']['last_name'] ?? '')
        );
        error_log('[SPA_IMPORT][TEST] parent_match=' . wp_json_encode($parent));
        error_log('[SPA_IMPORT][TEST] child_match=' . wp_json_encode($child));
        error_log('[SPA_IMPORT][TEST] registration=' . wp_json_encode($existingReg));
        error_log('[SPA_IMPORT][TEST] price_type=' . $priceType);
        error_log('[SPA_IMPORT][TEST] child_email_fallback=' . $childEmailPreview);
        error_log('[SPA_IMPORT][TEST] vs_csv=' . (string) ($dto['registration']['vs'] ?? ''));
        error_log('[SPA_IMPORT][TEST] marketing_consent=' . ($marketingConsent ? 'true' : 'false'));
        error_log('[SPA_IMPORT][TEST] registration_payload=' . wp_json_encode($payloadPreview));
        return ['ok' => true, 'action' => 'skip', 'message' => 'TEST MODE bez zapisu'];
    }

    $parentId = (int) $parent['id'];
    if ($parentId <= 0) {
        $parentId = spa_csv_import_create_parent($dto['parent'], $marketingConsentProvided ? $marketingConsent : null);
        if ($parentId <= 0) {
            return ['ok' => false, 'action' => 'error', 'error' => 'nepodarilo sa vytvorit rodica'];
        }
    } else {
        if ($marketingConsentProvided) {
            spa_csv_import_update_parent_marketing_consent($parentId, $marketingConsent);
        }
    }

    $childId = (int) $child['id'];
    if ($childId <= 0) {
        $child = spa_csv_import_resolve_child($dto['child'], $parentId);
        $childId = (int) $child['id'];
    }
    if ($childId <= 0) {
        $childId = spa_csv_import_create_child($dto['child'], $parentId, !$skipExisting);
        if ($childId <= 0) {
            return ['ok' => false, 'action' => 'error', 'error' => 'nepodarilo sa vytvorit dieta'];
        }
    }

    $existingReg = spa_csv_import_find_registration($childId, $programId);
    if (is_array($existingReg)) {
        $existingDbId = (int) ($existingReg['id'] ?? 0);
        $existingVs = trim((string) ($existingReg['spa_vs'] ?? ''));
        $targetVs = $csvVs !== '' ? $csvVs : $existingVs;
        if ($targetVs === '' && function_exists('spa_generate_vs')) {
            $targetVs = (string) spa_generate_vs();
        }

        if ($targetVs !== '' && !spa_csv_import_is_vs_unique($targetVs, $existingDbId)) {
            spa_csv_import_log_vs_conflict('update', $targetVs, $existingDbId);
            return ['ok' => false, 'action' => 'error', 'error' => 'VS konflikt pri update: ' . $targetVs];
        }
        $dto['registration']['vs'] = $targetVs;

        if ($skipExisting) {
            return ['ok' => true, 'action' => 'skip', 'message' => 'registracia existuje, preskocena (skip_existing=true, bez zmien)'];
        }

        $updateDebug = [
            'child_user_id' => $childId,
            'child_email_old' => '',
            'child_email_new' => '',
            'vs_old' => $existingVs,
            'vs_new' => trim((string) ($dto['registration']['vs'] ?? '')),
            'consent_old' => '',
            'consent_new' => '',
        ];

        $childUser = get_userdata($childId);
        if ($childUser instanceof WP_User) {
            $updateDebug['child_email_old'] = (string) $childUser->user_email;
            $targetEmail = spa_csv_import_generate_child_email(
                (string) ($dto['child']['first_name'] ?? ''),
                (string) ($dto['child']['last_name'] ?? '')
            );
            $updateDebug['child_email_new'] = $targetEmail;
            $owner = $targetEmail !== '' ? get_user_by('email', $targetEmail) : null;
            if ($owner instanceof WP_User && (int) $owner->ID !== $childId) {
                $updateDebug['child_email_new'] = (string) $childUser->user_email;
            } elseif ($targetEmail !== '' && $targetEmail !== (string) $childUser->user_email) {
                $updateResult = wp_update_user([
                    'ID' => $childId,
                    'user_email' => $targetEmail,
                ]);
                if (is_wp_error($updateResult)) {
                    return ['ok' => false, 'action' => 'error', 'error' => 'update email dietata zlyhal: ' . $updateResult->get_error_message()];
                }
            }
        }

        if ($parentId > 0 && $marketingConsentProvided) {
            $updateDebug['consent_old'] = (string) (int) get_user_meta($parentId, 'consent_marketing', true);
            spa_csv_import_update_parent_marketing_consent($parentId, $marketingConsent);
            $updateDebug['consent_new'] = (string) (int) get_user_meta($parentId, 'consent_marketing', true);
        }

        $updated = spa_csv_import_update_registration((int) $existingReg['id'], $dto['registration']);
        if (empty($updated['ok'])) {
            return ['ok' => false, 'action' => 'error', 'error' => 'update registracie zlyhal'];
        }
        $syncedVs = spa_csv_import_sync_child_vs_meta($childId, (int) $existingReg['id']);
        error_log('[SPA_IMPORT][UPDATE] user_id=' . (int) $updateDebug['child_user_id']
            . ' child_email_old=' . $updateDebug['child_email_old']
            . ' child_email_new=' . $updateDebug['child_email_new']
            . ' vs_old=' . $updateDebug['vs_old']
            . ' vs_new=' . $updateDebug['vs_new']
            . ' vs_synced=' . $syncedVs
            . ' consent_old=' . $updateDebug['consent_old']
            . ' consent_new=' . $updateDebug['consent_new']);

        return [
            'ok' => true,
            'action' => 'update',
            'message' => 'registracia aktualizovana | child_email ' . $updateDebug['child_email_old'] . ' -> ' . $updateDebug['child_email_new']
                . ' | vs ' . $updateDebug['vs_old'] . ' -> ' . $updateDebug['vs_new']
                . ' | consent ' . $updateDebug['consent_old'] . ' -> ' . $updateDebug['consent_new']
                . (!empty($updated['vs_message']) ? ' | ' . (string) $updated['vs_message'] : ''),
        ];
    }

    $targetCreateVs = $csvVs;
    if ($targetCreateVs === '' && function_exists('spa_generate_vs')) {
        $targetCreateVs = (string) spa_generate_vs();
    }
    if ($targetCreateVs !== '' && !spa_csv_import_is_vs_unique($targetCreateVs, 0)) {
        spa_csv_import_log_vs_conflict('create', $targetCreateVs, 0);
        return ['ok' => false, 'action' => 'error', 'error' => 'VS konflikt pri create: ' . $targetCreateVs];
    }
    $dto['registration']['vs'] = $targetCreateVs;

    $created = spa_csv_import_create_registration_via_service($dto, $parentId, $childId);
    if (!$created['ok']) {
        return ['ok' => false, 'action' => 'error', 'error' => $created['error']];
    }

    $newDbReg = spa_csv_import_find_registration($childId, $programId);
    if (is_array($newDbReg)) {
        $createUpdate = spa_csv_import_update_registration((int) $newDbReg['id'], $dto['registration']);
        $syncedVs = spa_csv_import_sync_child_vs_meta($childId, (int) $newDbReg['id']);
        $childUser = get_userdata($childId);
        error_log('[SPA_IMPORT][CREATE] child_email=' . (string) ($childUser instanceof WP_User ? $childUser->user_email : '')
            . ' vs_csv=' . (string) ($dto['registration']['vs'] ?? '')
            . ' vs_synced=' . $syncedVs
            . ' post_status=publish');
        if (!empty($createUpdate['vs_message'])) {
            return ['ok' => true, 'action' => 'create', 'message' => 'registracia vytvorena cez RegistrationService | ' . (string) $createUpdate['vs_message']];
        }
    }
    return ['ok' => true, 'action' => 'create', 'message' => 'registracia vytvorena cez RegistrationService'];
}

function spa_csv_import_resolve_parent(array $parent): array
{
    $email = (string) ($parent['email'] ?? '');
    if ($email !== '') {
        $candidate = get_user_by('email', $email);
        if ($candidate instanceof WP_User && spa_user_has_role((int) $candidate->ID, 'spa_parent')) {
            return ['id' => (int) $candidate->ID, 'method' => 'email'];
        }
    }

    $nameFirst = (string) ($parent['first_name'] ?? '');
    $phone = (string) ($parent['phone'] ?? '');
    if ($nameFirst !== '' && $phone !== '') {
        $query = new WP_User_Query([
            'number' => 10,
            'count_total' => false,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'first_name', 'value' => $nameFirst, 'compare' => '='],
                ['key' => 'phone', 'value' => $phone, 'compare' => '='],
            ],
        ]);
        foreach ((array) $query->get_results() as $user) {
            if ($user instanceof WP_User && spa_user_has_role((int) $user->ID, 'spa_parent')) {
                return ['id' => (int) $user->ID, 'method' => 'name_phone'];
            }
        }
    }

    return ['id' => 0, 'method' => 'none'];
}

function spa_csv_import_resolve_child(array $child, int $parentId = 0): array
{
    $rc = (string) ($child['rc'] ?? '');
    if ($rc !== '') {
        $query = new WP_User_Query([
            'number' => 1,
            'count_total' => false,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => 'rodne_cislo', 'value' => $rc, 'compare' => '='],
            ],
        ]);
        $ids = (array) $query->get_results();
        if (!empty($ids)) {
            $candidate = get_userdata((int) $ids[0]);
            if ($candidate instanceof WP_User && spa_user_has_role((int) $candidate->ID, 'spa_child')) {
                return ['id' => (int) $candidate->ID, 'method' => 'rc'];
            }
        }
    }

    $nameFirst = (string) ($child['first_name'] ?? '');
    $birthDate = (string) ($child['birth_date'] ?? '');
    if ($nameFirst !== '' && $birthDate !== '') {
        $query = new WP_User_Query([
            'number' => 10,
            'count_total' => false,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'first_name', 'value' => $nameFirst, 'compare' => '='],
                ['key' => 'birthdate', 'value' => $birthDate, 'compare' => '='],
            ],
        ]);
        foreach ((array) $query->get_results() as $user) {
            if ($user instanceof WP_User && spa_user_has_role((int) $user->ID, 'spa_child')) {
                return ['id' => (int) $user->ID, 'method' => 'name_birthdate'];
            }
        }
    }

    $nameLast = (string) ($child['last_name'] ?? '');
    if ($parentId > 0 && $nameFirst !== '' && $nameLast !== '') {
        $query = new WP_User_Query([
            'number' => 10,
            'count_total' => false,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'parent_id', 'value' => $parentId, 'compare' => '=', 'type' => 'NUMERIC'],
                ['key' => 'first_name', 'value' => $nameFirst, 'compare' => '='],
                ['key' => 'last_name', 'value' => $nameLast, 'compare' => '='],
            ],
        ]);
        foreach ((array) $query->get_results() as $user) {
            if ($user instanceof WP_User && spa_user_has_role((int) $user->ID, 'spa_child')) {
                return ['id' => (int) $user->ID, 'method' => 'parent_name'];
            }
        }
    }

    return ['id' => 0, 'method' => 'none'];
}

function spa_csv_import_create_parent(array $parent, ?int $marketingConsent = null): int
{
    $email = (string) ($parent['email'] ?? '');
    if ($email === '') {
        return 0;
    }
    $first = (string) ($parent['first_name'] ?? '');
    $last = (string) ($parent['last_name'] ?? '');
    if ($first === '' || $last === '') {
        return 0;
    }

    if (function_exists('spa_get_or_create_parent')) {
        $id = (int) spa_get_or_create_parent(
            $email,
            $first,
            $last,
            (string) ($parent['phone'] ?? ''),
            (string) ($parent['address_street'] ?? ''),
            (string) ($parent['address_zip'] ?? ''),
            (string) ($parent['address_city'] ?? '')
        );
        if ($id > 0) {
            if ($marketingConsent !== null) {
                spa_csv_import_update_parent_marketing_consent($id, $marketingConsent);
            }
            return $id;
        }
    }

    $username = sanitize_user(remove_accents(strtolower($first . '.' . $last)), true);
    $base = $username !== '' ? $username : 'parent';
    $username = $base;
    $i = 1;
    while (username_exists($username)) {
        $username = $base . $i;
        $i++;
    }

    $userId = wp_insert_user([
        'user_login' => $username,
        'user_email' => $email,
        'user_pass' => wp_generate_password(16, true),
        'first_name' => $first,
        'last_name' => $last,
        'display_name' => trim($first . ' ' . $last),
        'role' => 'spa_parent',
    ]);
    if (is_wp_error($userId)) {
        return 0;
    }

    update_user_meta((int) $userId, 'phone', (string) ($parent['phone'] ?? ''));
    update_user_meta((int) $userId, 'address_street', (string) ($parent['address_street'] ?? ''));
    update_user_meta((int) $userId, 'address_psc', (string) ($parent['address_zip'] ?? ''));
    update_user_meta((int) $userId, 'address_city', (string) ($parent['address_city'] ?? ''));
    update_user_meta((int) $userId, 'spa_roles', ['spa_parent']);
    if ($marketingConsent !== null) {
        spa_csv_import_update_parent_marketing_consent((int) $userId, $marketingConsent);
    }

    return (int) $userId;
}

function spa_csv_import_update_parent_marketing_consent(int $parentId, int $marketingConsent): void
{
    if ($parentId <= 0) {
        return;
    }
    update_user_meta($parentId, 'consent_marketing', $marketingConsent ? 1 : 0);
    // Backward-compatible mirror for legacy reads.
    update_user_meta($parentId, 'spa_marketing_consent', $marketingConsent ? 1 : 0);
}

function spa_csv_import_create_child(array $child, int $parentId, bool $allowExistingEmailMatch = false): int
{
    if ($parentId <= 0) {
        return 0;
    }
    if (function_exists('spa_create_child_account')) {
        $id = spa_create_child_account(
            (string) $child['first_name'],
            (string) $child['last_name'],
            (string) $child['birth_date'],
            $parentId,
            '',
            (string) $child['rc']
        );
        if (is_int($id) && $id > 0) {
            return $id;
        }
    }

    $first = (string) $child['first_name'];
    $last = (string) $child['last_name'];
    $username = sanitize_user(remove_accents(strtolower($first . '.' . $last)), true);
    $base = $username !== '' ? $username : 'child';
    $username = $base;
    $i = 1;
    while (username_exists($username)) {
        $username = $base . $i;
        $i++;
    }

    $email = spa_csv_import_generate_child_email($first, $last);
    $existingByEmail = get_user_by('email', $email);
    if ($existingByEmail instanceof WP_User) {
        if ($allowExistingEmailMatch && spa_user_has_role((int) $existingByEmail->ID, 'spa_child')) {
            update_user_meta((int) $existingByEmail->ID, 'parent_id', $parentId);
            return (int) $existingByEmail->ID;
        }
        return 0;
    }

    $userId = wp_insert_user([
        'user_login' => $username,
        'user_email' => $email,
        'user_pass' => wp_generate_password(20, true),
        'first_name' => $first,
        'last_name' => $last,
        'display_name' => trim($first . ' ' . $last),
        'role' => 'spa_child',
    ]);
    if (is_wp_error($userId)) {
        return 0;
    }

    update_user_meta((int) $userId, 'birthdate', (string) $child['birth_date']);
    update_user_meta((int) $userId, 'parent_id', $parentId);
    update_user_meta((int) $userId, 'rodne_cislo', (string) $child['rc']);
    update_user_meta((int) $userId, 'spa_roles', ['spa_child']);

    return (int) $userId;
}

function spa_csv_import_generate_child_email(string $firstName, string $lastName): string
{
    $local = spa_csv_import_normalize_email_local_part($firstName, $lastName);
    return sanitize_email($local . '@piaseckyacademy.sk');
}

function spa_csv_import_generate_child_email_preview(string $firstName, string $lastName): string
{
    $local = spa_csv_import_normalize_email_local_part($firstName, $lastName);
    return $local . '@piaseckyacademy.sk';
}

function spa_csv_import_normalize_email_local_part(string $firstName, string $lastName): string
{
    $first = trim(remove_accents(mb_strtolower($firstName)));
    $last = trim(remove_accents(mb_strtolower($lastName)));

    $first = preg_replace('/\s+/', '-', $first);
    $last = preg_replace('/\s+/', '-', $last);
    $first = preg_replace('/[^a-z0-9\-]/', '', $first);
    $last = preg_replace('/[^a-z0-9\-]/', '', $last);

    $local = trim($first . '.' . $last, '.');
    if ($local === '') {
        $local = 'child';
    }
    return $local;
}

function spa_csv_import_sync_child_vs_meta(int $childId, int $dbRegistrationId): string
{
    if ($childId <= 0 || $dbRegistrationId <= 0) {
        return '';
    }
    global $wpdb;
    $table = $wpdb->prefix . 'spa_registrations';
    $vs = (string) $wpdb->get_var($wpdb->prepare("SELECT spa_vs FROM {$table} WHERE id = %d LIMIT 1", $dbRegistrationId));
    if ($vs !== '') {
        update_user_meta($childId, 'spa_vs', $vs);
        update_user_meta($childId, 'variabilny_symbol', $vs);
    }
    return $vs;
}

function spa_csv_import_find_registration(int $childId, int $programId): ?array
{
    global $wpdb;
    $table = $wpdb->prefix . 'spa_registrations';
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, status, spa_vs, created_at FROM {$table} WHERE client_user_id = %d AND program_id = %d ORDER BY id DESC LIMIT 1",
            $childId,
            $programId
        ),
        ARRAY_A
    );
    return is_array($row) ? $row : null;
}

function spa_csv_import_get_vs_conflicts_detected(): int
{
    return max(0, (int) get_option('spa_import_vs_conflicts_detected', 0));
}

function spa_csv_import_increment_vs_conflicts_detected(): void
{
    update_option('spa_import_vs_conflicts_detected', spa_csv_import_get_vs_conflicts_detected() + 1, false);
}

function spa_csv_import_log_vs_conflict(string $context, string $vs, int $excludeDbRegistrationId = 0): void
{
    spa_csv_import_increment_vs_conflicts_detected();
    error_log(
        '[SPA_IMPORT][VS_CONFLICT] context=' . $context
        . ' vs=' . $vs
        . ' exclude_db_registration_id=' . $excludeDbRegistrationId
    );
}

function spa_csv_import_is_vs_unique(string $vs, int $excludeDbRegistrationId = 0): bool
{
    global $wpdb;
    $vs = preg_replace('/[^0-9]/', '', trim($vs));
    if ($vs === '') {
        return true;
    }

    $table = $wpdb->prefix . 'spa_registrations';
    if ($excludeDbRegistrationId > 0) {
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE spa_vs = %s AND id != %d",
                $vs,
                $excludeDbRegistrationId
            )
        );
    } else {
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE spa_vs = %s",
                $vs
            )
        );
    }

    return $count === 0;
}

function spa_csv_import_update_registration(int $dbRegistrationId, array $registration): array
{
    global $wpdb;
    $table = $wpdb->prefix . 'spa_registrations';

    $current = $wpdb->get_row(
        $wpdb->prepare("SELECT id, created_at, spa_vs FROM {$table} WHERE id = %d LIMIT 1", $dbRegistrationId),
        ARRAY_A
    );
    if (!is_array($current)) {
        return ['ok' => false, 'vs_message' => ''];
    }

    $data = [
        'status' => (string) ($registration['status'] ?? 'inactive'),
    ];
    $format = ['%s'];
    $vs = isset($registration['vs']) ? trim((string) $registration['vs']) : '';
    $vsMessage = '';
    if ($vs !== '') {
        $isUnique = spa_csv_import_is_vs_unique($vs, (int) $dbRegistrationId);
        if ($isUnique) {
            $data['spa_vs'] = $vs;
            $format[] = '%s';
        } else {
            $vsMessage = 'VS neuložený – duplicita';
            spa_csv_import_log_vs_conflict('update_registration', $vs, (int) $dbRegistrationId);
        }
    }

    $createdAt = (string) ($registration['created_at'] ?? '');
    if ($createdAt !== '' && (string) ($current['created_at'] ?? '') === '') {
        $data['created_at'] = $createdAt;
        $format[] = '%s';
    }
    $data['updated_at'] = current_time('mysql');
    $format[] = '%s';

    $ok = $wpdb->update($table, $data, ['id' => $dbRegistrationId], $format, ['%d']);
    if ($ok === false) {
        return ['ok' => false, 'vs_message' => $vsMessage];
    }

    $cptCandidates = get_posts([
        'post_type' => 'spa_registration',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            ['key' => 'db_registration_id', 'value' => $dbRegistrationId, 'compare' => '=', 'type' => 'NUMERIC'],
        ],
    ]);
    $cptId = !empty($cptCandidates) ? (int) $cptCandidates[0] : 0;

    if ($cptId > 0) {
        update_post_meta($cptId, 'status', (string) ($registration['status'] ?? 'inactive'));
        $syncVs = isset($data['spa_vs']) ? (string) $data['spa_vs'] : (string) ($current['spa_vs'] ?? '');
        if ($syncVs !== '') {
            update_post_meta($cptId, 'spa_vs', $syncVs);
        }
        wp_update_post([
            'ID' => $cptId,
            'post_status' => 'publish',
        ]);
        if (function_exists('spa_update_registration_status')) {
            spa_update_registration_status($cptId, (string) ($registration['status'] ?? 'inactive'));
        }
    }

    return ['ok' => true, 'vs_message' => $vsMessage];
}

function spa_csv_import_build_registration_payload(array $dto, int $parentId, int $childId): array
{
    $vs = isset($dto['registration']['vs']) ? trim((string) $dto['registration']['vs']) : '';
    return [
        'payload' => [
            'memberFirstName' => (string) $dto['child']['first_name'],
            'memberLastName' => (string) $dto['child']['last_name'],
            'memberBirthdate' => (string) $dto['child']['birth_date'],
            'memberBirthnumber' => (string) $dto['child']['rc'],
            'guardianFirstName' => (string) $dto['parent']['first_name'],
            'guardianLastName' => (string) $dto['parent']['last_name'],
            'parentEmail' => (string) $dto['parent']['email'],
            'parentPhone' => (string) $dto['parent']['phone'],
            'clientAddressStreet' => (string) $dto['parent']['address_street'],
            'clientAddressPostcode' => (string) $dto['parent']['address_zip'],
            'clientAddressCity' => (string) $dto['parent']['address_city'],
            'paymentMethod' => 'invoice_payment',
            'gfEntryId' => 0,
            // RegistrationPayload nema explicitne VS property; ponechavame pre transparentny debug preview.
            'spaVs' => $vs,
        ],
        'user_ids' => [
            'parent_user_id' => $parentId,
            'child_user_id' => $childId,
            'client_user_id' => $childId,
        ],
        'session' => [
            'program_id' => (int) $dto['registration']['program_id'],
            'frequency_key' => 'import_csv',
            'amount' => 0.0,
            'scope' => 'child',
            'created_at' => time(),
        ],
    ];
}

function spa_csv_import_create_registration_via_service(array $dto, int $parentId, int $childId): array
{
    if (!class_exists('\SpaRegisterGf\Services\RegistrationService') || !class_exists('\SpaRegisterGf\Domain\RegistrationPayload') || !class_exists('\SpaRegisterGf\Services\SessionService')) {
        return ['ok' => false, 'error' => 'spa-register-gf plugin nie je aktivny alebo chybaju triedy'];
    }

    $importData = spa_csv_import_build_registration_payload($dto, $parentId, $childId);
    try {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        $sessionBackup = array_key_exists('spa_registration', $_SESSION) ? $_SESSION['spa_registration'] : null;
        $_SESSION['spa_registration'] = $importData['session'];

        $payload = new \SpaRegisterGf\Domain\RegistrationPayload();
        foreach ((array) $importData['payload'] as $key => $value) {
            if (property_exists($payload, (string) $key)) {
                $payload->{$key} = $value;
            }
        }

        $session = new \SpaRegisterGf\Services\SessionService();
        $service = new \SpaRegisterGf\Services\RegistrationService();
        $service->create($payload, (array) $importData['user_ids'], $session);

        $csvVs = isset($dto['registration']['vs']) ? trim((string) $dto['registration']['vs']) : '';
        if ($csvVs !== '') {
            $dbRegistration = spa_csv_import_find_registration((int) $childId, (int) $dto['registration']['program_id']);
            if (is_array($dbRegistration) && !empty($dbRegistration['id'])) {
                spa_csv_import_update_registration((int) $dbRegistration['id'], [
                    'status' => (string) ($dto['registration']['status'] ?? 'inactive'),
                    'vs' => $csvVs,
                    'created_at' => (string) ($dto['registration']['created_at'] ?? ''),
                ]);
            }
        }

        if ($sessionBackup !== null) {
            $_SESSION['spa_registration'] = $sessionBackup;
        } else {
            unset($_SESSION['spa_registration']);
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'RegistrationService create zlyhal: ' . $e->getMessage()];
    }

    return ['ok' => true, 'error' => ''];
}
