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
    program_name: '',
    program_age: ''
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
                        window.wizardData.city_name = selectedCityName;
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
                    window.wizardData.city_name = selectedCityName;
                    window.spaFormState.city = true;
                    window.currentState = 1;
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
 * Aplikuj GET parametre do formulára
 */
    window.applyGetParams = function() {
        const urlParams = new URLSearchParams(window.location.search);
        const cityParam = urlParams.get('city');
        const programParam = urlParams.get('program');
        const frequencyParam = urlParams.get('spa_frequency');
        
        if (!cityParam && !programParam && !frequencyParam) {
            console.log('[SPA GET] No GET params found');
            return;
        }
        
        console.log('[SPA GET] ========== GET FLOW START ==========');
        console.log('[SPA GET] URL params:', { cityParam, programParam, frequencyParam });
        
        // ⭐ STAVOVÝ GUARD
        if (!window.spaGFGetState) {
            window.spaGFGetState = {
                cityApplied: false,
                programApplied: false
            };
        }

        if (window.spaGFGetState.cityApplied && !programParam) {
            console.log('[SPA GET] City already applied, no program in URL - skipping');
            return;
        }

        if (window.spaGFGetState.cityApplied && window.spaGFGetState.programApplied) {
            console.log('[SPA GET] Both already applied - skipping');
            return;
        }

        // ⭐ FLAG: Zabraň resetu programu
        window.isApplyingGetParams = true;
        
        // ⭐ Funkcia na aplikáciu CITY
        const applyCityFromGet = function() {
            if (!cityParam) return Promise.resolve(false);
            
            return new Promise((resolve) => {
                let attempts = 0;
                const maxAttempts = 30;
                
                const checkCityOptions = setInterval(() => {
                    attempts++;
                    const citySelect = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
                    
                    if (!citySelect || citySelect.options.length <= 1) {
                        if (attempts < maxAttempts) {
                            console.log('[SPA GET] Waiting for city options... (' + attempts + '/' + maxAttempts + ')');
                            return;
                        }
                        clearInterval(checkCityOptions);
                        console.error('[SPA GET] ❌ City TIMEOUT');
                        resolve(false);
                        return;
                    }
                    
                    clearInterval(checkCityOptions);
                    
                    const options = Array.from(citySelect.options);
                    const matchedOption = options.find(opt => 
                        opt.text.trim().toLowerCase().includes(cityParam.toLowerCase())
                    );
                    
                    if (!matchedOption) {
                        console.error('[SPA GET] ❌ City option not found:', cityParam);
                        resolve(false);
                        return;
                    }
                    
                    // ⭐ NASTAV cez jQuery
                    if (typeof jQuery !== 'undefined') {
                        jQuery(citySelect).val(matchedOption.value).trigger('change');
                        if (jQuery(citySelect).data('chosen')) {
                            jQuery(citySelect).trigger('chosen:updated');
                        }
                    } else {
                        citySelect.value = matchedOption.value;
                        citySelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    
                    // ⭐ OVER či hodnota je nastavená
                    setTimeout(() => {
                        if (citySelect.value === matchedOption.value) {
                            window.wizardData.city_name = matchedOption.text;
                            window.spaFormState.city = true;
                            window.currentState = 1;
                            window.spaGFGetState.cityApplied = true;
                            console.log('[SPA GET] ✅ City applied OK:', matchedOption.text);
                            resolve(true);
                        } else {
                            console.error('[SPA GET] ❌ City value not stable');
                            resolve(false);
                        }
                    }, 100);
                }, 200);
            });
        };
        
        // ⭐ Funkcia na aplikáciu PROGRAM
        const waitForProgramOption = function(programId) {
            if (!programId) return Promise.resolve(false);
            
            return new Promise((resolve) => {
                let attempts = 0;
                const maxAttempts = 80; // 80 * 100ms = 8s
                
                const checkProgramOption = setInterval(() => {
                    attempts++;
                    const programSelect = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
                    
                    if (!programSelect) {
                        if (attempts < maxAttempts) return;
                        clearInterval(checkProgramOption);
                        console.error('[SPA GET] ❌ Program select not found');
                        resolve(false);
                        return;
                    }
                    
                    // ⭐ Nájdi konkrétnu option (podľa value ALEBO data-program-id)
                    const matchedOption = Array.from(programSelect.options).find(opt => 
                        opt.value == programId || opt.getAttribute('data-program-id') == programId
                    );
                    
                    if (!matchedOption) {
                        if (attempts < maxAttempts) {
                            if (attempts % 10 === 0) {
                                console.log('[SPA GET] Waiting for program option... (' + attempts + '/' + maxAttempts + ')');
                            }
                            return;
                        }
                        clearInterval(checkProgramOption);
                        
                        // ⭐ DEBUG: Vypíš dostupné options
                        const availableOptions = Array.from(programSelect.options).slice(0, 20).map(opt => ({
                            value: opt.value,
                            text: opt.text.substring(0, 50),
                            dataId: opt.getAttribute('data-program-id'),
                            matches: (opt.value == programId || opt.getAttribute('data-program-id') == programId)
                        }));
                        console.error('[SPA GET] ❌ Program option not found. Available options (first 20):');
                        console.table(availableOptions);
                        console.error('[SPA GET] ❌ Looking for programId:', programId, 'type:', typeof programId);
                        resolve(false);
                        return;
                    }
                    
                    clearInterval(checkProgramOption);
                    console.log('[SPA GET] ✅ Program option found:', matchedOption.text);
                    
                    // ⭐ NASTAV cez jQuery
                    if (typeof jQuery !== 'undefined') {
                        jQuery(programSelect).val(matchedOption.value).trigger('change');
                        if (jQuery(programSelect).data('chosen')) {
                            jQuery(programSelect).trigger('chosen:updated');
                        }
                    } else {
                        programSelect.value = matchedOption.value;
                        programSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    
                    // ⭐ OVER či hodnota je nastavená
                    setTimeout(() => {
                        if (programSelect.value === matchedOption.value) {
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
                            
                            console.log('[SPA GET] ✅ Program applied OK:', matchedOption.text);
                            
                            // Načítaj infobox
                            window.loadInfoboxContent(window.currentState);
                            
                            resolve(true);
                        } else {
                            console.error('[SPA GET] ❌ Program value not stable');
                            resolve(false);
                        }
                    }, 100);
                }, 100);
            });
        };
        
        // ⭐ SEKVENČNÉ VYKONANIE
        applyCityFromGet()
            .then((citySuccess) => {
                if (!citySuccess) {
                    console.error('[SPA GET] City failed, aborting');
                    window.isApplyingGetParams = false;
                    return;
                }
                
                // ⭐ Počkaj na GF rerender programu (po city change)
                return new Promise((resolve) => {
                    setTimeout(() => {
                        console.log('[SPA GET] Waiting for GF to rerender program select...');
                        resolve();
                    }, 300);
                });
            })
            .then(() => {
                return waitForProgramOption(programParam);
            })
            .then((programSuccess) => {
                if (!programSuccess && programParam) {
                    console.error('[SPA GET] ❌ Program failed');
                }
                
                // ⭐ FREKVENCIA
                if (frequencyParam && window.currentState === 2) {
                    setTimeout(() => {
                        const frequencyRadio = document.querySelector(`input[name="spa_frequency"][value="${frequencyParam}"]`);
                        if (frequencyRadio) {
                            frequencyRadio.checked = true;
                            window.spaFormState.frequency = true;
                            window.updateSectionVisibility();
                            console.log('[SPA GET] ✅ Frequency applied:', frequencyParam);
                        }
                    }, 500);
                }
                
                // ⭐ VYPNI FLAG
                setTimeout(() => {
                    window.isApplyingGetParams = false;
                    console.log('[SPA GET] ========== GET FLOW END ==========');
                }, 1000);
            });
    };