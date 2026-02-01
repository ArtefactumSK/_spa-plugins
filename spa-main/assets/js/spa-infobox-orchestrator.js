/**
 * SPA Infobox Wizard – sekcie
 * CENTRALIZOVANÉ RIADENIE VIDITEĽNOSTI
 */

// ========== FIELDS REGISTRY MERGE ==========
// Merge JSON registry into spaConfig.fields (if available)
// This ensures all field mappings (including spa_frequency) are available at runtime
(function() {
    if (typeof window.spaFieldsRegistry !== 'undefined') {
        window.spaConfig = window.spaConfig || {};
        window.spaConfig.fields = {
            ...(window.spaConfig.fields || {}),    // PHP fields (3 keys) - base
            ...window.spaFieldsRegistry            // JSON registry (27 keys) - OVERRIDE
        };
        console.log('[SPA Registry] Fields merged:', Object.keys(window.spaConfig.fields).length, 'keys');
    } else {
        console.warn('[SPA Registry] spaFieldsRegistry not found – using runtime fields only');
    }
})();

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

    // 2. Skry child_only + adult_only polia
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
 * Architektúra: CASE → BASE → SCOPE → DERIVED
 */
window.updateSectionVisibility = function() {
    console.log('[SPA Section Control] ========== UPDATE START ==========');

    // ⭐ GUARD: spaConfig.fields MUSÍ existovať
    if (!window.spaConfig || !spaConfig.fields) {
        console.warn('[SPA Section Control] spaConfig.fields not ready – skipping');
        return;
    }

    // ========== CASE PHASE ==========
    const citySelected = !!(window.wizardData?.city_name && window.wizardData.city_name.trim() !== '');
    const programSelected = !!(window.wizardData?.program_name && window.wizardData.program_name.trim() !== '');
    const canShowProgramFlow = citySelected && programSelected; // CASE 2

    // PROGRAM TYPE determination
    let programType = null;

    // Try to determine from program select (PAGE 1)
    const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    if (programField && programField.value) {
        const opt = programField.options[programField.selectedIndex];
        const ageMin = parseInt(opt?.getAttribute('data-age-min'), 10);
        if (!isNaN(ageMin)) programType = ageMin < 18 ? 'child' : 'adult';
    }

    // Fallback: determine from infoboxData (PAGE 2+, when select is not in DOM)
    if (!programType && window.infoboxData?.program?.age_min) {
        const ageMin = parseFloat(window.infoboxData.program.age_min);
        if (!isNaN(ageMin)) programType = ageMin < 18 ? 'child' : 'adult';
        console.log('[SPA Orchestrator] Program type determined from infoboxData:', programType);
    }

    window.spaCurrentProgramType = programType;

    // RESOLVED TYPE → spa_resolved_type
    const resolvedTypeField = document.querySelector(`input[name="${spaConfig.fields.spa_resolved_type}"]`);
    if (resolvedTypeField) resolvedTypeField.value = programType || '';

    console.log('[SPA Section Control] CASE determined:', canShowProgramFlow ? '2' : (citySelected ? '1' : '0'), 'Type:', programType);

    // Set radio based on program type
    if (canShowProgramFlow && programType) {
        window.setSpaRegistrationType(programType);
    }

    // spa_frequency - BASE field for CASE 2 (always visible when program selected)
    const frequencyField = document.querySelector(`[name="${spaConfig.fields.spa_frequency}"]`);
    if (frequencyField) {
        const wrap = frequencyField.closest('.gfield');
        if (wrap) {
            wrap.style.display = canShowProgramFlow ? '' : 'none';
        }
    }

    // ========== SECTIONS VISIBILITY (CASE 2) ==========
    document.querySelectorAll('.spa-section-common').forEach(sec => {
        sec.style.display = canShowProgramFlow ? '' : 'none';
    });

    document.querySelectorAll('.spa-section-child').forEach(sec => {
        sec.style.display = (canShowProgramFlow && programType === 'child') ? '' : 'none';
    });

    document.querySelectorAll('.spa-section-adult').forEach(sec => {
        sec.style.display = (canShowProgramFlow && programType === 'adult') ? '' : 'none';
    });

    // ========== SCOPE FIELDS (child_only / adult_only) ==========
    if (canShowProgramFlow && programType) {
        [...window.spaFieldScopes.child_only, ...window.spaFieldScopes.adult_only].forEach(fieldName => {
            const scope = window.getSpaFieldScope(fieldName);
            let visible = false;

            if (scope === 'child') visible = (programType === 'child');
            if (scope === 'adult') visible = (programType === 'adult');

            window.spaSetFieldWrapperVisibility(fieldName, visible);
        });
    } else {
        // CASE 0 / CASE 1 - hide all SCOPE fields
        [...window.spaFieldScopes.child_only, ...window.spaFieldScopes.adult_only].forEach(fieldName => {
            window.spaSetFieldWrapperVisibility(fieldName, false);
        });
    }
    
    // ========== DERIVED FIELDS ==========
    // DERIVED FIELD: Set data-is-child on birthnumber field based on program age_min
        const birthNumberField = document.querySelector(
            `input[name="${spaConfig.fields.spa_member_birthnumber}"]`
        );

        const ageMin = parseInt(programData?.age_min, 10);

        if (birthNumberField && !isNaN(ageMin)) {
            const isChild = ageMin < 18;

            birthNumberField.setAttribute('data-is-child', isChild ? 'true' : 'false');

            console.log(
                '[SPA Orchestrator] Derived program type from age_min:',
                isChild ? 'CHILD' : 'ADULT',
                '(age_min =', ageMin, ')'
            );
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