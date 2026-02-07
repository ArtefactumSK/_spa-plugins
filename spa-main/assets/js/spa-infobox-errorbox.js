/**
 * SPA Infobox Wizard – ErrorBox UI
 * CENTRÁLNE ZOBRAZENIE STAVU VÝBERU
 */

/**
 * ERRORBOX: Centrálne zobrazenie stavu výberu
 */
window.updateErrorBox = function() {
    const state = window.currentState || 0;
        // GUARD: Bez user interakcie (GET init) sa errorbox NEZOBRAZUJE
    // Výnimka: explicitné GET state chyby (invalidCity / invalidProgram)
    const hasUserInteracted = (window.spaInputAuthority === 'select');

    
    // SPA errorbox (pre GET/state chyby)
    let spaErrorBox = document.querySelector('.spa-errorbox--state');
    
    // GF errorbox (len na detekciu validačných chýb)
    const gfErrorBox = document.querySelector('.gform_validation_errors');
    const gfErrorExists = gfErrorBox && gfErrorBox.innerHTML.trim() !== '';
    
    // ⭐ DETECTION: GF vykresil validation errors
    if (gfErrorExists && !window.spaErrorState.invalidCity && !window.spaErrorState.invalidProgram) {
        console.log('[SPA ErrorBox] GF validation detected, switching to SPA control');
        window.spaErrorState.formInvalid = true;
        window.spaErrorState.errorType = 'validation';
    }
    
    // ─────────────────────────────────────────────
    // 1. Najprv vyriešme chyby z URL parametrov → zmiznú hneď po oprave
    // ─────────────────────────────────────────────
    if (window.spaErrorState.invalidCity || window.spaErrorState.invalidProgram) {
        let message = '';

        if (window.spaErrorState.invalidCity) {
            const urlParams = new URLSearchParams(window.location.search);
            const cityParam = urlParams.get('city');
            message = `<h2 class="gform_submission_error">⛔ Neplatné mesto v odkaze</h2>
                       <p>Mesto "<span>${cityParam}</span>" nebolo nájdené. Prosím, vyberte mesto zo zoznamu.</p>`;
            window.spaErrorState.errorType = 'state';
        } 
        else if (window.spaErrorState.invalidProgram) {
            const urlParams = new URLSearchParams(window.location.search);
            const programParam = urlParams.get('program');
            message = `<h2 class="gform_submission_error">⛔ Neplatný program v odkaze</h2>
                       <p>Program s ID "<span>${programParam}</span>" nebol nájdený alebo nie je dostupný v zvolenom meste. Prosím, vyberte program zo zoznamu.</p>`;
            window.spaErrorState.errorType = 'state';
        }

        if (message) {
            // Vytvor/aktualizuj SPA errorbox (NIE GF errorbox)
            if (!spaErrorBox) {
                const gformBody = document.querySelector('.gform_body');
                if (gformBody) {
                    spaErrorBox = document.createElement('div');
                    spaErrorBox.className = 'spa-errorbox--state gform_validation_errors';
                    gformBody.insertBefore(spaErrorBox, gformBody.firstChild);
                } else {
                    return;
                }
            }
            spaErrorBox.innerHTML = message;
            spaErrorBox.style.display = 'block';
            console.log('[SPA ErrorBox] SPA state error displayed');
            return;
        }
    }

    // ─────────────────────────────────────────────
    // 2. Ak sú GF validation chyby → VLASTNÝ TEXT + KONTEXT
    // ─────────────────────────────────────────────
    /* if (window.spaErrorState.formInvalid) {
        // ⭐ KONTEXT: Pridaj info o zvolenom meste/programe
        let contextInfo = '';
        if (window.wizardData.city_name) {
            contextInfo += `<p><strong>Mesto:</strong> ${window.wizardData.city_name}</p>`;
        }
        if (window.wizardData.program_name) {
            contextInfo += `<p><strong>Program:</strong> ${window.wizardData.program_name}</p>`;
        }
        
        const message = `<h2 class="gform_submission_error">⛔ Chyba vo formulári</h2>
                        <p>Vznikol problém s vaším formulárom. Prezrite si zvýraznené polia nižšie.</p>
                        ${contextInfo}`;
        
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
        
        // ❌ REMOVED: side-effect call
        
        return;
    } */

        if (window.spaErrorState.formInvalid) {

            // GF validation chyba – iba zobraz informáciu
            // ❌ NEROBIŤ: reset, hide sections, updateSectionVisibility
            console.log('[SPA ErrorBox] GF validation detected – no CASE changes');
        
            return;
        }
        
           
    
    // ─────────────────────────────────────────────
    // 3. Žiadne SPA state chyby → VYČISTIŤ SPA errorbox
    // ─────────────────────────────────────────────
    if (spaErrorBox) {
        spaErrorBox.innerHTML = '';
        spaErrorBox.style.display = 'none';
        console.log('[SPA ErrorBox] SPA errorbox cleared');
    }
};

/**
 * HOOK: Po GF page load (submit/next/back) → check validation errors
 */
if (typeof jQuery !== 'undefined') {
    jQuery(document).on('gform_post_render', function(event, form_id, current_page) {
        console.log('[SPA ErrorBox] GF post_render, checking errors');

        setTimeout(() => {
            const gfError = document.querySelector('.gform_validation_errors');
            const hasError = gfError && gfError.innerHTML.trim() !== '';

            if (hasError) {
                console.log('[SPA ErrorBox] GF errors found');

                // označ typ chyby (validation)
                window.spaErrorState.formInvalid = true;
                window.spaErrorState.errorType = 'validation';

                // ⭐ dôležité: updateErrorBox v "silent" režime (bez side-effectov)
                window.__spaErrorboxSilent = true;

                if (!window.__spaRestoringState) {
                    window.updateErrorBox();
                } else {
                    console.log('[SPA ErrorBox] SKIPPED - restore in progress');
                }

                window.__spaErrorboxSilent = false;

            } else if (window.spaErrorState.formInvalid) {
                // clear flag, ak chyby zmizli
                window.spaErrorState.formInvalid = false;
                window.spaErrorState.errorType = null;

                window.__spaErrorboxSilent = true;
                window.updateErrorBox();
                window.__spaErrorboxSilent = false;
            }
        }, 50);
    });
}


/**
 * SYNCHRONIZÁCIA: Required ↔ Visibility
 * Skryté pole NESMIE byť required
 */
window.syncRequiredWithVisibility = function() {
    console.log('[SPA Required] Syncing required attributes with visibility');
    
    // Nájdi všetky polia v sekcii s .gfield_visibility_hidden
    const allFields = document.querySelectorAll('.gfield');
    
    allFields.forEach(field => {
        const isHidden = field.style.display === 'none' || 
                        field.classList.contains('gfield_visibility_hidden');
        
        const input = field.querySelector('input, select, textarea');
        
        if (input && isHidden) {
            // Skryté pole → odober required
            if (input.hasAttribute('aria-required')) {
                input.setAttribute('data-original-required', 'true');
                input.removeAttribute('aria-required');
                input.removeAttribute('required');
                console.log('[SPA Required] Removed required from hidden field:', input.name);
            }
        } else if (input && !isHidden) {
            // Viditeľné pole → obnov required ak bolo
            if (input.getAttribute('data-original-required') === 'true') {
                input.setAttribute('aria-required', 'true');
                input.setAttribute('required', 'required');
                input.removeAttribute('data-original-required');
                console.log('[SPA Required] Restored required to visible field:', input.name);
            }
        }
    });
};

// ❌ REMOVED: wrapper ktorý volal updateSectionVisibility (side-effect)

// Trigger aj po GF render
if (typeof jQuery !== 'undefined') {
    jQuery(document).on('gform_post_render', function() {
        setTimeout(() => {
            window.syncRequiredWithVisibility();
        }, 100);
    });
}