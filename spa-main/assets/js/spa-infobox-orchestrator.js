/**
 * SPA Infobox Wizard – sekcie
 * CENTRALIZOVANÉ RIADENIE VIDITEĽNOSTI
 */

window.spaCurrentProgramType = null;

/**
 * SPA FIELD SCOPE – JEDINÝ ZDROJ PRAVDY
 */
window.spaFieldScopes = {
    child_only: [
        spaConfig.fields.spa_guardian_name_first,
        spaConfig.fields.spa_guardian_name_last,
        spaConfig.fields.spa_parent_email,
        spaConfig.fields.spa_parent_phone,
        spaConfig.fields.spa_client_email,
        spaConfig.fields.spa_consent_guardian,
        spaConfig.fields.spa_member_birthnumber
    ],
    adult_only: [
        spaConfig.fields.spa_client_email_required
    ]
};

/**
 * Vráti scope podľa: 'child' | 'adult' | 'always'
 */
window.getSpaFieldScope = function(fieldName) {
    if (window.spaFieldScopes.child_only.includes(fieldName)) return 'child';
    if (window.spaFieldScopes.adult_only.includes(fieldName)) return 'adult';
    return 'always';
};

/**
 * Skrytie všetkých sekcií + polí pri INIT
 */
window.hideAllSectionsOnInit = function() {
    console.log('[SPA Init] ========== INIT RESET ==========');

    // ⭐ GUARD: spaConfig.fields MUSÍ existovať
    if (!window.spaConfig || !spaConfig.fields) {
        console.warn('[SPA Init] spaConfig.fields not ready – skipping');
        return;
    }

    if (window.spa_sections_hidden) {
        console.log('[SPA Init] Already initialized, skipping');
        return;
    }

    // 1. Skry sekcie
    document.querySelectorAll('.spa-section-common, .spa-section-child, .spa-section-adult').forEach(sec => {
        sec.style.display = 'none';
    });

    // 2. Skry spa_registration_type
    const regTypeField = document.querySelector(`input[name="${spaConfig.fields.spa_registration_type}"]`);
    if (regTypeField) {
        const wrap = regTypeField.closest('.gfield');
        if (wrap) wrap.style.display = 'none';
    }

    // 3. Skry child_only + adult_only polia
    [...window.spaFieldScopes.child_only, ...window.spaFieldScopes.adult_only].forEach(fieldName => {
        const el = document.querySelector(`[name="${fieldName}"]`);
        if (el) {
            const wrap = el.closest('.gfield');
            if (wrap) wrap.style.display = 'none';
        }
    });

    window.spa_sections_hidden = true;
    console.log('[SPA Init] ========== INIT COMPLETE ==========');
};

/**
 * Nastav wrapper viditeľnosť
 */
window.spaSetFieldWrapperVisibility = function(fieldName, visible) {
    const el = document.querySelector(`[name="${fieldName}"]`);
    if (!el) return;

    const wrap = el.closest('.gfield');
    if (!wrap) return;

    wrap.style.display = visible ? '' : 'none';
    el.disabled = !visible;

    if (!visible) {
        if (el.type === 'radio' || el.type === 'checkbox') {
            el.checked = false;
        } else {
            el.value = '';
        }
    }
};

/**
 * RIADENIE VIDITEĽNOSTI SEKCIÍ + POLÍ
 */
window.updateSectionVisibility = function() {
    console.log('[SPA Section Control] ========== UPDATE START ==========');

    // ⭐ GUARD: spaConfig.fields MUSÍ existovať
    if (!window.spaConfig || !spaConfig.fields) {
        console.warn('[SPA Section Control] spaConfig.fields not ready – skipping');
        return;
    }

    const citySelected = !!(window.wizardData?.city_name && window.wizardData.city_name.trim() !== '');
    const programSelected = !!(window.wizardData?.program_name && window.wizardData.program_name.trim() !== '');
    const canShowProgramFlow = citySelected && programSelected;

    // PROGRAM TYPE
    let programType = null;
    const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    if (programField && programField.value) {
        const opt = programField.options[programField.selectedIndex];
        const ageMin = parseInt(opt?.getAttribute('data-age-min'), 10);
        if (!isNaN(ageMin)) programType = ageMin < 18 ? 'child' : 'adult';
    }
    window.spaCurrentProgramType = programType;

    // RESOLVED TYPE → spa_resolved_type
    const resolvedTypeField = document.querySelector(`input[name="${spaConfig.fields.spa_resolved_type}"]`);
    if (resolvedTypeField) resolvedTypeField.value = programType || '';

    // spa_registration_type - zobraz LEN ak program vybraný
    const regTypeField = document.querySelector(`input[name="${spaConfig.fields.spa_registration_type}"]`);
    if (regTypeField) {
        const wrap = regTypeField.closest('.gfield');
        if (wrap) {
            wrap.style.display = canShowProgramFlow ? '' : 'none';

            if (canShowProgramFlow && programType) {
                const radios = document.querySelectorAll(`input[name="${spaConfig.fields.spa_registration_type}"]`);
                radios.forEach(radio => {
                    const isChild = radio.value.toLowerCase().includes('dieť');

                    if (programType === 'child') {
                        radio.checked = isChild;
                        radio.disabled = !isChild;
                    } else {
                        radio.checked = !isChild;
                        radio.disabled = isChild;
                    }
                });
            } else {
                const radios = document.querySelectorAll(`input[name="${spaConfig.fields.spa_registration_type}"]`);
                radios.forEach(r => {
                    r.checked = false;
                    r.disabled = false;
                });
            }
        }
    }

    // SEKCIE - zobraz LEN ak program vybraný
    document.querySelectorAll('.spa-section-common').forEach(sec => {
        sec.style.display = canShowProgramFlow ? '' : 'none';
    });

    document.querySelectorAll('.spa-section-child').forEach(sec => {
        sec.style.display = (canShowProgramFlow && programType === 'child') ? '' : 'none';
    });

    document.querySelectorAll('.spa-section-adult').forEach(sec => {
        sec.style.display = (canShowProgramFlow && programType === 'adult') ? '' : 'none';
    });

    // FIELD SCOPE ENFORCEMENT - polia zobraz LEN ak program vybraný
    if (canShowProgramFlow && programType) {
        [...window.spaFieldScopes.child_only, ...window.spaFieldScopes.adult_only].forEach(fieldName => {
            const scope = window.getSpaFieldScope(fieldName);
            let visible = false;

            if (scope === 'child') visible = (programType === 'child');
            if (scope === 'adult') visible = (programType === 'adult');

            window.spaSetFieldWrapperVisibility(fieldName, visible);
        });
    } else {
        [...window.spaFieldScopes.child_only, ...window.spaFieldScopes.adult_only].forEach(fieldName => {
            window.spaSetFieldWrapperVisibility(fieldName, false);
        });
    }

    console.log('[SPA Section Control] ========== UPDATE END ==========');
};

/**
 * INIT + EVENT BINDING
 */
window.spaInitSectionOrchestrator = function() {
    // ⭐ GUARD: spaConfig.fields MUSÍ existovať
    if (!window.spaConfig || !spaConfig.fields) {
        console.warn('[SPA Orchestrator] spaConfig.fields not ready – skipping');
        return;
    }

    window.spaVisibilityControlled = true;

    if (typeof window.hideAllSectionsOnInit === 'function') window.hideAllSectionsOnInit();
    if (typeof window.updateSectionVisibility === 'function') window.updateSectionVisibility();

    const cityEl = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
    const programEl = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    const freqEl = document.querySelector(`[name="${spaConfig.fields.spa_frequency}"]`);
    const regTypeEls = document.querySelectorAll(`input[name="${spaConfig.fields.spa_registration_type}"]`);

    const handler = () => {
        if (typeof window.updateSectionVisibility === 'function') window.updateSectionVisibility();
    };

    [cityEl, programEl, freqEl].forEach(el => {
        if (!el) return;
        el.addEventListener('change', handler);
        el.addEventListener('input', handler);
    });

    regTypeEls.forEach(el => {
        el.addEventListener('change', handler);
        el.addEventListener('input', handler);
    });

    console.log('[SPA Orchestrator] Initialized and listening');
};

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => window.spaInitSectionOrchestrator(), 50);
});

if (window.jQuery) {
    jQuery(document).on('gform_post_render', function() {
        setTimeout(() => window.spaInitSectionOrchestrator(), 50);
    });
}