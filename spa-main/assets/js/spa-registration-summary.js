/**
 * SPA Registration – Auto-fill email pre CHILD
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

        // Event listener na zmenu mesta
        cityField.addEventListener('change', function() {
            const cityId = this.value;
            
            if (!cityId) {
                // Reset program field ak nie je mesto vybrané
                if (programField) {
                    programField.value = '';
                }
                return;
            }

            // ⭐ PROGRAMY SA PREFILTRUJÚ SERVER-SIDE cez gform_pre_render
            // Žiadny AJAX load nie je potrebný
            
            // Len vyprázdni program select (užívateľ musí vybrať znovu)
            if (programField) {
                programField.value = '';
            }
        });

        // Event listener na zmenu programu
        programField.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (!selectedOption || !selectedOption.value) {
                return;
            }
            
            // Nastav listenery pre auto-fill emailu
            setupNameFieldListeners();
        });

        console.log('[SPA] Auto-fill email inicializovaný.');
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
    function autoFillChildEmail() {
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