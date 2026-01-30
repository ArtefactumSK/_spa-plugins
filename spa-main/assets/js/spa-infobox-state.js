/**
 * SPA Infobox Wizard – Frontend logika
 * CENTRALIZOVANÁ STATE MANAGEMENT
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
            errorBox.innerHTML = '';
        }
        window.spaRequestVisibilityUpdate('errorbox-clear');
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
    window.spaRequestVisibilityUpdate('errorbox-show');
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
    
    const cityBackup = document.getElementById('spa_city_backup');
    const programBackup = document.getElementById('spa_program_backup');
    
    console.log('[SPA Restore] Backup fields:', {
        cityBackupValue: cityBackup?.value,
        programBackupValue: programBackup?.value
    });
    
    if (!cityBackup?.value && !programBackup?.value) {
        console.log('[SPA Restore] No backup values, skipping');
        return;
    }
    
    let attempts = 0;
    const maxAttempts = 20;
    
    const waitForSelects = setInterval(() => {
        attempts++;
        
        const citySelect = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
        const programSelect = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
        
        const cityHasOptions = citySelect && citySelect.options.length > 1;
        const programHasOptions = programSelect && programSelect.options.length > 1;
        
        console.log(`[SPA Restore] Attempt ${attempts}/${maxAttempts}:`, {
            cityExists: !!citySelect,
            cityOptionsCount: citySelect?.options.length,
            programExists: !!programSelect,
            programOptionsCount: programSelect?.options.length
        });
        
        if ((cityHasOptions && programHasOptions) || attempts >= maxAttempts) {
            clearInterval(waitForSelects);
            
            if (!cityHasOptions || !programHasOptions) {
                console.error('[SPA Restore] TIMEOUT - selects still not ready');
                return;
            }
            
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
            
            if (programBackup?.value && programSelect) {
                programSelect.value = programBackup.value;
                
                const selectedOption = programSelect.options[programSelect.selectedIndex];
                if (selectedOption && selectedOption.value) {
                    window.wizardData.program_name = selectedOption.text;
                    window.wizardData.program_id = selectedOption.getAttribute('data-program-id') || selectedOption.value;
                    window.spaFormState.program = true;
                    
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
    }, 100);
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
    
    document.addEventListener('change', function(e) {
        if (e.target.name === spaConfig.fields.spa_city) {
            console.log('[SPA GET DEBUG] Change event triggered on input_1');
            console.log('[SPA GET DEBUG] Change event value:', e.target.value);
            console.log('[SPA GET DEBUG] Change event triggered by:', e.isTrusted ? 'USER' : 'SCRIPT');
        }
        console.log('[SPA DEBUG] Change event:', e.target.name, e.target.value);
        
        if (e.target.name === spaConfig.fields.spa_city) {
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
                    window.currentState = 1;
                }
                
                if (selectedCityName && selectedCityName.trim() !== '') {
                    window.filterProgramsByCity(selectedCityName);
                }
                
                return;
            }
            
            // Reset program data (frequency reset handled by orchestrator in CASE transition)
            window.wizardData.program_name = '';
            window.wizardData.program_id = null;
            window.wizardData.program_age = '';
            window.spaFormState.program = false;
            window.spaFormState.frequency = false;
            
            const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
            if (programField) {
                programField.value = '';
                
                if (selectedCityName && selectedCityName.trim() !== '') {
                    window.filterProgramsByCity(selectedCityName);
                }
            }
            
            // Frequency UI cleanup (orchestrator handles field visibility)
            const frequencySelector = document.querySelector('.spa-frequency-selector');
            if (frequencySelector) {
                frequencySelector.innerHTML = '';
            }
            
            window.filterProgramsByCity(selectedCityName);
            
            if (cityField.value && cityField.value !== '0' && cityField.value !== '') {
                window.wizardData.city_name = selectedCityName;
                window.wizardData.city_slug = spa_remove_diacritics(selectedCityName);
                window.spaFormState.city = true;
                window.currentState = 1;
                window.spaErrorState.invalidCity = false;
                
                if (window.spaErrorState.errorType === 'state') {
                    window.spaErrorState.errorType = null;
                    window.spaRequestVisibilityUpdate('city-valid');
                }
            } else {
                window.wizardData.city_name = '';
                window.spaFormState.city = false;
                window.currentState = 0;
            }
            
            window.loadInfoboxContent(window.currentState);
            window.spaRequestVisibilityUpdate('city-change');
            window.updatePriceSummary();
        }
    });
    
    const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    
    if (programField) {
        programField.addEventListener('change', function() {
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
                window.spaErrorState.invalidProgram = false;
                
                if (window.spaErrorState.errorType === 'state') {
                    window.spaErrorState.errorType = null;
                    window.spaRequestVisibilityUpdate('program-valid');
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
                window.currentState = 2;
            } else {
                window.wizardData.program_name = '';
                window.wizardData.program_id = null;
                window.wizardData.program_age = '';
                window.spaFormState.program = false;
                window.spaFormState.frequency = false;
                window.currentState = window.wizardData.city_name ? 1 : 0;
                
                const frequencySelector = document.querySelector('.spa-frequency-selector');
                if (frequencySelector) {
                    frequencySelector.innerHTML = '';
                }
                
                window.filterProgramsByCity(selectedCityName);
            }
            
            window.loadInfoboxContent(window.currentState);
            window.spaRequestVisibilityUpdate('program-change');
        });
    } else {
        console.error('[SPA Infobox] Program field NOT FOUND!');
    }
    
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('gform_page_loaded', function(event, form_id, current_page) {
            console.log('[SPA] GF Page loaded:', current_page, 'form:', form_id);
            
            setTimeout(() => {
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
                window.spaRequestVisibilityUpdate('infobox-loaded');
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
 * Helper: Odstránenie diakritiky (client-side normalizácia)
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
                        
                        const cityBackup = document.getElementById('spa_city_backup');
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
                        window.currentState = 1;
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
                        window.currentState = 0;
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
                                            console.log('[SPA GET] ✅ Program value set via observer');
                                            observer.disconnect();
                                    }
                                }
                            }
                        });
			
                        observer.observe(programSelect, {
                            childList: true,
                            subtree: false
                        });
                        
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
                        
                        const programBackup = document.getElementById('spa_program_backup');
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
                        window.currentState = 2;
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
                        window.spaRequestVisibilityUpdate('get-program-applied');
                    } else {
                        console.warn('[SPA GET] ⚠️ Program option not found:', programParam);
                        window.spaErrorState.invalidProgram = true;
                        window.currentState = window.wizardData.city_name ? 1 : 0;
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
                    window.spaRequestVisibilityUpdate('get-frequency-applied');
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