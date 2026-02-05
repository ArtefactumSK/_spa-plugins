/**
 * SPA Infobox Wizard – ErrorBox UI
 * CENTRÁLNE ZOBRAZENIE STAVU VÝBERU
 */

/**
 * ERRORBOX: Centrálne zobrazenie stavu výberu
 */
window.updateErrorBox = function() {
    const state = window.currentState || 0;
    
    let errorBox = document.querySelector('.gform_validation_errors');
    const gfErrorExists = errorBox && errorBox.innerHTML.trim() !== '';
    
    // ⭐ DETECTION: GF vykresil validation errors
    if (gfErrorExists && !window.spaErrorState.invalidCity && !window.spaErrorState.invalidProgram) {
        console.log('[SPA ErrorBox] GF validation detected, switching to SPA control');
        window.spaErrorState.formInvalid = true;
        window.spaErrorState.errorType = 'validation';
    }
    
    // ─────────────────────────────────────────────
    // 1. Najprv vyriešme chyby z URL parametrov
    //    → tieto musia zmiznúť hneď po oprave
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
            if (typeof window.spaRequestVisibilityUpdate === 'function') {
                window.spaRequestVisibilityUpdate('errorbox-show');
            }
            return; // ── important: ak je chyba z URL, ďalej nepokračujeme
        }
    }

    // ─────────────────────────────────────────────
    // 2. Ak sú GF validation chyby → VLASTNÝ TEXT
    // ─────────────────────────────────────────────
    if (window.spaErrorState.formInvalid) {
        const message = `<h2 class="gform_submission_error">⛔ Chyba vo formulári</h2>
                         <p>Vznikol problém s vaším formulárom. Prezrite si zvýraznené polia nižšie.</p>`;
        
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
        
        if (typeof window.spaRequestVisibilityUpdate === 'function') {
            window.spaRequestVisibilityUpdate('errorbox-show');
        }
        return;
    }
    
    // ─────────────────────────────────────────────
    // 3. Žiadne chyby → VYČISTIŤ errorbox
    // ─────────────────────────────────────────────
    if (errorBox) {
        errorBox.innerHTML = '';
        errorBox.style.display = 'none';
    }
    
    if (typeof window.spaRequestVisibilityUpdate === 'function') {
        window.spaRequestVisibilityUpdate('errorbox-clear');
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
                console.log('[SPA ErrorBox] GF errors found, triggering updateErrorBox');
                
                // ⭐ GUARD: Nevolaj updateErrorBox ak prebieha restore
                if (!window.__spaRestoringState) {
                    window.updateErrorBox();
                } else {
                    console.log('[SPA ErrorBox] SKIPPED - restore in progress');
                }
            } else if (window.spaErrorState.formInvalid) {
                // Clear flag ak chyby zmizli
                window.spaErrorState.formInvalid = false;
                window.updateErrorBox();
            }
        }, 50);
    });
}