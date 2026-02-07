/**
 * SPA Infobox Wizard – Frontend logika
 * CENTRALIZOVANÉ STATE MANAGEMENT
 */

// ========== DUPLICATE DETECTION ==========
(function() {
    const functionsToCheck = [
        'spaSetProgramType',
        'updateInfoboxState',
        'determineCaseState'
    ];
    
    functionsToCheck.forEach(fnName => {
        if (typeof window[fnName] === 'function') {
            console.warn('[SPA DUPLICATE] ' + fnName + ' already exists before state load', {
                __source: window[fnName].__source || 'unknown',
                type: typeof window[fnName]
            });
        }
    });
})();

/**
 * SETTER: Centrálne nastavenie program type (SINGLE SOURCE OF TRUTH)
 */
window.spaSetProgramType = function(newType) {
    const oldType = window.wizardData.program_type;
    
    if (newType !== oldType) {
        window.wizardData.program_type = newType;
        
        // Mirror do window.spaCurrentProgramType (backward compatibility)
        window.spaCurrentProgramType = newType;
        
        console.log('[SPA State] Program type updated:', {
            old: oldType,
            new: newType,
            wizardData_program_type: window.wizardData.program_type,
            spaCurrentProgramType: window.spaCurrentProgramType,
            callStack: new Error().stack.split('\n').slice(1, 4).join('\n')  // Show caller
        });
        
        // AUTOMATIC TRIGGER: updateSectionVisibility with retry + debounce
        if (window.__spaVisibilityUpdateTimeout) {
            clearTimeout(window.__spaVisibilityUpdateTimeout);
        }
        
        window.__spaVisibilityUpdateTimeout = setTimeout(() => {
            let attempts = 0;
            const maxAttempts = 15; // Increased from 10
            
            const tryUpdate = () => {
                attempts++;
                
                if (typeof window.updateSectionVisibility === 'function') {
                    const domReady = document.readyState === 'complete' || document.readyState === 'interactive';
                    
                    if (domReady) {
                        console.log('[SPA State] Auto-triggering updateSectionVisibility (attempt ' + attempts + ')');
                        window.updateSectionVisibility();
                    } else if (attempts < maxAttempts) {
                        setTimeout(tryUpdate, 100);
                    } else {
                        console.error('[SPA State] FAILED: DOM not ready after ' + maxAttempts + ' attempts');
                    }
                } else if (attempts < maxAttempts) {
                    console.warn('[SPA State] updateSectionVisibility not defined, retrying... (' + attempts + '/' + maxAttempts + ')');
                    setTimeout(tryUpdate, 50);
                } else {
                    console.error('[SPA State] FAILED: updateSectionVisibility not defined after ' + maxAttempts + ' attempts', {
                        'window.updateSectionVisibility': typeof window.updateSectionVisibility,
                        'window.__spaOrchestratorBound': window.__spaOrchestratorBound
                    });
                }
            };
            
            tryUpdate();
        }, 50);
    }
};
// Tag source for debugging
window.spaSetProgramType.__source = 'spa-infobox-state.js';
console.log('[SPA SRC] spaSetProgramType defined in:', window.spaSetProgramType.__source);

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
    
    window.setSpaState(newState, 'updateInfoboxState');    
    window.loadInfoboxContent(window.currentState);
};


/**
 * RESET programu pri zmene mesta
 */
window.resetProgramSelection = function(reason) {
    console.log('[SPA Reset] Program selection reset:', reason);
    
    // State cleanup
    window.wizardData.program_id = null;
    window.wizardData.program_name = '';
    window.wizardData.program_age = '';
    window.wizardData.program_type = null;
    window.spaFormState.program = false;
    
    // DOM cleanup
    const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    if (programField) {
        programField.value = '';
        programField.selectedIndex = 0;
        
        // Trigger change pre Gravity Forms
        programField.dispatchEvent(new Event('change', { bubbles: true }));
        
        // Chosen plugin sync
        if (typeof jQuery !== 'undefined' && jQuery(programField).data('chosen')) {
            jQuery(programField).trigger('chosen:updated');
        }
    }
    
    // Frequency cleanup
    window.spaFormState.frequency = false;
    const frequencySelector = document.querySelector('.spa-frequency-selector');
    if (frequencySelector) {
        frequencySelector.innerHTML = '';
    }
    
    // Backup field cleanup
    const programBackup = document.querySelector(`[name="${spaConfig.fields.spa_program_backup}"]`);
    if (programBackup) {
        programBackup.value = '';
    }
    
    console.log('[SPA Reset] Program reset complete');
};

/**
 * Sledovanie zmien vo formulári
 */
window.watchFormChanges = function() {
    if (!document.getElementById('spa-infobox-container')) {
        return;
    }
    
    if (window.listenersAttached) {
        console.log('[SPA Infobox] Listeners already attached, skipping');
        return;
    }
    
    // ✅ Backup polia sú STATICKÉ Gravity Forms Hidden fieldy (nie dynamicky vytvorené)
    // Prístup cez spaConfig.fields.spa_city_backup / spa_program_backup
    document.addEventListener('change', function(e) {
        if (e.target.name === spaConfig.fields.spa_city) {
            console.log('[SPA GET DEBUG] Change event triggered on input_1');
            console.log('[SPA GET DEBUG] Change event value:', e.target.value);
            console.log('[SPA GET DEBUG] Change event triggered by:', e.isTrusted ? 'USER' : 'SCRIPT');
        }
        console.log('[SPA DEBUG] Change event:', e.target.name, e.target.value);
        
        if (e.target.name === spaConfig.fields.spa_city) {
            // ✅ Reset programu ONLY pri USER zmene (NIE počas GET ani RESTORE)
            if (!window.isApplyingGetParams && !window.__spaRestoringState && e.isTrusted) {
                window.resetProgramSelection('city:user-change');
            }
            
            // ⚠️ RESET ERROR FLAGS + SPA ERRORBOX IMMEDIATELY
            if (window.spaErrorState.invalidCity || window.spaErrorState.errorType === 'state') {  
                console.log('[SPA City Change] Resetting error state + hiding SPA errorbox');  
                window.spaErrorState.invalidCity = false;  
                if (window.spaErrorState.errorType === 'state' && !window.spaErrorState.invalidProgram) {  
                    window.spaErrorState.errorType = null;  
                }
                
                // Okamžite skry SPA errorbox
                const spaErrorBox = document.querySelector('.spa-errorbox--state');
                if (spaErrorBox) {
                    spaErrorBox.innerHTML = '';
                    spaErrorBox.style.display = 'none';
                }
            }  
            
            console.log('[SPA DEBUG] City field detected!');
            const cityField = e.target;
            const selectedOption = cityField.options[cityField.selectedIndex];
            const selectedCityName = selectedOption ? selectedOption.text.trim() : '';
            
            console.log('[SPA City Change] Selected:', selectedCityName);
            
            if (window.isApplyingGetParams) {
                console.log('[SPA City Change] GET loading - skipping program reset');
                
                if (cityField.value && cityField.value !== '0' && cityField.value !== '') {
                    if (!window.wizardData.city_name) {
                        window.wizardData.city_name = selectedCityName;
                        window.wizardData.city_slug = spa_remove_diacritics(selectedCityName);
                    }
                    window.spaFormState.city = true;
                    window.setSpaState(1, 'city:load-selected');
                }
                
                if (selectedCityName && selectedCityName.trim() !== '') {
                    window.filterProgramsByCity(selectedCityName);
                }
                
                return;
            }
            
            window.filterProgramsByCity(selectedCityName);
            
            if (cityField.value && cityField.value !== '0' && cityField.value !== '') {
                window.wizardData.city_name = selectedCityName;
                window.wizardData.city_slug = spa_remove_diacritics(selectedCityName);
                window.spaFormState.city = true;
                window.setSpaState(1, 'city:user-select');
                
                // ✅ WRITE-BACK: Aktualizuj backup field (ONLY pri USER interakcii)
                if (e.isTrusted && !window.isApplyingGetParams && !window.__spaRestoringState) {
                    const cityBackup = document.querySelector(`[name="${spaConfig.fields.spa_city_backup}"]`);
                    if (cityBackup) {
                        cityBackup.value = cityField.value;
                        console.log('[SPA Backup] City backup updated:', cityField.value);
                    } else {
                        console.warn('[SPA Backup] City backup field NOT FOUND - check GF Hidden field mapping');
                    }
                }
                
                if (typeof window.spaRequestVisibilityUpdate === 'function') {
                    window.spaRequestVisibilityUpdate('city-valid');
                }
            } else {
                window.wizardData.city_name = '';
                window.spaFormState.city = false;
                window.setSpaState(0, 'city:clear');
                
                // ✅ WRITE-BACK: Vyčisti backup field pri clear
                if (e.isTrusted && !window.isApplyingGetParams && !window.__spaRestoringState) {
                    const cityBackup = document.querySelector(`[name="${spaConfig.fields.spa_city_backup}"]`) || 
                                      document.getElementById('spa_city_backup');
                    if (cityBackup) {
                        cityBackup.value = '';
                        console.log('[SPA Backup] City backup cleared');
                    }
                }
            }
            
            window.loadInfoboxContent(window.currentState);
            if (typeof window.spaRequestVisibilityUpdate === 'function') {
                window.spaRequestVisibilityUpdate('city-change');
            }
            window.updatePriceSummary();
        }
    });
    
    const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    
    if (programField) {
        programField.addEventListener('change', function() {
            // ⚠️ RESET ERROR FLAGS + SPA ERRORBOX IMMEDIATELY
            if (window.spaErrorState.invalidProgram || window.spaErrorState.errorType === 'state') {
                console.log('[SPA Program Change] Resetting error state + hiding SPA errorbox');
                window.spaErrorState.invalidProgram = false;
                if (window.spaErrorState.errorType === 'state' && !window.spaErrorState.invalidCity) {
                    window.spaErrorState.errorType = null;
                }
                
                // Okamžite skry SPA errorbox
                const spaErrorBox = document.querySelector('.spa-errorbox--state');
                if (spaErrorBox) {
                    spaErrorBox.innerHTML = '';
                    spaErrorBox.style.display = 'none';
                }
            }
            
            const selectedOption = this.options[this.selectedIndex];
            
            console.log('[SPA Infobox] Program changed - value:', this.value);
            console.log('[SPA Infobox] Program changed - text:', selectedOption.text);
            
            const summaryContainer = document.querySelector('.spa-price-summary');
            if (summaryContainer) {
                summaryContainer.innerHTML = '';
                console.log('[SPA] Cleared price summary on program change');
            }
            
            if (this.value) {
                window.wizardData.program_name = selectedOption.text;
                window.wizardData.program_id = selectedOption.getAttribute('data-program-id') || this.value;
                
                // ✅ WRITE-BACK: Aktualizuj backup field (ONLY pri USER interakcii)
                // Detekcia USER vs SCRIPT: addEventListener('change') nemá e.isTrusted v arrow function scope
                // Riešenie: Použijeme window flags ako proxy
                if (!window.isApplyingGetParams && !window.__spaRestoringState) {
                    const programBackup = document.querySelector(`[name="${spaConfig.fields.spa_program_backup}"]`) || 
                                         document.getElementById('spa_program_backup');
                    if (programBackup) {
                        programBackup.value = this.value;
                        console.log('[SPA Backup] Program backup updated:', this.value);
                    }
                }
                
                console.log('[SPA Infobox] Program ID:', window.wizardData.program_id);
                
                window.wizardData.program_age = '';
                
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
                window.setSpaState(2, 'program:user-select');
                
                // ✅ FLAG: Wizard progressed beyond GET bootstrap phase
                if (!window.__spaWizardProgressed) {
                    window.__spaWizardProgressed = true;
                    console.log('[SPA State] Wizard progressed to CASE 2 - GET logic now DISABLED');
                }
            } else {
                // ⭐ RESET PROGRAMU
                window.wizardData.program_name = '';
                window.wizardData.program_id = null;
                window.wizardData.program_age = '';
                window.wizardData.program_type = null;  // ⭐ RESET SCOPE
                window.spaFormState.program = false;
                window.spaFormState.frequency = false;
                window.setSpaState(window.wizardData.city_name ? 1 : 0, 'program:clear');
                
                // ✅ WRITE-BACK: Vyčisti backup field pri clear
                if (!window.isApplyingGetParams && !window.__spaRestoringState) {
                    const programBackup = document.querySelector(`[name="${spaConfig.fields.spa_program_backup}"]`) || 
                                         document.getElementById('spa_program_backup');
                    if (programBackup) {
                        programBackup.value = '';
                        console.log('[SPA Backup] Program backup cleared');
                    }
                }
                
                const frequencySelector = document.querySelector('.spa-frequency-selector');
                if (frequencySelector) {
                    frequencySelector.innerHTML = '';
                }                              
                
                window.filterProgramsByCity(selectedCityName);
            }
            
            window.loadInfoboxContent(window.currentState);
            if (typeof window.spaRequestVisibilityUpdate === 'function') {
                window.spaRequestVisibilityUpdate('program-change');
            }
        });
    } else {
        console.error('[SPA Infobox] Program field NOT FOUND!');
    }
    
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('gform_page_loaded', function(event, form_id, current_page) {
            console.log('[SPA Restore] page_loaded page=' + current_page);
            
            setTimeout(() => {
                // ✅ REHYDRATION: Obnov stav z backup polí (po pagebreaku)
                if (current_page > 1) {
                    const cityBackup = document.querySelector(`[name="${spaConfig.fields.spa_city_backup}"]`);
                    const programBackup = document.querySelector(`[name="${spaConfig.fields.spa_program_backup}"]`);
                    
                    const hasCityBackup = cityBackup && cityBackup.value && cityBackup.value.trim() !== '';
                    const hasProgramBackup = programBackup && programBackup.value && programBackup.value.trim() !== '';
                    
                    console.log('[SPA Restore] Backup values:', {
                        city: hasCityBackup ? cityBackup.value : 'none',
                        program: hasProgramBackup ? programBackup.value : 'none'
                    });
                    
                    if (hasCityBackup) {
                        // Obnov city state
                        window.wizardData.city_id = cityBackup.value;
                        window.spaFormState.city = true;
                        
                        if (hasProgramBackup) {
                            // Obnov program state
                            window.wizardData.program_id = programBackup.value;
                            window.spaFormState.program = true;
                            window.currentState = 2;
                            console.log('[SPA Restore] State rehydrated: CASE 2');
                        } else {
                            // Len city
                            window.currentState = 1;
                            console.log('[SPA Restore] State rehydrated: CASE 1');
                        }
                        
                        // Spusti visibility + infobox
                        if (typeof window.updateSectionVisibility === 'function') {
                            window.updateSectionVisibility();
                        }
                        window.loadInfoboxContent(window.currentState);
                        
                        // FLAG: Restore dokončený
                        window.__spaRestoreComplete = true;
                    } else {
                        // Žiadne backup hodnoty
                        window.currentState = 0;
                        window.__spaRestoreComplete = true;
                        console.log('[SPA Restore] No backup values - CASE 0');
                    }
                }
                
                window.updatePriceSummary();
            }, 200);
        });
    }
    
    window.listenersAttached = true;
    console.log('[SPA Infobox] Event listeners attached');
};

/**
 * Načítanie obsahu infoboxu cez AJAX
 */
window.loadInfoboxContent = function(state) {
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
            
            setTimeout(() => {
                if (typeof window.spaRequestVisibilityUpdate === 'function') {
                    window.spaRequestVisibilityUpdate('infobox-loaded');
                }
            }, 100);
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
    // ✅ CRITICAL: GET logic DISABLED after wizard progresses to CASE 2
    if (window.__spaWizardProgressed) {
        console.log('[SPA GET] Wizard already progressed - GET logic DISABLED');
        return;
    }
    
    const urlParams = new URLSearchParams(window.location.search);
    let cityParam = urlParams.get('city');
    const programParam = urlParams.get('program');
    const frequencyParam = urlParams.get('spa_frequency');
    
    if (cityParam) {
        cityParam = spa_remove_diacritics(cityParam);
        console.log('[SPA GET] Normalized city param:', cityParam);
    }
    
    if (!cityParam && !programParam && !frequencyParam) {
        console.log('[SPA GET] No GET params found');
        return;
    }
    
    console.log('[SPA GET] Found params:', { cityParam, programParam, frequencyParam });
    
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
        console.log('[SPA GET] Both city and program already applied - skipping');
        return;
    }
    
    window.isApplyingGetParams = true;
    
    setTimeout(() => {
        let attempts = 0;
        const maxAttempts = 30;
        
        const checkOptions = setInterval(() => {
            attempts++;
            console.log('[SPA GET DEBUG] ========== START DIAGNOSTICS (attempt ' + attempts + '/' + maxAttempts + ') ==========');
            console.log('[SPA GET DEBUG] URL params:', { cityParam, programParam, frequencyParam });
            console.log('[SPA GET DEBUG] spaConfig.fields:', spaConfig.fields);
            
            const citySelect = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
            console.log('[SPA GET DEBUG] City select element:', citySelect);
            console.log('[SPA GET DEBUG] City select exists:', !!citySelect);
            
            if (citySelect) {
                console.log('[SPA GET DEBUG] City select name:', citySelect.name);
                console.log('[SPA GET DEBUG] City select id:', citySelect.id);
                console.log('[SPA GET DEBUG] City select options.length:', citySelect.options.length);
                
                const optionsList = Array.from(citySelect.options).slice(0, 10).map(opt => ({
                    value: opt.value,
                    text: opt.text,
                    selected: opt.selected
                }));
                console.log('[SPA GET DEBUG] City select options (first 10):', optionsList);
                console.log('[SPA GET DEBUG] City select value BEFORE:', citySelect.value);
            } else {
                console.error('[SPA GET DEBUG] ❌ City select NOT FOUND with selector:', `[name="${spaConfig.fields.spa_city}"]`);
                
                const altSelect1 = document.querySelector('[name="spa_city"]');
                const altSelect2 = document.querySelector('#input_1_1');
                const altSelect3 = document.querySelector('select[id^="input_1"]');
                
                console.log('[SPA GET DEBUG] Alternative selectors:');
                console.log('  [name="input_1"]:', !!altSelect1);
                console.log('  #input_1_1:', !!altSelect2);
                console.log('  select[id^="input_1"]:', !!altSelect3);
            }
            
            console.log('[SPA GET DEBUG] ========== END DIAGNOSTICS ==========');
            
            const citySelect2 = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
            if (!citySelect || citySelect.options.length <= 1) {
                if (attempts < maxAttempts) {
                    console.log('[SPA GET] Waiting for options... (' + attempts + '/' + maxAttempts + ')');
                    return;
                } else {
                    console.error('[SPA GET] TIMEOUT - options not ready after ' + attempts + ' attempts');
                    clearInterval(checkOptions);
                    return;
                }
            }
            
            clearInterval(checkOptions);
            console.log('[SPA GET] ✅ Options ready, applying params');
            
            let stateChanged = false;
            
            if (cityParam) {
                const citySelect = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
                if (citySelect) {
                    const options = Array.from(citySelect.options);
                    const matchedOption = options.find(opt => {
                        const normalizedOptionText = spa_remove_diacritics(opt.text.trim());
                        const normalizedSearchText = cityParam;
                        return normalizedOptionText === normalizedSearchText;
                    });
                    
                    if (matchedOption) {
                        citySelect.value = matchedOption.value;
                        
                        const cityBackup = document.querySelector(`[name="${spaConfig.fields.spa_city_backup}"]`);
                        if (cityBackup) {
                            cityBackup.value = matchedOption.value;                            
                            console.log('[SPA GET] Backed up city value:', matchedOption.value);
                        }
                        
                        if (typeof jQuery !== 'undefined') {
                            setTimeout(() => {
                                if (jQuery(citySelect).data('chosen')) {
                                    jQuery(citySelect).trigger('chosen:updated');
                                    console.log('[SPA GET] Chosen updated for city select');
                                } else {
                                    citySelect.selectedIndex = Array.from(citySelect.options).findIndex(opt => {
                                        const normalizedOptionText = spa_remove_diacritics(opt.text.trim());
                                        return normalizedOptionText === cityParam;
                                    });
                                    console.log('[SPA GET] Chosen not found, using selectedIndex fallback');
                                }
                            }, 50);
                        }
                        
                        console.log('[SPA GET DEBUG] City select value AFTER:', citySelect.value);
                        console.log('[SPA GET DEBUG] Matched option value:', matchedOption.value);
                        console.log('[SPA GET DEBUG] Matched option text:', matchedOption.text);
                        
                        setTimeout(() => {
                            const finalValue = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
                            console.log('[SPA GET DEBUG] City select value AFTER 500ms:', finalValue?.value);
                            console.log('[SPA GET DEBUG] City select still exists:', !!finalValue);
                        }, 500);
                        
                        window.wizardData.city_name = matchedOption.text.trim();
                        window.wizardData.city_slug = spa_remove_diacritics(matchedOption.text.trim());
                        window.spaFormState.city = true;
                        window.setSpaState(1, 'GET:city');
                        stateChanged = true;
                        window.spaGFGetState.cityApplied = true;
                        console.log('[SPA GET] ✅ City applied:', matchedOption.text);
                        
                        citySelect.dispatchEvent(new Event('change', { bubbles: true }));
                        
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
                        }, 200);
                    } else {
                        console.warn('[SPA GET] City option not found:', cityParam);
                        window.spaErrorState.invalidCity = true;
                        window.setSpaState(0, 'GET:city-invalid');
                        window.updateErrorBox();
                    }
                }
            }
            
            if (programParam && stateChanged && !window.spaGFGetState.programApplied) {
                setTimeout(() => {
                    let programAttempts = 0;
                    const maxProgramAttempts = 10;
                    
                    const checkProgramOptions = setInterval(() => {
                        programAttempts++;
                        const programSelect = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
                        const hasProgramOptions = programSelect && programSelect.options.length > 1;
                        
                        console.log('[SPA GET] Waiting for program options... (' + programAttempts + '/' + maxProgramAttempts + ')');
                        
                        if (!hasProgramOptions && programAttempts < maxProgramAttempts) {
                            return;
                        }
                        
                        clearInterval(checkProgramOptions);
                        
                        if (!hasProgramOptions) {
                            console.error('[SPA GET] TIMEOUT - program options not ready');
                            return;
                        }
                        
                        console.log('[SPA GET] Program options ready');
                        
                        const matchedOption = Array.from(programSelect.options).find(opt => 
                            opt.value == programParam
                        );
                        
                        if (matchedOption) {
                            // ✅ Stabilný observer: ak sa program select znovu vyrenderuje (Chosen/AJAX), nastav hodnotu znova
                        const observer = new MutationObserver(() => {
                            const freshOption = Array.from(programSelect.options).find(opt => opt.value == programParam);
                            if (freshOption) {
                                programSelect.value = freshOption.value;

                                if (typeof jQuery !== 'undefined' && jQuery(programSelect).data('chosen')) {
                                    jQuery(programSelect).trigger('chosen:updated');
                                }

                                programSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                console.log('[SPA GET] ✅ Program value set via observer');
                                observer.disconnect();
                            }
                        });

                        observer.observe(programSelect, { childList: true, subtree: false });
                        
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
                        
                        const programBackup = document.querySelector(`[name="${spaConfig.fields.spa_program_backup}"]`);
                        if (programBackup) {
                            programBackup.value = programParam;
                            console.log('[SPA GET] Backed up program value:', programParam);
                        }
                        
                        window.wizardData.program_name = matchedOption.text;
                        window.wizardData.program_id = matchedOption.getAttribute('data-program-id') || matchedOption.value;
                        
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
                        window.setSpaState(2, 'GET:program');
                        window.spaGFGetState.programApplied = true;
                        
                        programSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        
                        console.log('[SPA GET] ✅ Program applied:', matchedOption.text);
                        
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
                                const freshOption = Array.from(currentSelect.options).find(opt => opt.value == programParam);
                                if (freshOption) {
                                    currentSelect.value = freshOption.value;
                                    currentSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                    console.log('[SPA GET] Re-applied program value (attempt ' + verifyAttempts + ')');
                                }
                            }
                        }, 100);
                        
                        window.loadInfoboxContent(window.currentState);
                        if (typeof window.spaRequestVisibilityUpdate === 'function') {
                            window.spaRequestVisibilityUpdate('get-program-applied');
                        }
                    } else {
                        console.warn('[SPA GET] ⚠️ Program option not found:', programParam);
                        window.spaErrorState.invalidProgram = true;
                        window.setSpaState(window.wizardData.city_name ? 1 : 0, 'GET:program-invalid');
                        window.updateErrorBox();
                    }
                }, 100);
            }, 150);
        }
        
        if (frequencyParam && window.currentState === 2) {
            setTimeout(() => {
                const frequencyRadio = document.querySelector(`input[name="spa_frequency"][value="${frequencyParam}"]`);
                if (frequencyRadio) {
                    frequencyRadio.checked = true;
                    window.spaFormState.frequency = true;
                    if (typeof window.spaRequestVisibilityUpdate === 'function') {
                        window.spaRequestVisibilityUpdate('get-frequency-applied');
                    }
                    console.log('[SPA GET] Applied frequency:', frequencyParam);
                } else {
                    console.warn('[SPA GET] Frequency option not found:', frequencyParam);
                }
            }, 500);
        }
        
        setTimeout(() => {
            window.isApplyingGetParams = false;
            console.log('[SPA GET] Flag cleared - normal change handling restored');
        }, 1500);
    }, 200);
}, 500);
};