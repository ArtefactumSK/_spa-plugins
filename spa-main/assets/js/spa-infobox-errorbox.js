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
    // 2. Ak nie sú žiadne chyby → VYČISTIŤ errorbox
    // ─────────────────────────────────────────────
    if (errorBox) {
        errorBox.innerHTML = '';
    }
    
    // ─────────────────────────────────────────────
    // 3. Skryť Gravity Forms anchor (ak existuje)
    // ─────────────────────────────────────────────
    const gformAnchor = document.querySelector('.gform_validation_errors');
    if (gformAnchor) {
        gformAnchor.style.display = 'none';
    }
    
    if (typeof window.spaRequestVisibilityUpdate === 'function') {
        window.spaRequestVisibilityUpdate('errorbox-clear');
    }
};