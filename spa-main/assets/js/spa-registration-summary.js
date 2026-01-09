/**
 * SPA Registration – Dynamické selecty (City → Program)
 * FINAL VERSION s načítaním miest
 */

(function() {
    'use strict';

    if (typeof spaConfig === 'undefined') {
        console.error('[SPA] spaConfig nie je definovaný.');
        return;
    }

    const cityInputId = spaConfig.fields.spa_city;
    const programInputId = spaConfig.fields.spa_program;

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

    document.addEventListener('DOMContentLoaded', function() {
        initDynamicSelects();
    });

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('gform_post_render', function() {
            initDynamicSelects();
        });
    }

    function initDynamicSelects() {
        const citySelector = getFieldSelector(cityInputId);
        const programSelector = getFieldSelector(programInputId);

        if (!citySelector || !programSelector) {
            console.warn('[SPA] Nemožno vytvoriť selektory pre GF polia.');
            return;
        }

        const cityField = document.querySelector(citySelector);
        const programField = document.querySelector(programSelector);

        if (!cityField || !programField) {
            console.warn('[SPA] GF select polia neboli nájdené v DOM.');
            return;
        }

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
            const selectedOption = this.options[this.selectedIndex];
            
            if (!selectedOption || !selectedOption.value) {
                // Reset pri zrušení výberu
                hideAllEmailFields();
                return;
            }
            
            // Získaj target (child/youth/adult)
            const target = selectedOption.getAttribute('data-target');
            
            if (target) {
                // Označ radio button + zobraz email pole
                handleParticipantTypeSelection(target);
            }
        });

        console.log('[SPA] Dynamické selecty inicializované.');
    }

    /**
     * Načítanie miest cez AJAX
     */
    function loadCities(cityField) {
        setLoadingState(cityField, true, 'Načítavam mestá...');

        const formData = new FormData();
        formData.append('action', 'spa_get_cities');

        fetch(spaConfig.ajaxUrl, {
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

        fetch(spaConfig.ajaxUrl, {
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
        
        if (ageMin) {
            autoFillChildEmail(ageMin);
        }
    }
    /**
     * Naplnenie program fieldu
     */
    function populateProgramField(selectElement, programs) {
        selectElement.innerHTML = '<option value="">Vyberte program</option>';
        programs.forEach(program => {
            const option = document.createElement('option');
            option.value = program.id;
            option.textContent = program.label;
            
            // Pridaj data atribúty pre JS logiku
            option.setAttribute('data-target', program.target);
            if (program.age_min) option.setAttribute('data-age-min', program.age_min);
            if (program.age_max) option.setAttribute('data-age-max', program.age_max);
            
            selectElement.appendChild(option);
        });
        selectElement.disabled = false;
        
        // Nastav listenery pre meno/priezvisko (blur)
        setupNameFieldListeners();
    }

    /**
     * Skrytie všetkých emailových polí
     */
    function hideAllEmailFields() {
        const childEmailInput = document.querySelector('input[name="input_15"]');
        const adultEmailInput = document.querySelector('input[name="input_16"]');
        
        if (childEmailInput) {
            const wrapper = childEmailInput.closest('.gfield');
            if (wrapper) wrapper.style.display = 'none';
        }
        if (adultEmailInput) {
            const wrapper = adultEmailInput.closest('.gfield');
            if (wrapper) wrapper.style.display = 'none';
        }
    }
    
    /**
     * Automatická voľba typu účastníka + riadenie viditeľnosti emailových polí
     */
    function handleParticipantTypeSelection(target) {
        console.log('[SPA] handleParticipantTypeSelection called with target:', target);
        
        // 1. RADIO BUTTONY pre typ účastníka
        const radioButtons = document.querySelectorAll('input[type="radio"]');
        
        let selectedRadio = null;
        
        radioButtons.forEach(radio => {
            // Získaj parent label alebo closest label element
            const labelElement = radio.closest('label') || radio.parentElement;
            if (!labelElement) return;
            
            const label = labelElement.textContent.trim().toLowerCase();
            const isChildOption = label.includes('dieťa') || label.includes('diet') || label.includes('mladš');
            const isAdultOption = label.includes('dospel') || label.includes('18+') || label.includes('adult');
            
            if (target === 'child' || target === 'youth') {
                if (isChildOption) {
                    radio.checked = true;
                    radio.disabled = false;
                    selectedRadio = radio;
                } else if (isAdultOption) {
                    radio.checked = false;
                    radio.disabled = true;
                }
            } else if (target === 'adult') {
                if (isAdultOption) {
                    radio.checked = true;
                    radio.disabled = false;
                    selectedRadio = radio;
                } else if (isChildOption) {
                    radio.checked = false;
                    radio.disabled = true;
                }
            }
        });
        
        // Trigger change event na označenom radio buttonu
        if (selectedRadio) {
            const event = new Event('change', { bubbles: true });
            selectedRadio.dispatchEvent(event);
        }
        
        // 2. EMAILOVÉ POLIA – priame riadenie viditeľnosti
        handleEmailFieldVisibility(target);
    }

    /**
     * Riadenie viditeľnosti emailových polí na základe veku programu
     * CHILD/YOUTH → input_15 (auto-generovaný)
     * ADULT → input_16 (povinný)
     */
    function handleEmailFieldVisibility(target) {
        console.log('[SPA] handleEmailFieldVisibility called with target:', target);
        
        // Nájdi emailové inputy podľa name atribútu
        const childEmailInput = document.querySelector('input[name="input_15"]');
        const adultEmailInput = document.querySelector('input[name="input_16"]');
        
        if (!childEmailInput || !adultEmailInput) {
            console.warn('[SPA] Email inputs not found in DOM');
            return;
        }
        
        // Získaj GF field wrappery (closest .gfield)
        const childEmailWrapper = childEmailInput.closest('.gfield');
        const adultEmailWrapper = adultEmailInput.closest('.gfield');
        
        if (!childEmailWrapper || !adultEmailWrapper) {
            console.warn('[SPA] Email field wrappers not found');
            return;
        }
        
        // CHILD/YOUTH programy
        if (target === 'child' || target === 'youth') {
            // Zobraz CHILD pole (input_15)
            childEmailWrapper.style.display = '';
            childEmailWrapper.classList.remove('gfield_visibility_hidden');
            childEmailInput.removeAttribute('disabled');
            
            // Skry ADULT pole (input_16)
            adultEmailWrapper.style.display = 'none';
            adultEmailWrapper.classList.add('gfield_visibility_hidden');
            adultEmailInput.setAttribute('disabled', 'disabled');
            adultEmailInput.removeAttribute('required');
            adultEmailInput.setAttribute('aria-required', 'false');
            
            console.log('[SPA] CHILD email field visible, ADULT hidden');
        }
        // ADULT programy
        else if (target === 'adult') {
            // Skry CHILD pole (input_15)
            childEmailWrapper.style.display = 'none';
            childEmailWrapper.classList.add('gfield_visibility_hidden');
            childEmailInput.setAttribute('disabled', 'disabled');
            
            // Zobraz ADULT pole (input_16)
            adultEmailWrapper.style.display = '';
            adultEmailWrapper.classList.remove('gfield_visibility_hidden');
            adultEmailInput.removeAttribute('disabled');
            adultEmailInput.setAttribute('required', 'required');
            adultEmailInput.setAttribute('aria-required', 'true');
            
            console.log('[SPA] ADULT email field visible, CHILD hidden');
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
    function removeDiacritics(str) {
        const diacriticsMap = {
            'á': 'a', 'ä': 'a', 'č': 'c', 'ď': 'd', 'é': 'e',
            'í': 'i', 'ľ': 'l', 'ĺ': 'l', 'ň': 'n', 'ó': 'o',
            'ô': 'o', 'ŕ': 'r', 'š': 's', 'ť': 't', 'ú': 'u',
            'ý': 'y', 'ž': 'z'
        };
        
        return str.replace(/[^\w\s]/g, char => diacriticsMap[char] || char)
                  .toLowerCase()
                  .replace(/[^a-z0-9]/g, '');
    }

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
        
        const firstPart = removeDiacritics(firstName);
        const lastPart = removeDiacritics(lastName);
        
        return `${firstPart}.${lastPart}@piaseckyacademy.sk`;
    }

    
    /**
     * Automatické vyplnenie e-mailu pre CHILD
     */
    function autoFillChildEmail(ageMin) {
        if (!ageMin || ageMin >= 18) {
            return;
        }
        
        const childEmailInput = document.querySelector('input[name="input_15"]');
        
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