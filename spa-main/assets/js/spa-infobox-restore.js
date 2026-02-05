/**
 * Obnovenie wizardData z hidden backup polí
 */
window.restoreWizardData = function() {
    console.log('[SPA Restore] ========== START ==========');
    
    // ⭐ FLAG: Zabráň updateErrorBox() počas restore
    window.__spaRestoringState = true;
    
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
                // ⭐ IMMEDIATE READ pred možným GF resetom
                const matchedCityOption = Array.from(citySelect.options).find(opt => opt.value === cityBackup.value);
                
                if (matchedCityOption) {
                    citySelect.value = matchedCityOption.value;
                    
                    // ⭐ Prečítaj text IHNEĎ z matched option (nie z selectedIndex)
                    window.wizardData.city_name = matchedCityOption.text.trim();
                    window.wizardData.city_slug = spa_remove_diacritics(matchedCityOption.text.trim());
                    window.spaFormState.city = true;
                    window.setSpaState(1, 'restoreWizardData:city');
                    
                    console.log('[SPA Restore] ✅ City RESTORED:', {
                        city_name: window.wizardData.city_name,
                        city_slug: window.wizardData.city_slug,
                        backup_value: cityBackup.value
                    });
                } else {
                    console.error('[SPA Restore] ❌ City option not found for backup value:', cityBackup.value);
                }
            }
            
            if (programBackup?.value && programSelect) {
                // ⭐ IMMEDIATE READ pred možným GF resetom
                const matchedProgramOption = Array.from(programSelect.options).find(opt => opt.value === programBackup.value);
                
                if (matchedProgramOption) {
                    programSelect.value = matchedProgramOption.value;
                    
                    // ⭐ Prečítaj všetky dáta IHNEĎ z matched option
                    window.wizardData.program_name = matchedProgramOption.text.trim();
                    window.wizardData.program_id = matchedProgramOption.getAttribute('data-program-id') || matchedProgramOption.value;
                    window.spaFormState.program = true;
                    
                    // Parsuj vek
                    const ageMatch = matchedProgramOption.text.match(/(\d+)[–-](\d+)/);
                    if (ageMatch) {
                        window.wizardData.program_age = ageMatch[1] + '–' + ageMatch[2];
                    } else {
                        const agePlusMatch = matchedProgramOption.text.match(/(\d+)\+/);
                        if (agePlusMatch) {
                            window.wizardData.program_age = agePlusMatch[1] + '+';
                        }
                    }
                    
                    window.setSpaState(2, 'restoreWizardData:program');
                    
                    console.log('[SPA Restore] ✅ Program RESTORED:', {
                        program_name: window.wizardData.program_name,
                        program_id: window.wizardData.program_id,
                        program_age: window.wizardData.program_age,
                        backup_value: programBackup.value
                    });
                } else {
                    console.error('[SPA Restore] ❌ Program option not found for backup value:', programBackup.value);
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
            
            // ⭐ CLEAR FLAG: Restore complete, povoľ updateErrorBox()
            setTimeout(() => {
                window.__spaRestoringState = false;
                console.log('[SPA Restore] Flag cleared, updateErrorBox enabled');
            }, 200);
        }
    }, 100);
};