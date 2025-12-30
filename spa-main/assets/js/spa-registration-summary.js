/**
 * SPA Registration – Dynamické selecty (City → Program)
 * Vanilla JS implementácia pre Gravity Forms
 */

(function() {
    'use strict';

    // Kontrola, či existuje konfigurácia
    if (typeof spaConfig === 'undefined') {
        console.error('[SPA] spaConfig nie je definovaný.');
        return;
    }

    // Extrakcia field ID z spa-config
    const cityInputId = spaConfig.fields.spa_city;
    const programInputId = spaConfig.fields.spa_program;

    if (!cityInputId || !programInputId) {
        console.error('[SPA] Chýbajúce field ID v spa-config.');
        return;
    }

    // Konverzia input_XX na selector ID
    // input_11 → #input_1_11 (pre form ID = 1, dynamicky detekované)
    function getFieldSelector(inputId) {
        const fieldNum = inputId.replace('input_', '');
        // GF používa formát: #input_{form_id}_{field_id}
        // Dynamicky nájdeme form ID z HTML
        const formElement = document.querySelector('.gform_wrapper form');
        if (!formElement) return null;
        
        const formId = formElement.id.replace('gform_', '');
        return `#input_${formId}_${fieldNum}`;
    }

    // Inicializácia po načítaní DOM
    document.addEventListener('DOMContentLoaded', function() {
        initDynamicSelects();
    });

    // Gravity Forms AJAX callback (pre multi-page forms)
    if (typeof gform !== 'undefined') {
        jQuery(document).on('gform_post_render', function() {
            initDynamicSelects();
        });
    }

    /**
     * Inicializácia dynamických selectov
     */
    function initDynamicSelects() {
        const citySelector = getFieldSelector(cityInputId);
        const programSelector = getFieldSelector(programInputId);

        if (!citySelector || !programSelector) {
            console.warn('[SPA] Nemožno nájsť GF polia.');
            return;
        }

        const cityField = document.querySelector(citySelector);
        const programField = document.querySelector(programSelector);

        if (!cityField || !programField) {
            console.warn('[SPA] GF select polia neboli nájdené v DOM.');
            return;
        }

        // Event listener na zmenu mesta
        cityField.addEventListener('change', function() {
            const cityId = this.value;
            
            if (!cityId) {
                resetProgramField(programField);
                return;
            }

            loadPrograms(cityId, programField);
        });

        console.log('[SPA] Dynamické selecty inicializované.');
    }

    /**
     * Načítanie programov cez AJAX
     */
    function loadPrograms(cityId, programField) {
        // Zobrazenie loading stavu
        setLoadingState(programField, true);

        // FormData pre AJAX request
        const formData = new FormData();
        formData.append('action', 'spa_get_programs');
        formData.append('city_id', cityId);
        formData.append('nonce', spaConfig.nonce);

        // Fetch API request
        fetch(spaConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            setLoadingState(programField, false);

            if (data.success && data.data) {
                populateProgramField(programField, data.data);
            } else {
                showError(programField, data.data?.message || 'Chyba pri načítaní programov.');
            }
        })
        .catch(error => {
            setLoadingState(programField, false);
            console.error('[SPA] AJAX error:', error);
            showError(programField, 'Nastala technická chyba.');
        });
    }

    /**
     * Naplnenie select poľa programami
     */
    function populateProgramField(selectElement, programs) {
        // Vyčistenie existujúcich options
        selectElement.innerHTML = '<option value="">Vyberte program</option>';

        // Pridanie nových options
        programs.forEach(program => {
            const option = document.createElement('option');
            option.value = program.id;
            option.textContent = program.name;
            selectElement.appendChild(option);
        });

        // Aktivácia poľa
        selectElement.disabled = false;
    }

    /**
     * Reset program fieldu
     */
    function resetProgramField(selectElement) {
        selectElement.innerHTML = '<option value="">Najprv vyberte mesto</option>';
        selectElement.disabled = true;
    }

    /**
     * Zobrazenie loading stavu
     */
    function setLoadingState(selectElement, isLoading) {
        if (isLoading) {
            selectElement.innerHTML = '<option value="">Načítavam...</option>';
            selectElement.disabled = true;
        }
    }

    /**
     * Zobrazenie chybovej správy
     */
    function showError(selectElement, message) {
        selectElement.innerHTML = `<option value="">${message}</option>`;
        selectElement.disabled = true;
        console.error('[SPA]', message);
    }

})();