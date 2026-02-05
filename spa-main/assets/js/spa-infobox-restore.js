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
                    window.setSpaState(1, 'restoreWizardData:city');
                    
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
                    
                    window.setSpaState(2, 'restoreWizardData:program');
                    
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