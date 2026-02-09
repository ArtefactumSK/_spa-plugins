/**
 * SPA Registration – Dynamické selecty (City → Program)
 */

(function() {
    'use strict';

    if (typeof spaRegistrationConfig === 'undefined') {
        console.error('[SPA] spaRegistrationConfig nie je definovaný.');
        return;
    }

    const cityInputId = spaRegistrationConfig.fields.spa_city;
    const programInputId = spaRegistrationConfig.fields.spa_program;

    if (!cityInputId || !programInputId) {
        console.error('[SPA] Chýbajúce field ID v spa-config.');
        return;
    }

    function getFieldSelector(inputId) {
        const fieldNum = inputId.replace('input_', '');
        const formElement = document.querySelector('.gform_wrapper form');
        if (!formElement) return null;
        
        const formId = formElement.id.replace('gform_', '');
        return `#input_${formId}_${fieldNum}`;
    }

    // ✅ Robustné nájdenie správneho GF formu (po pagebreaku môže byť DOM prerender)
    function getGfFormByFieldName(fieldName) {
        const forms = Array.from(document.querySelectorAll('.gform_wrapper form'));
        for (const f of forms) {
            if (f.querySelector(`[name="${fieldName}"]`)) {
                return f;
            }
        }
        return null;
    }

    function getFieldElByInputId(inputId) {
        if (!inputId) return null;
        const fieldNum = String(inputId).replace('input_', '');
        const fieldName = `input_${fieldNum}`;
        const form = getGfFormByFieldName(fieldName);
        if (!form) return null;
        return form.querySelector(`[name="${fieldName}"]`);
    }


    document.addEventListener('DOMContentLoaded', function() {
        initDynamicSelects();
    });

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('gform_post_render', function() {
            initDynamicSelects();
        });
    }

    function initDynamicSelects() {
        // ▶ Robustný výber GF polí podľa name="input_X" (odolné voči pagebreak re-renderu)
        const cityField = getFieldElByInputId(cityInputId);
        const programField = getFieldElByInputId(programInputId);

        if (!cityField || !programField) {
            console.warn('[SPA] GF select polia neboli nájdené v DOM (name-based lookup zlyhal).');
            return;
        }


        // ▶ DETECT: je toto pagebreak (re-render) alebo first load?
        const isPagebreak = (window.__spaFormRendered === true);
        window.__spaFormRendered = true;

        // Načítaj mestá pri inicializácii
        loadCities(cityField);

        // Event listener na zmenu mesta
        cityField.addEventListener('change', function() {
            const cityId = this.value;
        
            if (!cityId) {
                resetProgramField(programField);
                return;
            }
        
            loadPrograms(cityId, programField);
        });
        

        // Event listener na zmenu programu
        programField.addEventListener('change', function() {
            const programId = this.value;
        
            // ▶ STORE program to BACKUP
            const programBackupId = spaRegistrationConfig.fields.spa_program_backup;
            if (programBackupId) {
                const selector = getFieldSelector(programBackupId);
                const backupField = selector ? document.querySelector(selector) : null;
                if (backupField) {
                    backupField.value = programId;
                    console.log('[SPA] Program backup stored:', programId);
                }
            }
        
            const selectedOption = this.options[this.selectedIndex];
            if (!selectedOption || !selectedOption.value) {
                return;
            }
        
            setupNameFieldListeners();
        });        
        // ▶ RESTORE: ONLY after pagebreak (not on first load)
        if (isPagebreak) {
            const programBackupId = spaRegistrationConfig.fields.spa_program_backup;
            if (programBackupId) {
                const backupSelector = getFieldSelector(programBackupId);
                const backupField = backupSelector ? document.querySelector(backupSelector) : null;

                if (backupField && backupField.value) {
                    const programId = String(backupField.value);
                    console.log('[SPA Restore] Pagebreak detected, restoring program:', programId);

                    // derive city from program
                    const programCities = spaRegistrationConfig.programCities || {};
                    let derivedCityId = null;

                    Object.entries(programCities).some(([cityId, programIds]) => {
                        if (Array.isArray(programIds) && programIds.map(String).includes(programId)) {
                            derivedCityId = cityId;
                            return true;
                        }
                        return false;
                    });

                    // load programs + restore (city sa automaticky nastaví v populateCityField)
                    if (derivedCityId) {
                        // 1️⃣ nastav mesto
                        cityField.value = derivedCityId;
                        cityField.dispatchEvent(new Event('change', { bubbles: true }));
                    
                        // 2️⃣ loadPrograms sa spustí z change handlera mesta
                    }
                    
                }
            }
        }

        console.log('[SPA] Dynamické selecty inicializované.');
    }

    /**
     * Načítanie miest cez AJAX
     */
    function loadCities(cityField) {
        setLoadingState(cityField, true, 'Načítavam mestá...');

        const formData = new FormData();
        formData.append('action', 'spa_get_cities');

        fetch(spaRegistrationConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                populateCityField(cityField, data.data);
            } else {
                showError(cityField, 'Chyba pri načítaní miest.');
            }
        })
        .catch(error => {
            console.error('[SPA] Cities AJAX error:', error);
            showError(cityField, 'Nastala technická chyba.');
        });
    }

    /**
     * Načítanie programov cez AJAX
     */
    function loadPrograms(cityId, programField) {
        setLoadingState(programField, true, 'Načítavam programy...');

        const formData = new FormData();
        formData.append('action', 'spa_get_programs');
        formData.append('city_id', cityId);

        fetch(spaRegistrationConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                populateProgramField(programField, data.data);
            } else {
                showError(programField, data.data?.message || 'Chyba pri načítaní programov.');
            }
        })
        .catch(error => {
            console.error('[SPA] Programs AJAX error:', error);
            showError(programField, 'Nastala technická chyba.');
        });
    }

    /**
     * Naplnenie city fieldu
     */
    function populateCityField(selectElement, cities) {
        selectElement.innerHTML = '<option value="">Vyberte mesto</option>';

        cities.forEach(city => {
            const option = document.createElement('option');
            option.value = city.id;
            option.textContent = city.name;
            selectElement.appendChild(option);
        });

        selectElement.disabled = false;        
    }

    /**
     * Naplnenie program fieldu
     * Program je jediný zdroj pravdy – mesto je odvodené
     */
    function populateProgramField(selectElement, programs) {
        selectElement.innerHTML = '<option value="">Vyberte program</option>';
    
        programs.forEach(program => {
            const option = document.createElement('option');
            option.value = String(program.id);
            option.textContent = program.label;
    
            if (program.target) option.setAttribute('data-target', program.target);
            if (program.age_min) option.setAttribute('data-age-min', program.age_min);
            if (program.age_max) option.setAttribute('data-age-max', program.age_max);
    
            selectElement.appendChild(option);
        });
    
        selectElement.disabled = false;
    
        // ▶ Restore program z BACKUPU
        const backupId = spaRegistrationConfig.fields.spa_program_backup;
        if (backupId) {
            const sel = getFieldSelector(backupId);
            const backupField = sel ? document.querySelector(sel) : null;
    
            if (
                backupField &&
                backupField.value &&
                selectElement.querySelector(`option[value="${backupField.value}"]`)
            ) {
                selectElement.value = String(backupField.value);
                selectElement.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }
    


    /**
     * Nastavenie listenerov na blur meno/priezvisko
     */
    function setupNameFieldListeners() {
        const firstNameField = document.querySelector('input[name*="meno"], input[placeholder*="meno"]');
        const lastNameField = document.querySelector('input[name*="priezvisko"], input[placeholder*="priezvisko"]');
        
        if (!firstNameField || !lastNameField) {
            return;
        }
        
        [firstNameField, lastNameField].forEach(field => {
            // Odstráň starý listener (ak existuje)
            field.removeEventListener('blur', handleNameBlur);
            // Pridaj nový
            field.addEventListener('blur', handleNameBlur);
        });
    }

    /**
     * Handler pre blur na meno/priezvisko
     */
    function handleNameBlur() {
        const programField = document.querySelector(getFieldSelector(programInputId));
        
        if (!programField || !programField.value) {
            return;
        }
        
        const selectedOption = programField.options[programField.selectedIndex];
        const ageMin = parseInt(selectedOption.getAttribute('data-age-min'));
        
        if (ageMin && ageMin < 18) {
            autoFillChildEmail();
        }
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
    function setLoadingState(selectElement, isLoading, message = 'Načítavam...') {
        if (isLoading) {
            selectElement.innerHTML = `<option value="">${message}</option>`;
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

    /**
     * Odstránenie diakritiky
     */
    /* function removeDiacritics(str) {
        const diacriticsMap = {
            'á': 'a', 'ä': 'a', 'č': 'c', 'ď': 'd', 'é': 'e',
            'í': 'i', 'ľ': 'l', 'ĺ': 'l', 'ň': 'n', 'ó': 'o',
            'ô': 'o', 'ŕ': 'r', 'š': 's', 'ť': 't', 'ú': 'u',
            'ý': 'y', 'ž': 'z'
        };
        
        return str.replace(/[^\w\s]/g, char => diacriticsMap[char] || char)
                  .toLowerCase()
                  .replace(/[^a-z0-9]/g, '');
    } */

    /**
     * Generovanie e-mailu pre CHILD
     */
    function generateChildEmail() {
        const firstNameField = document.querySelector('input[name*="meno"], input[placeholder*="meno"]');
        const lastNameField = document.querySelector('input[name*="priezvisko"], input[placeholder*="priezvisko"]');
        
        if (!firstNameField || !lastNameField) {
            return null;
        }
        
        const firstName = firstNameField.value.trim();
        const lastName = lastNameField.value.trim();
        
        if (!firstName || !lastName) {
            return null;
        }
        
        const firstPart = spa_remove_diacritics(firstName);
        const lastPart = spa_remove_diacritics(lastName);
        
        return `${firstPart}.${lastPart}@piaseckyacademy.sk`;
    }

    /**
     * Automatické vyplnenie e-mailu pre CHILD
     */
    function autoFillChildEmail() {
        const childEmailInput = document.querySelector('input[name="spa_client_email"]');
        
        if (!childEmailInput || childEmailInput.value.trim() !== '') {
            return;
        }
        
        const generatedEmail = generateChildEmail();
        
        if (generatedEmail) {
            childEmailInput.value = generatedEmail;
            console.log('[SPA] E-mail pre CHILD vygenerovaný:', generatedEmail);
        }
    }
})();