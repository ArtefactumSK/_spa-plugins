/**
 * SPA Infobox Wizard – Frontend logika
 * CENTRALIZOVANÝ STATE MANAGEMENT
 */

// ⭐ GLOBÁLNE PREMENNÉ (prístupné všetkým súborom)
window.spaFormState = {
    city: false,
    program: false,
    frequency: false
};

window.initialized = false;
window.listenersAttached = false;
window.lastCapacityFree = null;
window.currentState = 0;
window.wizardData = {
    program_id: null,
    city_name: '',
    city_slug: '',
    program_name: '',
    program_age: ''
};

window.spaErrorState = {
    invalidCity: false,
    invalidProgram: false,
    errorType: null  // 'state' | 'validation'
};

    /**
     * ERRORBOX: Centrálne zobrazenie stavu výberu
     */
    window.updateErrorBox = function() {
        const state = window.currentState || 0;
        
        let errorBox = document.querySelector('.gform_validation_errors');
        
        if (state === 2 && !window.spaErrorState.invalidCity && !window.spaErrorState.invalidProgram) {
            if (errorBox) {
                errorBox.style.display = 'none';
            }
            return;
        }
        
        let message = '';
        
        if (window.spaErrorState.invalidCity) {
            const urlParams = new URLSearchParams(window.location.search);
            const cityParam = urlParams.get('city');
            message = '<h2 class="gform_submission_error">⛔ Neplatné mesto v odkaze</h2><p>Mesto "<span>' + cityParam + '</span>" nebolo nájdené. Prosím, vyberte mesto zo zoznamu.</p>';
            window.spaErrorState.errorType = 'state';
        } else if (window.spaErrorState.invalidProgram) {
            const urlParams = new URLSearchParams(window.location.search);
            const programParam = urlParams.get('program');
            message = '<h2 class="gform_submission_error">⛔ Neplatný program v odkaze</h2><p>Program s ID "<span>' + programParam + '</span>" nebol nájdený alebo nie je dostupný v zvolenom meste. Prosím, vyberte program zo zoznamu.</p>';
            window.spaErrorState.errorType = 'state';
        } else if (state === 0) {
            message = '<h2 class="gform_submission_error">⛔ Vyberte mesto</h2><p>Prosím, vyberte mesto zo zoznamu.</p>';
            window.spaErrorState.errorType = 'state';
        } else if (state === 1) {
            message = '<h2 class="gform_submission_error">⛔ Vyberte tréningový program</h2><p>Prosím, vyberte tréningový program zo zoznamu.</p>';
            window.spaErrorState.errorType = 'state';
        }
        if (!errorBox) {
            const gformBody = document.querySelector('.gform_body');
            if (gformBody) {
                errorBox = document.createElement('div');
                errorBox.className = 'gform_validation_errors';
                gformBody.insertBefore(errorBox, gformBody.firstChild);
            } else {
                return;
            }
        }
        
        errorBox.innerHTML = message;
        errorBox.style.display = 'block';
    };


    /**
     * CENTRÁLNE URČENIE CASE
     */
    window.determineCaseState = function() {
        if (!window.wizardData.city_name) {
            return 0;
        }
        if (window.wizardData.city_name && !window.wizardData.program_name) {
            return 1;
        }
        if (window.wizardData.city_name && window.wizardData.program_name) {
            return 2;
        }
        return 0;
    };

    /**
     * CENTRÁLNY UPDATE STAVU
     */
    window.updateInfoboxState = function() {
        const newState = determineCaseState();
        
        console.log('[SPA Infobox] State transition:', {
            from: window.currentState,
            to: newState,
            wizardData: window.wizardData
        });
        
        window.currentState = newState;
        window.updateErrorBox();
        window.loadInfoboxContent(window.currentState);
    };

    
    /**
     * Obnovenie wizardData z hidden backup polí
     */
    window.restoreWizardData = function() {
        console.log('[SPA Restore] ========== START ==========');
        
        
        
        console.log('[SPA Restore] Backup fields:', {
            cityBackupValue: cityBackup?.value,
            programBackupValue: programBackup?.value
        });
        
        // Ak nemáme žiadne backup hodnoty, ukonči
        if (!cityBackup?.value && !programBackup?.value) {
            console.log('[SPA Restore] No backup values, skipping');
            return;
        }
        
        // Počkaj na načítanie selectov (GF AJAX)
        let attempts = 0;
        const maxAttempts = 20; // 20 * 100ms = 2 sekundy max
        
        const waitForSelects = setInterval(() => {
            attempts++;
            
            const citySelect = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
            const programSelect = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
            
            // Skontroluj, či majú selecty options
            const cityHasOptions = citySelect && citySelect.options.length > 1;
            const programHasOptions = programSelect && programSelect.options.length > 1;
            
            console.log(`[SPA Restore] Attempt ${attempts}/${maxAttempts}:`, {
                cityExists: !!citySelect,
                cityOptionsCount: citySelect?.options.length,
                programExists: !!programSelect,
                programOptionsCount: programSelect?.options.length
            });
            
            // Ak máme options ALEBO sme skúšali príliš dlho
            if ((cityHasOptions && programHasOptions) || attempts >= maxAttempts) {
                clearInterval(waitForSelects);
                
                if (!cityHasOptions || !programHasOptions) {
                    console.error('[SPA Restore] TIMEOUT - selects still not ready');
                    return;
                }
                
                // OBNOV MESTO
                if (cityBackup?.value && citySelect) {
                    citySelect.value = cityBackup.value;
                    
                    const selectedOption = citySelect.options[citySelect.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        window.wizardData.city_name = selectedOption.text;
                        window.spaFormState.city = true;
                        window.currentState = 1;
                        
                        console.log('[SPA Restore] ✅ City RESTORED:', window.wizardData.city_name);
                    } else {
                        console.error('[SPA Restore] ❌ City restore FAILED - no option found for value:', cityBackup.value);
                    }
                }
                
                // OBNOV PROGRAM
                if (programBackup?.value && programSelect) {
                    programSelect.value = programBackup.value;
                    
                    const selectedOption = programSelect.options[programSelect.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        window.wizardData.program_name = selectedOption.text;
                        window.wizardData.program_id = selectedOption.getAttribute('data-program-id') || selectedOption.value;
                        window.spaFormState.program = true;
                        
                        // Parsuj vek
                        const ageMatch = selectedOption.text.match(/(\d+)[–-](\d+)/);
                        if (ageMatch) {
                            window.wizardData.program_age = ageMatch[1] + '–' + ageMatch[2];
                        } else {
                            const agePlusMatch = selectedOption.text.match(/(\d+)\+/);
                            if (agePlusMatch) {
                                window.wizardData.program_age = agePlusMatch[1] + '+';
                            }
                        }
                        
                        window.currentState = 2;
                        
                        console.log('[SPA Restore] ✅ Program RESTORED:', window.wizardData.program_name);
                    } else {
                        console.error('[SPA Restore] ❌ Program restore FAILED - no option found for value:', programBackup.value);
                    }
                }
                
                // Načítaj infobox ak máme dáta
                if (window.currentState > 0) {
                    console.log('[SPA Restore] Loading infobox for state:', currentState);
                    window.loadInfoboxContent(window.currentState);
                    
                } else {
                    console.warn('[SPA Restore] ⚠️ currentState is 0, NOT loading infobox');
                }
                
                console.log('[SPA Restore] ========== DONE ==========', {
                    currentState,
                    wizardData,
                    spaFormState: window.spaFormState
                });
            }
        }, 100); // Skúšaj každých 100ms
    };

    /**
     * Sledovanie zmien vo formulári
     */
    window.watchFormChanges = function() {
        // ⭐ GUARD: Container musí existovať
        if (!document.getElementById('spa-infobox-container')) {
            return;
        }
        
        // ⭐ GUARD: Zabraň duplicitným event listenerom
        if (window.listenersAttached) {
            console.log('[SPA Infobox] Listeners already attached, skipping');
            return;
        }
        
        // ⭐ DELEGOVANÝ listener pre mesto (funguje aj po GF rerenderi)
        document.addEventListener('change', function(e) {
            if (e.target.name === 'input_1') {
                console.log('[SPA GET DEBUG] Change event triggered on input_1');
                console.log('[SPA GET DEBUG] Change event value:', e.target.value);
                console.log('[SPA GET DEBUG] Change event triggered by:', e.isTrusted ? 'USER' : 'SCRIPT');
            }
            console.log('[SPA DEBUG] Change event:', e.target.name, e.target.value);
            // Skontroluj či ide o city field
            if (e.target.name === 'input_1') {
                console.log('[SPA DEBUG] City field detected!');
                const cityField = e.target;
                const selectedOption = cityField.options[cityField.selectedIndex];
                const selectedCityName = selectedOption ? selectedOption.text.trim() : '';
                
                console.log('[SPA City Change] Selected:', selectedCityName);
                
                // ⭐ AK ide o GET load, NERESETUJ program (aplikuje sa neskôr)
                if (window.isApplyingGetParams) {
                    console.log('[SPA City Change] GET loading - skipping program reset');
                    
                    // Nastavíme city_name ale NERESETNEME program
                    if (cityField.value && cityField.value !== '0' && cityField.value !== '') {
                        // ⭐ NEPREPIS city_name ak už je nastavené z GET
                        if (!window.wizardData.city_name) {
                            window.wizardData.city_name = selectedCityName;
                            window.wizardData.city_slug = spa_remove_diacritics(selectedCityName);
                        }
                        window.spaFormState.city = true;
                        window.currentState = 1;
                    }
                    
                    // ⭐ FILTRUJ options (potrebné pre program load)
                    if (selectedCityName && selectedCityName.trim() !== '') {
                        window.filterProgramsByCity(selectedCityName);
                    }
                    
                    return; // ⭐ ZASTAV, nepokračuj v reset logike
                }
                
                // ⭐ NORMÁLNY USER CHANGE: RESETUJ PROGRAM
                window.wizardData.program_name = '';
                window.wizardData.program_id = null;
                window.wizardData.program_age = '';
                window.wizardData.frequency = '';
                window.spaFormState.program = false;
                window.spaFormState.frequency = false;
                
                // Vyčisti program select
                const programField = document.querySelector('[name="input_2"]');
                if (programField) {
                    programField.value = '';
                    
                    // ⭐ FILTRUJ options podľa mesta
                    if (selectedCityName && selectedCityName.trim() !== '') {
                        window.filterProgramsByCity(selectedCityName);
                    }
                }
                
                // VYČISTI frekvenčný selector
                const frequencySelector = document.querySelector('.spa-frequency-selector');
                if (frequencySelector) {
                    frequencySelector.innerHTML = '';
                }
                
                // ⭐ VYČISTI VŠETKY POLIA V SEKCIÁCH
                window.filterProgramsByCity(selectedCityName);
                
                if (cityField.value && cityField.value !== '0' && cityField.value !== '') {
                    // ⭐ V normálnom flow (nie GET) vždy prepíš
                    window.wizardData.city_name = selectedCityName;
                    window.wizardData.city_slug = spa_remove_diacritics(selectedCityName);
                    window.spaFormState.city = true;
                    window.currentState = 1;
                    window.spaErrorState.invalidCity = false;
                
                    // ⭐ SKRY STATE chyby (ale NIE VALIDATION chyby)
                    if (window.spaErrorState.errorType === 'state') {
                        window.spaErrorState.errorType = null;
                        const errorBox = document.querySelector('.gform_validation_errors');
                        if (errorBox) {
                            errorBox.style.display = 'none';
                        }
                        }
                } else {
                    // Úplné vymazanie mesta
                    window.wizardData.city_name = '';
                    window.spaFormState.city = false;
                    window.currentState = 0;
                }
                
                    window.loadInfoboxContent(window.currentState);
                
                window.updateSectionVisibility();
                window.updatePriceSummary(); // ⭐ AKTUALIZUJ PREHĽAD
            }
        });
        
        // ⭐ DEFINUJ programField PRED použitím!
        const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
        
        // Sleduj zmenu programu
        if (programField) {
            programField.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                
                console.log('[SPA Infobox] Program changed - value:', this.value);
                console.log('[SPA Infobox] Program changed - text:', selectedOption.text);
                
                // ⭐ VYČISTI PREHĽAD pri zmene programu
                const summaryContainer = document.querySelector('.spa-price-summary');
                if (summaryContainer) {
                    summaryContainer.innerHTML = '';
                    console.log('[SPA] Cleared price summary on program change');
                }
                
                if (this.value) {
                    window.wizardData.program_name = selectedOption.text;
                    window.wizardData.program_id = selectedOption.getAttribute('data-program-id') || this.value;
                    window.spaErrorState.invalidProgram = false;
                    
                    // ⭐ SKRY STATE chyby (ale NIE VALIDATION chyby)
                    if (window.spaErrorState.errorType === 'state') {
                        window.spaErrorState.errorType = null;
                        const errorBox = document.querySelector('.gform_validation_errors');
                        if (errorBox) {
                            errorBox.style.display = 'none';
                        }
                    }
                    
                    console.log('[SPA Infobox] Program ID:', window.wizardData.program_id);
                    
                    // RESET veku pred novým parsovaním
                    window.wizardData.program_age = '';
                    
                    // Parsuj vek z názvu programu
                    const ageMatch = selectedOption.text.match(/(\d+)[–-](\d+)/);
                    if (ageMatch) {
                        window.wizardData.program_age = ageMatch[1] + '–' + ageMatch[2];
                    } else {
                        const agePlusMatch = selectedOption.text.match(/(\d+)\+/);
                        if (agePlusMatch) {
                            window.wizardData.program_age = agePlusMatch[1] + '+';
                        }
                    }
                    
                    window.spaFormState.program = true;
                    window.currentState = 2;   
                } else {
                    // ⭐ RESET PROGRAMU
                    window.wizardData.program_name = '';
                    window.wizardData.program_id = null;
                    window.wizardData.program_age = '';
                    window.spaFormState.program = false;
                    window.spaFormState.frequency = false;
                    window.currentState = window.wizardData.city_name ? 1 : 0;
                    
                    // VYČISTI frekvenčný selector
                    const frequencySelector = document.querySelector('.spa-frequency-selector');
                    if (frequencySelector) {
                        frequencySelector.innerHTML = '';
                    }
                    
                    // ⭐ VYČISTI POLIA
                    window.filterProgramsByCity(selectedCityName);
                }
                
                window.loadInfoboxContent(window.currentState);
                
                window.updateSectionVisibility();
            });
        } else {
            console.error('[SPA Infobox] Program field NOT FOUND!');
        }
        
        
        // ⭐ LISTENER na Gravity Forms page change (Next/Back buttony)
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('gform_page_loaded', function(event, form_id, current_page) {
                console.log('[SPA] GF Page loaded:', current_page, 'form:', form_id);
                
                // Počkaj kým sa stránka renderuje
                setTimeout(() => {
                    window.updatePriceSummary();
                }, 200);
            });
        }

        // ⭐ OZNAČ, že listenery sú pripojené
        window.listenersAttached = true;
        console.log('[SPA Infobox] Event listeners attached');
    };  

    
    
    /**
     * Načítanie obsahu infoboxu cez AJAX
     */
    window.loadInfoboxContent = function(state) {
        // ⭐ GUARD: Container musí existovať
        if (!document.getElementById('spa-infobox-container')) {
            return;
        }
        
        console.log('[SPA Infobox] Loading state:', state, window.wizardData);
        window.showLoader();

        const formData = new FormData();
        formData.append('action', 'spa_get_infobox_content');
        formData.append('program_id', window.wizardData.program_id);
        formData.append('state', state);
        formData.append('city_name', window.wizardData.city_name);
        formData.append('city_slug', window.wizardData.city_slug);
        formData.append('program_name', window.wizardData.program_name);
        formData.append('program_age', window.wizardData.program_age);

        fetch(spaConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            console.log('[SPA Infobox] AJAX Response:', data);
            
            if (data.success) {
                window.renderInfobox(data.data, data.data.icons, data.data.capacity_free, data.data.price);
            } else {
                console.error('[SPA Infobox] Chyba:', data.data?.message);
                window.hideLoader();
            }
        })
        .catch(error => {
            console.error('[SPA Infobox] AJAX error:', error);
            window.hideLoader();
        });
    };

    /**
 * Helper: Odstránenie diakritiky (client-side normalizácia)
 * Zhodné s WordPress sanitize_title() behavior
 */
window.spa_remove_diacritics = function(str) {
    const diacriticsMap = {
        'á':'a','ä':'a','č':'c','ď':'d','é':'e','ě':'e','í':'i','ľ':'l','ĺ':'l',
        'ň':'n','ó':'o','ô':'o','ŕ':'r','š':'s','ť':'t','ú':'u','ů':'u','ý':'y','ž':'z',
        'Á':'a','Ä':'a','Č':'c','Ď':'d','É':'e','Ě':'e','Í':'i','Ľ':'l','Ĺ':'l',
        'Ň':'n','Ó':'o','Ô':'o','Ŕ':'r','Š':'s','Ť':'t','Ú':'u','Ů':'u','Ý':'y','Ž':'z'
    };
    
    return str.toLowerCase().split('').map(char => diacriticsMap[char] || char).join('');
};
    /**
 * Aplikuj GET parametre do formulára
 */
    window.applyGetParams = function() {
        const urlParams = new URLSearchParams(window.location.search);
        let cityParam = urlParams.get('city');
        const programParam = urlParams.get('program');
        const frequencyParam = urlParams.get('spa_frequency');
        
        // ⭐ Normalizuj cityParam (odstráň diakritiku)
        if (cityParam) {
            cityParam = spa_remove_diacritics(cityParam);
            console.log('[SPA GET] Normalized city param:', cityParam);
        }
        
        if (!cityParam && !programParam && !frequencyParam) {
            console.log('[SPA GET] No GET params found');
            return;
        }
        
        console.log('[SPA GET] Found params:', { cityParam, programParam, frequencyParam });
        
        // ⭐ STAVOVÝ GUARD: Sleduje fázy GET aplikácie
        if (!window.spaGFGetState) {
            window.spaGFGetState = {
                cityApplied: false,
                programApplied: false
            };
        }

        // Ak už bola aplikovaná city FÁZA a v URL nie je program, zastav
        if (window.spaGFGetState.cityApplied && !programParam) {
            console.log('[SPA GET] City already applied, no program in URL - skipping');
            return;
        }

        // Ak už boli obe fázy aplikované, zastav
        if (window.spaGFGetState.cityApplied && window.spaGFGetState.programApplied) {
            console.log('[SPA GET] Both city and program already applied - skipping');
            return;
        }

        // ⭐ FLAG: Zabraň resetu programu pri city change z GET
        window.isApplyingGetParams = true;
    
        // ⭐ ODLOŽI POLLING o 500ms (počkaj na GF AJAX load)
        setTimeout(() => {
            let attempts = 0;
            const maxAttempts = 30;

            const checkOptions = setInterval(() => {
        attempts++;
        console.log('[SPA GET DEBUG] ========== START DIAGNOSTICS (attempt ' + attempts + '/' + maxAttempts + ') ==========');
        console.log('[SPA GET DEBUG] URL params:', { cityParam, programParam, frequencyParam });
        console.log('[SPA GET DEBUG] spaConfig.fields:', spaConfig.fields);

        // 1. Skontroluj či existuje select pre mesto
        const citySelect = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
        console.log('[SPA GET DEBUG] City select element:', citySelect);
        console.log('[SPA GET DEBUG] City select exists:', !!citySelect);

        if (citySelect) {
            // 2. Jeho name + id + počet option
            console.log('[SPA GET DEBUG] City select name:', citySelect.name);
            console.log('[SPA GET DEBUG] City select id:', citySelect.id);
            console.log('[SPA GET DEBUG] City select options.length:', citySelect.options.length);
            
            // 3. Zoznam prvých 10 option.value (alebo všetkých)
            const optionsList = Array.from(citySelect.options).slice(0, 10).map(opt => ({
                value: opt.value,
                text: opt.text,
                selected: opt.selected
            }));
            console.log('[SPA GET DEBUG] City select options (first 10):', optionsList);
            
            // 4. Aktuálna hodnota selectu PRED nastavením
            console.log('[SPA GET DEBUG] City select value BEFORE:', citySelect.value);
            
            
        } else {
            console.error('[SPA GET DEBUG] ❌ City select NOT FOUND with selector:', `[name="${spaConfig.fields.spa_city}"]`);
            
            // Skús alternatívne selektory
            const altSelect1 = document.querySelector('[name="input_1"]');
            const altSelect2 = document.querySelector('#input_1_1');
            const altSelect3 = document.querySelector('select[id^="input_1"]');
            
            console.log('[SPA GET DEBUG] Alternative selectors:');
            console.log('  [name="input_1"]:', !!altSelect1);
            console.log('  #input_1_1:', !!altSelect2);
            console.log('  select[id^="input_1"]:', !!altSelect3);
        }

        console.log('[SPA GET DEBUG] ========== END DIAGNOSTICS ==========');
        
        // ⭐ KONTROLA: Ak options neexistujú a ešte je čas, skús znova
        const citySelect2 = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
        if (!citySelect || citySelect.options.length <= 1) {
            if (attempts < maxAttempts) {
                console.log('[SPA GET] Waiting for options... (' + attempts + '/' + maxAttempts + ')');
                return; // Pokračuj v pollingu
            } else {
                console.error('[SPA GET] TIMEOUT - options not ready after ' + attempts + ' attempts');
                clearInterval(checkOptions);
                return;
            }
        }
        
        // ⭐ Options sú ready, zastav polling
        clearInterval(checkOptions);
        console.log('[SPA GET] ✅ Options ready, applying params');
        
        let stateChanged = false;
        
        // MESTO
        if (cityParam) {
            const citySelect = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
            if (citySelect) {
                // Skús nájsť option (case-insensitive porovnanie)
                const options = Array.from(citySelect.options);
                const matchedOption = options.find(opt => {
                    const normalizedOptionText = spa_remove_diacritics(opt.text.trim());
                    const normalizedSearchText = cityParam; // už je normalized
                    return normalizedOptionText === normalizedSearchText;
                });
                
                if (matchedOption) {
                    citySelect.value = matchedOption.value;
                    
                    // ⭐ BACKUP city hodnotu do hidden field (ochrana pred GF refresh)
                    const cityBackup = document.getElementById('spa_city_backup');
                    if (cityBackup) {
                        cityBackup.value = matchedOption.value;
                        console.log('[SPA GET] Backed up city value:', matchedOption.value);
                    }
                    
                    // ⭐ REFRESH Chosen UI (GF uses jQuery Chosen)
                    if (typeof jQuery !== 'undefined') {
                        setTimeout(() => {
                            if (jQuery(citySelect).data('chosen')) {
                                jQuery(citySelect).trigger('chosen:updated');
                                console.log('[SPA GET] Chosen updated for city select');
                            } else {
                                // Fallback: Force native select UI update
                                citySelect.selectedIndex = Array.from(citySelect.options).findIndex(opt => {
                                    const normalizedOptionText = spa_remove_diacritics(opt.text.trim());
                                    return normalizedOptionText === cityParam;
                                });
                                console.log('[SPA GET] Chosen not found, using selectedIndex fallback');
                            }
                        }, 50);
                    }
                    
                    // 4. Aktuálna hodnota selectu PO nastavení
                    console.log('[SPA GET DEBUG] City select value AFTER:', citySelect.value);
                    console.log('[SPA GET DEBUG] Matched option value:', matchedOption.value);
                    console.log('[SPA GET DEBUG] Matched option text:', matchedOption.text);

                    // 5. Overiť či hodnota zostala aj po 500ms
                    setTimeout(() => {
                        const finalValue = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
                        console.log('[SPA GET DEBUG] City select value AFTER 500ms:', finalValue?.value);
                        console.log('[SPA GET DEBUG] City select still exists:', !!finalValue);
                    }, 500);
                    window.wizardData.city_name = matchedOption.text.trim();
                    window.wizardData.city_slug = spa_remove_diacritics(matchedOption.text.trim());
                    window.spaFormState.city = true;
                    window.currentState = 1;
                    stateChanged = true;
                    window.spaGFGetState.cityApplied = true;
                    console.log('[SPA GET] ✅ City applied:', matchedOption.text);

                    // ⭐ TRIGGER CHANGE EVENT
                    citySelect.dispatchEvent(new Event('change', { bubbles: true }));
                    
                    // ⭐ AGGRESSIVE POLLING: Sleduj či GF zresetoval select a opakuj nastavenie
                    let verifyCityAttempts = 0;
                    const maxVerifyCityAttempts = 10;
                    
                    const verifyCityValue = setInterval(() => {
                        verifyCityAttempts++;
                        const currentCitySelect = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
                        const currentValue = currentCitySelect?.value;
                        
                        if (currentValue && currentValue === matchedOption.value) {
                            console.log('[SPA GET] ✅ City value stable:', currentValue);
                            clearInterval(verifyCityValue);
                        } else if (verifyCityAttempts >= maxVerifyCityAttempts) {
                            console.error('[SPA GET] ❌ City value never stabilized');
                            clearInterval(verifyCityValue);
                        } else if (currentCitySelect && currentCitySelect.options.length > 1) {
                            // Options sú ready, ale hodnota chýba - aplikuj znova
                            const freshOption = Array.from(currentCitySelect.options).find(opt => {
                                const normalizedOptionText = spa_remove_diacritics(opt.text.trim());
                                return normalizedOptionText === cityParam;
                            });
                            if (freshOption) {
                                currentCitySelect.value = freshOption.value;
                                if (typeof jQuery !== 'undefined' && jQuery(currentCitySelect).data('chosen')) {
                                    jQuery(currentCitySelect).trigger('chosen:updated');
                                }
                                console.log('[SPA GET] Re-applied city value (attempt ' + verifyCityAttempts + ')');
                            }
                        }
                    }, 200); // Skúšaj každých 200ms
                    
                } else {
                    console.warn('[SPA GET] City option not found:', cityParam);
                    window.spaErrorState.invalidCity = true;
                    window.currentState = 0;
                    window.updateErrorBox();
                }
            }
        }
        

        // ⭐ PROGRAM - aplikuj LEN AK:
        // 1. bolo mesto úspešne nastavené
        // 2. program ešte nebol aplikovaný
        if (programParam && stateChanged && !window.spaGFGetState.programApplied) {
            // Počkaj na filtrovanie program options po city change
            setTimeout(() => {
                let programAttempts = 0;
                const maxProgramAttempts = 10;
                
                const checkProgramOptions = setInterval(() => {
                    programAttempts++;
                    const programSelect = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
                    const hasProgramOptions = programSelect && programSelect.options.length > 1;
                    
                    console.log('[SPA GET] Waiting for program options... (' + programAttempts + '/' + maxProgramAttempts + ')');
                    
                    if (!hasProgramOptions && programAttempts < maxProgramAttempts) {
                        return; // Pokračuj v pollingu
                    }
                    
                    clearInterval(checkProgramOptions);
                    
                    if (!hasProgramOptions) {
                        console.error('[SPA GET] TIMEOUT - program options not ready');
                        return;
                    }
                    
                    console.log('[SPA GET] Program options ready');
                    
                    // ⭐ NÁJDI OPTION PODĽA value (program=889 je ID)
                    const matchedOption = Array.from(programSelect.options).find(opt => 
                        opt.value == programParam
                    );
                    
                    if (matchedOption) {
                        // ⭐ OBSERVER: Sleduj GF rerender a aplikuj hodnotu AŽ POTOM
                        const observer = new MutationObserver((mutations) => {
                            for (const mutation of mutations) {
                                if (mutation.type === 'childList' && mutation.target === programSelect) {
                                    console.log('[SPA GET] Program <select> re-rendered, applying value');
                                    
                                    const freshOption = Array.from(programSelect.options).find(opt => opt.value == programParam);
                                    if (freshOption) {
                                        programSelect.value = freshOption.value;
                                        
                                        if (typeof jQuery !== 'undefined' && jQuery(programSelect).data('chosen')) {
                                            jQuery(programSelect).trigger('chosen:updated');
                                        }
                                        
                                        programSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                        console.log('[SPA GET] ✅ Program value set AFTER rerender:', freshOption.value);
                                        
                                        observer.disconnect();
                                    }
                                }
                            }
                        });
                        
                        observer.observe(programSelect, {
                            childList: true,
                            subtree: false
                        });
                        
                        // Fallback ak sa observer nespustí do 3s
                        setTimeout(() => {
                            observer.disconnect();
                            if (!programSelect.value || programSelect.value === '') {
                                const freshOption = Array.from(programSelect.options).find(opt => opt.value == programParam);
                                if (freshOption) {
                                    programSelect.value = freshOption.value;
                                    programSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                    console.log('[SPA GET] ✅ Program value set via FALLBACK');
                                }
                            }
                        }, 3000);
                        
                        // ⭐ BACKUP do hidden fieldu (ochrana pred GF refresh)
                        const programBackup = document.getElementById('spa_program_backup');
                        if (programBackup) {
                            programBackup.value = programParam;
                            console.log('[SPA GET] Backed up program value:', programParam);
                        }
                        
                        window.wizardData.program_name = matchedOption.text;
                        window.wizardData.program_id = matchedOption.getAttribute('data-program-id') || matchedOption.value;
                        
                        // Parsuj vek
                        const ageMatch = matchedOption.text.match(/(\d+)[–-](\d+)/);
                        if (ageMatch) {
                            window.wizardData.program_age = ageMatch[1] + '–' + ageMatch[2];
                        } else {
                            const agePlusMatch = matchedOption.text.match(/(\d+)\+/);
                            if (agePlusMatch) {
                                window.wizardData.program_age = agePlusMatch[1] + '+';
                            }
                        }
                        
                        window.spaFormState.program = true;
                        window.currentState = 2;
                        window.spaGFGetState.programApplied = true;

                        // ⭐ TRIGGER CHANGE EVENT
                        programSelect.dispatchEvent(new Event('change', { bubbles: true }));

                        console.log('[SPA GET] ✅ Program applied:', matchedOption.text);
                        
                        // ⭐ AGGRESSIVE POLLING: Sleduj či GF zresetoval select a opakuj nastavenie
                        let verifyAttempts = 0;
                        const maxVerifyAttempts = 20;
                        
                        const verifyProgramValue = setInterval(() => {
                            verifyAttempts++;
                            const currentSelect = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
                            const currentValue = currentSelect?.value;
                            
                            if (currentValue && currentValue == programParam) {
                                console.log('[SPA GET] ✅ Program value stable:', currentValue);
                                clearInterval(verifyProgramValue);
                            } else if (verifyAttempts >= maxVerifyAttempts) {
                                console.error('[SPA GET] ❌ Program value never stabilized');
                                clearInterval(verifyProgramValue);
                            } else if (currentSelect && currentSelect.options.length > 1) {
                                // Options sú ready, ale hodnota chýba - aplikuj znova
                                const freshOption = Array.from(currentSelect.options).find(opt => opt.value == programParam);
                                if (freshOption) {
                                    currentSelect.value = freshOption.value;
                                    currentSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                    console.log('[SPA GET] Re-applied program value (attempt ' + verifyAttempts + ')');
                                }
                            }
                        }, 100);
                        
                        // Načítaj infobox pre state 2
                        window.loadInfoboxContent(window.currentState);
                        } else {
                            console.warn('[SPA GET] ⚠️ Program option not found:', programParam);
                            window.spaErrorState.invalidProgram = true;
                            window.currentState = window.wizardData.city_name ? 1 : 0;
                            window.updateErrorBox();
                        }
                }, 100); // Skúšaj každých 100ms
            }, 150); // Počkaj na dokončenie filterProgramsByCity
        }

        // Ak sa zmenil state, reload infobox
        /* if (stateChanged) {
            window.loadInfoboxContent(window.currentState);
        } */
        
        // FREKVENCIA - aplikuj až PO renderi infoboxu
        if (frequencyParam && window.currentState === 2) {
            setTimeout(() => {
                const frequencyRadio = document.querySelector(`input[name="spa_frequency"][value="${frequencyParam}"]`);
                if (frequencyRadio) {
                    frequencyRadio.checked = true;
                    window.spaFormState.frequency = true;
                    window.updateSectionVisibility();
                    console.log('[SPA GET] Applied frequency:', frequencyParam);
                } else {
                    console.warn('[SPA GET] Frequency option not found:', frequencyParam);
                }
            }, 500);  // Počkaj na renderFrequencySelector
        }
        
        // ⭐ ZRUŠ FLAG po dokončení všetkých GET operácií
        setTimeout(() => {
            window.isApplyingGetParams = false;
            console.log('[SPA GET] Flag cleared - normal change handling restored');
        }, 1500); // Po všetkých setTimeout-och v GET flow
        
    }, 200);  // Polling každých 200ms
}, 500);  // ⭐ ODLOŽENÝ ŠTART o 500ms
};