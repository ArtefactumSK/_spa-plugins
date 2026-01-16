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

// ⭐ TOP-LEVEL GUARD: Koniec ak nie je infobox container
if (!document.getElementById('spa-infobox-container')) {
    return; // Ticho skonči, žiadne logy, žiadne listenery
}
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
    function determineCaseState() {
        if (!wizardData.city_name) {
            return 0;
        }
        if (wizardData.city_name && !wizardData.program_name) {
            return 1;
        }
        if (wizardData.city_name && wizardData.program_name) {
            return 2;
        }
        return 0;
    }

    /**
     * CENTRÁLNY UPDATE STAVU
     */
    function updateInfoboxState() {
        const newState = determineCaseState();
        
        console.log('[SPA Infobox] State transition:', {
            from: currentState,
            to: newState,
            wizardData: wizardData
        });
        
        currentState = newState;
        window.loadInfoboxContent(window.currentState);
    }

    
    /**
     * Obnovenie wizardData z hidden backup polí
     */
    function restoreWizardData() {
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
                        wizardData.city_name = selectedOption.text;
                        window.spaFormState.city = true;
                        currentState = 1;
                        
                        console.log('[SPA Restore] ✅ City RESTORED:', wizardData.city_name);
                    } else {
                        console.error('[SPA Restore] ❌ City restore FAILED - no option found for value:', cityBackup.value);
                    }
                }
                
                // OBNOV PROGRAM
                if (programBackup?.value && programSelect) {
                    programSelect.value = programBackup.value;
                    
                    const selectedOption = programSelect.options[programSelect.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        wizardData.program_name = selectedOption.text;
                        wizardData.program_id = selectedOption.getAttribute('data-program-id') || selectedOption.value;
                        window.spaFormState.program = true;
                        
                        // Parsuj vek
                        const ageMatch = selectedOption.text.match(/(\d+)[–-](\d+)/);
                        if (ageMatch) {
                            wizardData.program_age = ageMatch[1] + '–' + ageMatch[2];
                        } else {
                            const agePlusMatch = selectedOption.text.match(/(\d+)\+/);
                            if (agePlusMatch) {
                                wizardData.program_age = agePlusMatch[1] + '+';
                            }
                        }
                        
                        currentState = 2;
                        
                        console.log('[SPA Restore] ✅ Program RESTORED:', wizardData.program_name);
                    } else {
                        console.error('[SPA Restore] ❌ Program restore FAILED - no option found for value:', programBackup.value);
                    }
                }
                
                // Načítaj infobox ak máme dáta
                if (currentState > 0) {
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
    }

    /**
     * Sledovanie zmien vo formulári
     */
    function watchFormChanges() {
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
            console.log('[SPA DEBUG] Change event:', e.target.name, e.target.value);
            // Skontroluj či ide o city field
            if (e.target.name === 'input_1') {
                console.log('[SPA DEBUG] City field detected!');
                const cityField = e.target;
                const selectedOption = cityField.options[cityField.selectedIndex];
                const selectedCityName = selectedOption ? selectedOption.text.trim() : '';
                
                console.log('[SPA City Change] Selected:', selectedCityName);
                
                // ⭐ VŽDY RESETUJ PROGRAM pri zmene mesta
                wizardData.program_name = '';
                wizardData.program_id = null;
                wizardData.program_age = '';
                wizardData.frequency = '';
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
                    wizardData.city_name = selectedCityName;
                    window.spaFormState.city = true;
                    currentState = 1;
                } else {
                    // Úplné vymazanie mesta
                    wizardData.city_name = '';
                    window.spaFormState.city = false;
                    currentState = 0;
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
                    wizardData.program_name = selectedOption.text;
                    wizardData.program_id = selectedOption.getAttribute('data-program-id') || this.value;
                    
                    console.log('[SPA Infobox] Program ID:', wizardData.program_id);
                    
                    // RESET veku pred novým parsovaním
                    wizardData.program_age = '';
                    
                    // Parsuj vek z názvu programu
                    const ageMatch = selectedOption.text.match(/(\d+)[–-](\d+)/);
                    if (ageMatch) {
                        wizardData.program_age = ageMatch[1] + '–' + ageMatch[2];
                    } else {
                        const agePlusMatch = selectedOption.text.match(/(\d+)\+/);
                        if (agePlusMatch) {
                            wizardData.program_age = agePlusMatch[1] + '+';
                        }
                    }
                    
                    window.spaFormState.program = true;
                    currentState = 2;
                } else {
                    // ⭐ RESET PROGRAMU
                    wizardData.program_name = '';
                    wizardData.program_id = null;
                    wizardData.program_age = '';
                    window.spaFormState.program = false;
                    window.spaFormState.frequency = false;
                    currentState = wizardData.city_name ? 1 : 0;
                    
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
        listenersAttached = true;
        console.log('[SPA Infobox] Event listeners attached');
    }  

    
    
    /**
     * Načítanie obsahu infoboxu cez AJAX
     */
    function loadInfoboxContent(state) {
        // ⭐ GUARD: Container musí existovať
        if (!document.getElementById('spa-infobox-container')) {
            return;
        }
        
        console.log('[SPA Infobox] Loading state:', state, window.wizardData);
        window.showLoader();

        const formData = new FormData();
        formData.append('action', 'spa_get_infobox_content');
        formData.append('program_id', wizardData.program_id);
        formData.append('state', state);
        formData.append('city_name', wizardData.city_name);
        formData.append('program_name', wizardData.program_name);
        formData.append('program_age', wizardData.program_age);

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
    }