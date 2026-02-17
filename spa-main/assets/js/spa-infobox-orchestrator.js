
/**
 * SPA Infobox Wizard – sekcie
 * CENTRALIZOVANÉ RIADENIE VIDITEĽNOSTI
 */

// ========== DUPLICATE DETECTION ==========
(function() {
    const functionsToCheck = [
        'updateSectionVisibility',
        'spaInitSectionOrchestrator',
        'hideAllSectionsOnInit'
    ];
    
    functionsToCheck.forEach(fnName => {
        if (typeof window[fnName] === 'function') {
            console.warn('[SPA DUPLICATE] ' + fnName + ' already exists before orchestrator load', {
                __source: window[fnName].__source || 'unknown',
                type: typeof window[fnName]
            });
        }
    });
})();

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

/**
 * SPA FIELD SCOPE – JEDINÝ ZDROJ PRAVDY
 */
window.spaCurrentProgramType = null;

/**
 * SPA FIELD SCOPE – JEDINÝ ZDROJ PRAVDY
 * GUARD: Wait for spaConfig to be ready
 */
(function() {
    // Retry until spaConfig.fields is available
    let attempts = 0;
    const maxAttempts = 50;
    
    const initScopes = function() {
        attempts++;
        
        // Check if spaConfig.fields exists
        if (typeof window.spaConfig !== 'undefined' && window.spaConfig.fields) {
            console.log('[SPA Orchestrator] spaConfig.fields ready, initializing scopes');
            
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
            
            console.log('[SPA Orchestrator] Field scopes initialized:', Object.keys(window.spaFieldScopes));
        } else if (attempts < maxAttempts) {
            console.warn('[SPA Orchestrator] spaConfig.fields not ready, retrying... (' + attempts + '/' + maxAttempts + ')');
            setTimeout(initScopes, 50);
        } else {
            console.error('[SPA Orchestrator] TIMEOUT: spaConfig.fields never loaded');
        }
    };
    
    // Start retry loop
    initScopes();
})();

// TEMPORARY FALLBACK (until scopes are loaded)
window.spaFieldScopes = window.spaFieldScopes || {
    child_only: [],
    adult_only: []
};

/**
 * Vráti scope podľa: 'child' | 'adult' | 'always'
 */
window.getSpaFieldScope = function(fieldName) {
    if (!window.spaFieldScopes) return 'always';
    if (window.spaFieldScopes.child_only && window.spaFieldScopes.child_only.includes(fieldName)) return 'child';
    if (window.spaFieldScopes.adult_only && window.spaFieldScopes.adult_only.includes(fieldName)) return 'adult';
    return 'always';
};


/**
 * Skrytie všetkých sekcií + polí pri INIT
 */
window.hideAllSectionsOnInit = function() {
    console.log('[SPA Init] ========== INIT RESET ==========');

    // ⚠️ GUARD: spaConfig.fields MUSÍ existovať
    if (!window.spaConfig || !spaConfig.fields) {
        console.warn('[SPA Init] spaConfig.fields not ready – skipping');
        return;
    }
    
    // ⚠️ GUARD: spaFieldScopes MUSÍ existovať
    if (!window.spaFieldScopes || !window.spaFieldScopes.child_only) {
        console.warn('[SPA Init] spaFieldScopes not ready – skipping');
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
            if (wrap && !wrap.classList.contains('spa-infobox-container')) {
                wrap.style.display = 'none';
            }
        }
    });

    // window.spa_sections_hidden = true;
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
 * DEBUG HELPER: Diagnostika sekcií
 */
window.spaDebugVisibility = function() {
    console.log('[SPA DEBUG] ========== VISIBILITY DIAGNOSTIC ==========');
    
    // Scope state
    console.log('[SPA DEBUG] Scope State:', {
        wizard_program_type: window.wizardData?.program_type,
        spaCurrentProgramType: window.spaCurrentProgramType,
        age_min: window.infoboxData?.program?.age_min,
        program_name: window.wizardData?.program_name
    });
    
    // Section detection
    const patterns = ['common', 'child', 'adult'];
    patterns.forEach(type => {
        const selectors = [
            `.spa-section-${type}`,
            `[data-section="${type}"]`,
            `#spa_section_${type}`,
            `.gfield[id*="section_${type}"]`,
            `.gsection.spa-section-${type}`
        ];
        
        console.log(`[SPA DEBUG] Sections for "${type}":`);
        selectors.forEach(sel => {
            const found = document.querySelectorAll(sel);
            if (found.length > 0) {
                console.log(`  ✅ ${sel}: ${found.length} found`);
                found.forEach((el, i) => {
                    const visible = el.style.display !== 'none' && el.style.visibility !== 'hidden';
                    console.log(`     [${i}] visible=${visible}, display="${el.style.display}", visibility="${el.style.visibility}"`);
                });
            } else {
                console.log(`  ❌ ${sel}: 0 found`);
            }
        });
    });
    
    // Field detection
    if (window.spaFieldScopes) {
        console.log('[SPA DEBUG] Child-only fields:');
        window.spaFieldScopes.child_only.forEach(fieldName => {
            const el = document.querySelector(`[name="${fieldName}"]`);
            if (el) {
                const wrap = el.closest('.gfield');
                const visible = wrap && wrap.style.display !== 'none';
                console.log(`  ${fieldName}: ${visible ? '✅ visible' : '❌ hidden'}`);
            } else {
                console.log(`  ${fieldName}: ⚠️ not found`);
            }
        });
    }
    
    console.log('[SPA DEBUG] ========================================');
};
// Tag source
window.spaDebugVisibility.__source = 'spa-infobox-orchestrator.js';

/**
 * EXTENDED DEBUG DUMP: Comprehensive state + DOM reality check
 */
window.spaDebugDump = function() {
    console.log('[SPA DUMP] ========== COMPREHENSIVE DIAGNOSTIC ==========');
    
    // STATE
    console.log('[SPA DUMP] STATE:', {
        'wizardData.program_type': window.wizardData?.program_type,
        'wizardData.city_name': window.wizardData?.city_name,
        'wizardData.program_name': window.wizardData?.program_name,
        'spaCurrentProgramType': window.spaCurrentProgramType,
        'currentState': window.currentState
    });
    
    // PROGRAM DATA
    if (window.infoboxData?.program) {
        console.log('[SPA DUMP] PROGRAM DATA:', {
            'age_min': window.infoboxData.program.age_min,
            'age_max': window.infoboxData.program.age_max,
            'title': window.infoboxData.program.title
        });
    } else {
        console.log('[SPA DUMP] PROGRAM DATA: not loaded');
    }
    
    // SECTION WRAPPERS
    const sectionCounts = {
        common: document.querySelectorAll('.spa-section-common').length,
        child: document.querySelectorAll('.spa-section-child').length,
        adult: document.querySelectorAll('.spa-section-adult').length
    };
    console.log('[SPA DUMP] SECTION WRAPPERS:', sectionCounts);
    
    if (sectionCounts.child === 0 && sectionCounts.adult === 0) {
        console.warn('[SPA DUMP] ⚠️ SECTION WRAPPERS MISSING – FIELD MODE REQUIRED');
    }
    
    // FIELD MAPPING CHECK
    if (window.spaFieldScopes) {
        console.log('[SPA DUMP] FIELD MAPPING CHECK:');
        
        const checkFields = [
            ...window.spaFieldScopes.child_only.slice(0, 2),
            ...window.spaFieldScopes.adult_only
        ];
        
        checkFields.forEach(fieldName => {
            const configKey = Object.keys(spaConfig.fields || {}).find(k => spaConfig.fields[k] === fieldName);
            const el = document.querySelector(`[name="${fieldName}"]`);
            const wrap = el ? el.closest('.gfield') : null;
            const computedDisplay = wrap ? window.getComputedStyle(wrap).display : 'N/A';
            const computedVisibility = wrap ? window.getComputedStyle(wrap).visibility : 'N/A';
            
            console.log(`  ${configKey || 'UNKNOWN'} (${fieldName}):`, {
                'in_spaConfig': !!configKey,
                'element_exists': !!el,
                'wrapper_exists': !!wrap,
                'computed_display': computedDisplay,
                'computed_visibility': computedVisibility,
                'visible': computedDisplay !== 'none' && computedVisibility !== 'hidden'
            });
        });
    }
    
    console.log('[SPA DUMP] ========================================');
};


/**
 * Prepni autoritu z GET → SELECT (JEDNOSMERNÁ OPERÁCIA)
 */
window.spaSwitchToSelectAuthority = function(reason) {
    if (window.spaInputAuthority === 'select') {
        console.log('[SPA Authority] Already using SELECT authority');
        return;
    }
    
    window.spaInputAuthority = 'select';
    console.log('[SPA Authority] ✅ Switched to SELECT authority:', reason);
};



/**
 * RIADENIE VIDITEĽNOSTI SEKCIÍ + POLÍ
 * Architektúra: CASE → BASE → SCOPE → DERIVED
 */
window.updateSectionVisibility = function() {
    // ⛔ GUARD: počas restore NESMIEME aplikovať CASE ani STATE reset
    if (window.__spaRestoringState === true) {
        console.log('[SPA Section Control] Restore in progress – skipping CASE/STATE logic');
        return;
    }
            // GF VALIDATION MODE:
            // Pri GF validačnej chybe NESMIEME resetovať/gatovať (inak sa schová program select),
            // ale MUSÍME odomknúť predchádzajúce gate (clearCaseGate) a nechať GF ukázať chyby.
            const isGfValidation = (window.spaErrorState?.formInvalid === true);
            if (isGfValidation) {
                console.log('[SPA Orchestrator] GF validation mode – no resets, but clearing gates');
            }

    
    console.log('[SPA Section Control] ========== UPDATE START ==========');
    
    if (window.SPA_DEBUG === true) {
        console.trace('[SPA Section Control] Called from:');
    }
    
    document.querySelectorAll('.spa-section-common, .spa-section-child, .spa-section-adult').forEach(sec => {
        sec.style.display = 'none';
        sec.style.visibility = 'hidden';
    });
    console.log('[SPA Section Control] Hard reset applied');
    
    if (!window.spaConfig || !spaConfig.fields) {
        console.warn('[SPA Section Control] spaConfig.fields not ready – skipping');
        return;
    }

    // ========== CASE DETECTION (DOM-FIRST) ==========
    const cityEl = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
    const programEl = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);

    const cityValueDOM = cityEl?.value || '';
    const programValueDOM = programEl?.value || '';

    // DOM je zdroj pravdy pre CASE; fallback je wizardData (kvôli re-render/restore)
    const citySelected = cityValueDOM.trim() !== ''
    ? true
    : !!(window.wizardData?.city_name && window.wizardData.city_name.trim() !== '');

    const programSelected = programValueDOM.trim() !== ''
    ? true
    : !!(window.wizardData?.program_name && window.wizardData.program_name.trim() !== '');

    const caseNum = !citySelected ? 0 : (!programSelected ? 1 : 2);

    console.log('[SPA CASE Detection] DOM-FIRST', {
    case: caseNum,
    citySelected,
    programSelected,
    dom: { cityValue: cityValueDOM, programValue: programValueDOM },
    wizardData: { city: window.wizardData?.city_name, program: window.wizardData?.program_name },
    authority: window.spaInputAuthority
    });

        // ========== GF VALIDATION: UNLOCK GATES + FORCE CITY/PROGRAM VISIBLE ==========
        if (isGfValidation) {
            // Odomkni všetko, čo mohol CASE gate schovať (najmä program select)
            clearCaseGate();
    
            // Force city + program gfield visible (ak existujú)
            const cityGfield = cityEl?.closest('.gfield');
            const programGfield = programEl?.closest('.gfield');
            if (cityGfield) showGfield(cityGfield);
            if (programGfield) showGfield(programGfield);
    
            console.log('[SPA Orchestrator] GF validation – gates cleared, city/program forced visible');
            console.log('[SPA Section Control] ========== UPDATE END (GF VALIDATION) ==========');
            return;
        }
    
    // ========== STATE SANITIZATION (AUTHORITY-AWARE) ==========
    // Apply only when SELECT authority is active
    if (window.wizardData && window.spaInputAuthority === 'select') {
        // 1) Ak je mesto v DOM prázdne => vynúť city/program reset v state
        if (cityValueDOM.trim() === '') {
            window.wizardData.city_name = '';
            window.wizardData.program_name = '';
            window.wizardData.program_type = null;
            window.spaCurrentProgramType = null;

            if (window.infoboxData) {
                window.infoboxData.program = null;
            }
        }

        // 2) Ak je program v DOM prázdny => vynúť program reset v state
        if (programValueDOM.trim() === '') {
            window.wizardData.program_name = '';
            window.wizardData.program_type = null;
            window.spaCurrentProgramType = null;

            if (window.infoboxData) {
                window.infoboxData.program = null;
            }
        }
    }

    
    // ========== CASE GATE APPLICATION ==========
    const cityGfield = cityEl?.closest('.gfield');
    const programGfield = programEl?.closest('.gfield');
    
    if (caseNum === 0 || caseNum === 1) {
        // RESET: Internal state PRED applyCaseGate
        window.spaCurrentProgramType = null;
        if (window.wizardData) {
            window.wizardData.program_name = '';
            window.wizardData.program_type = null;
        }
        if (window.infoboxData) {
            window.infoboxData.program = null;
        }
        
        applyCaseGate(caseNum, cityGfield, programGfield);
        
        console.log('[SPA Section Control] CASE', caseNum, '— early return (gate applied)');
        
        // FINAL LOG
        const gfieldsVisible = document.querySelectorAll('.gfield:not([style*="display: none"]):not([style*="display:none"])').length;
        console.log('[SPA CASE FINAL]', {
            case: caseNum,
            citySelected,
            programSelected,
            programType: null,
            dom: { cityValue: cityValueDOM, programValue: programValueDOM },
            visibleCounts: {
                gfieldsVisible: gfieldsVisible,
                commonSectionsVisible: 0,
                childSectionsVisible: 0,
                adultSectionsVisible: 0
            }
        });
        
        console.log('[SPA Section Control] ========== UPDATE END ==========');
        return;
    }
    
    // ========== CASE2: Clear gate + apply scope ==========
    clearCaseGate();
    
    const programType = window.wizardData?.program_type ?? null;
    
    console.log('[SPA Section Control] CASE2 – programType:', programType);
    
    window.spaCurrentProgramType = programType;
    
    const resolvedTypeField = document.querySelector(`input[name="${spaConfig.fields.spa_resolved_type}"]`);
    if (resolvedTypeField) resolvedTypeField.value = programType || '';
    
    const frequencyField = document.querySelector(`[name="${spaConfig.fields.spa_frequency}"]`);
    if (frequencyField) {
        const wrap = frequencyField.closest('.gfield');
        if (wrap) wrap.style.display = '';
    }
    
    // ========== SECTIONS VISIBILITY (scope MUSÍ byť určený) ==========
    const canShowSections = (programType !== null);
    
    const sectionWrappersExist = {
        common: document.querySelectorAll('.spa-section-common').length > 0,
        child: document.querySelectorAll('.spa-section-child').length > 0,
        adult: document.querySelectorAll('.spa-section-adult').length > 0
    };
    
    const hasScopeWrappers = sectionWrappersExist.child || sectionWrappersExist.adult;
    
    if (!hasScopeWrappers && canShowSections) {
        console.warn('[SPA Section Control] ⚠️ SECTION WRAPPERS MISSING – using FIELD MODE');
    }
    
    if (canShowSections) {
        if (sectionWrappersExist.common) {
            document.querySelectorAll('.spa-section-common').forEach(sec => {
                sec.style.display = '';
                sec.style.visibility = 'visible';
            });
        }

        if (programType === 'child' && sectionWrappersExist.child) {
            document.querySelectorAll('.spa-section-child').forEach(sec => {
                sec.style.display = '';
                sec.style.visibility = 'visible';
            });
            console.log('[SPA Section Control] ✅ CHILD sections shown');
        } else if (programType === 'adult' && sectionWrappersExist.adult) {
            document.querySelectorAll('.spa-section-adult').forEach(sec => {
                sec.style.display = '';
                sec.style.visibility = 'visible';
            });
            console.log('[SPA Section Control] ✅ ADULT sections shown');
        } else if (!hasScopeWrappers) {
            console.log('[SPA Section Control] Using FIELD MODE (no section wrappers)');
        }
    }

    // ========== SCOPE FIELDS (child_only / adult_only) ==========
    if (programType) {
        [...window.spaFieldScopes.child_only, ...window.spaFieldScopes.adult_only].forEach(fieldName => {
            const fieldScope = window.getSpaFieldScope(fieldName);
            let visible = false;

            if (fieldScope === 'child') visible = (programType === 'child');
            if (fieldScope === 'adult') visible = (programType === 'adult');

            window.spaSetFieldWrapperVisibility(fieldName, visible);
        });
    } else {
        [...window.spaFieldScopes.child_only, ...window.spaFieldScopes.adult_only].forEach(fieldName => {
            window.spaSetFieldWrapperVisibility(fieldName, false);
        });
    }
    
    // ========== DERIVED FIELDS ==========
    const birthNumberField = document.querySelector(`input[name="${spaConfig.fields.spa_member_birthnumber}"]`);
    if (birthNumberField) {
        if (programType === 'child') {
            birthNumberField.setAttribute('data-is-child', 'true');
        } else if (programType === 'adult') {
            birthNumberField.setAttribute('data-is-child', 'false');
        } else {
            birthNumberField.removeAttribute('data-is-child');
        }
    }

    // ========== FINAL LOG ==========
    const gfieldsVisible = document.querySelectorAll('.gfield:not([style*="display: none"]):not([style*="display:none"])').length;
    const commonVisible = document.querySelectorAll('.spa-section-common:not([style*="display: none"])').length;
    const childVisible = document.querySelectorAll('.spa-section-child:not([style*="display: none"])').length;
    const adultVisible = document.querySelectorAll('.spa-section-adult:not([style*="display: none"])').length;
    
    console.log('[SPA CASE FINAL]', {
        case: caseNum,
        citySelected,
        programSelected,
        programType,
        dom: { cityValue: cityValueDOM, programValue: programValueDOM },
        visibleCounts: {
            gfieldsVisible: gfieldsVisible,
            commonSectionsVisible: commonVisible,
            childSectionsVisible: childVisible,
            adultSectionsVisible: adultVisible
        }
    });
    
    console.log('[SPA Section Control] ========== UPDATE END ==========');
};

function showGfield(gf) {
    if (!gf) return;
    gf.style.display = '';
    gf.style.visibility = '';
    delete gf.dataset.spaCaseHidden;
}

function hideGfield(gf) {
    if (!gf) return;
    gf.style.display = 'none';
    gf.style.visibility = 'hidden';
    gf.dataset.spaCaseHidden = '1';
}

function applyCaseGate(caseNum, cityGfield, programGfield, retryCount = 0) {
    const MAX_RETRIES = 10;
    const RETRY_DELAY = 80;
    
    console.log('[SPA CASE Gate] Applying CASE', caseNum, 'retry:', retryCount);
    
    const allGfields = document.querySelectorAll('.gfield');
    const submitBtn = document.querySelector('.gform_footer, .gform_page_footer');
    const pageBreaks = document.querySelectorAll('.gform_page_footer, .gf_step');
    // Získaj program element pre RESET
    const programEl = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    
    // Helper: Je to infobox? (NIKDY neskrývať)
    const isInfobox = (gf) => {
        return gf.classList.contains('spa-infobox-container') || 
               gf.querySelector('.spa-infobox-wrapper') !== null ||
               gf.querySelector('.spa-infobox-content') !== null ||
               gf.id === 'gfield_spa_infobox' ||
               (gf.querySelector && gf.querySelector('[id*="infobox"]') !== null);
    };
    
    if (caseNum === 0) {

        // 1️⃣ RESET HODNÔT (deterministicky, raz)
        allGfields.forEach(gf => {
            if (isInfobox(gf) || gf === cityGfield) return;
    
            const inputs = gf.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = false;
                } else {
                    input.value = '';
                    if (input.tagName === 'SELECT') input.selectedIndex = 0;
                }
            });
        });
    
        // Program
        if (programEl) {
            programEl.value = '';
            programEl.selectedIndex = 0;
        }
    
        // Frequency
        document.querySelectorAll(`[name="${spaConfig.fields.spa_frequency}"]`)
            .forEach(el => {
                if (el.type === 'radio' || el.type === 'checkbox') el.checked = false;
                else {
                    el.value = '';
                    if (el.tagName === 'SELECT') el.selectedIndex = 0;
                }
            });
    
        // Resolved type
        const resolvedTypeEl = document.querySelector(
            `input[name="${spaConfig.fields.spa_resolved_type}"]`
        );
        if (resolvedTypeEl) resolvedTypeEl.value = '';
    
        // 2️⃣ HARD HIDE – JEDINÝ ZDROJ PRAVDY
        allGfields.forEach(gf => hideGfield(gf));
        if (submitBtn) hideGfield(submitBtn);
        pageBreaks.forEach(pb => hideGfield(pb));
    
        // 3️⃣ POVOLENÉ V CASE0
        if (cityGfield) showGfield(cityGfield);
    
        allGfields.forEach(gf => {
            if (isInfobox(gf)) showGfield(gf);
        });
    
        return;
    }
     else if (caseNum === 1) {
        if (!programGfield || programGfield.style.display === 'none') {
            if (retryCount < MAX_RETRIES) {
                console.warn('[SPA CASE Gate] Program field not ready, retrying...', retryCount + 1);
                setTimeout(() => {
                    const cityEl = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
                    const freshProgramGfield = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`)?.closest('.gfield');
                    const freshCityGfield = cityEl?.closest('.gfield');
                    applyCaseGate(caseNum, freshCityGfield, freshProgramGfield, retryCount + 1);
                }, RETRY_DELAY);
                return;
            } else {
                console.error('[SPA CASE Gate] Program field not found after', MAX_RETRIES, 'retries');
            }
        }
        
        // RESET: Vyčisti všetky polia okrem city a program
        allGfields.forEach(gf => {
            if (gf === cityGfield || gf === programGfield || isInfobox(gf)) {
                return; // Preskočiť
            }
            
            // Vyčisti input values
            const inputs = gf.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = false;
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                    input.value = '';
                } else {
                    input.value = '';
                }
            });
        });

        allGfields.forEach(gf => {
            if (gf === cityGfield || gf === programGfield) {
                showGfield(gf);
            } else if (isInfobox(gf)) {
                showGfield(gf);
                console.log('[SPA CASE Gate] Infobox protected (CASE 1)');
            } else {
                hideGfield(gf);
            }
        });
        if (submitBtn) hideGfield(submitBtn);
        pageBreaks.forEach(pb => hideGfield(pb));
    }
}

function clearCaseGate() {
    console.log('[SPA CASE Gate] Clearing all gates');
    const allGfields = document.querySelectorAll('.gfield[data-spa-case-hidden="1"]');
    allGfields.forEach(gf => {
        gf.style.display = '';
        gf.style.visibility = '';
        delete gf.dataset.spaCaseHidden;
    });
    
    const submitBtn = document.querySelector('.gform_footer, .gform_page_footer');
    const pageBreaks = document.querySelectorAll('.gform_page_footer, .gf_step');
    if (submitBtn && submitBtn.dataset.spaCaseHidden === '1') {
        showGfield(submitBtn);
    }
    pageBreaks.forEach(pb => {
        if (pb.dataset.spaCaseHidden === '1') {
            showGfield(pb);
        }
    });
}


/**
 * INIT + EVENT BINDING
 */
/**
 * RE-APPLY scope (callable multiple times, safe)
 */
window.spaApplyScopeState = function(force) {
    console.log('[SPA Scope] Applying scope state', force ? '(FORCED)' : '');
    
    if (!window.spaConfig || !spaConfig.fields) {
        console.warn('[SPA Scope] spaConfig.fields not ready — skipping');
        return;
    }
    
    if (typeof window.updateSectionVisibility === 'function') {
        if (force) {
            window.__spaForceVisibilityUpdate = true;
        }
        window.updateSectionVisibility();
        if (force) {
            window.__spaForceVisibilityUpdate = false;
        }
    }
};
/**
 * INIT orchestrator (bind listeners once)
 */
window.spaInitSectionOrchestrator = function() {
    console.log('[SPA SRC] spaInitSectionOrchestrator called');
    
    // GUARD: Already initialized
    if (window.__spaOrchestratorBound) {
        console.log('[SPA Orchestrator] Already bound at:', new Date(window.__spaOrchestratorBoundAt).toISOString(), '— skipping bind');
        // ✅ NOVÉ: Re-apply scope (safe, idempotent, FORCED after pagebreak)
        window.spaApplyScopeState(true);
        return;
    }
    
    if (!window.spaConfig || !spaConfig.fields) {
        console.warn('[SPA Orchestrator] spaConfig.fields not ready — skipping');
        return;
    }

    window.spaVisibilityControlled = true;
    window.__spaOrchestratorBound = true;
    window.__spaOrchestratorBoundAt = Date.now();

    // ✅ Initial scope apply
    if (typeof window.hideAllSectionsOnInit === 'function') {
        window.hideAllSectionsOnInit();
    }
    window.spaApplyScopeState();

    // ✅ Bind event listeners (only once)
    const cityEl = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
    const programEl = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    const freqEl = document.querySelector(`[name="${spaConfig.fields.spa_frequency}"]`);   

    const handler = () => {
        if (typeof window.updateSectionVisibility === 'function') {
            window.updateSectionVisibility();
        }
    };

    [cityEl, programEl, freqEl].forEach(el => {
        if (!el) return;
        el.addEventListener('change', handler);
        el.addEventListener('input', handler);
    });

    // Authority switch listener
    document.addEventListener('change', function(e) {
        const name = e.target?.name;
        if (name !== spaConfig.fields.spa_city && name !== spaConfig.fields.spa_program) return;
        if (!e.isTrusted) return;
        if (window.isApplyingGetParams) return;
        if (window.__spaRestoringState) return;
        if (window.spaInputAuthority === 'select') return;
    
        window.spaInputAuthority = 'select';
        console.log('[SPA Authority] Switched to SELECT (user interaction):', name);
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

// ========== SOURCE TAGS (must be AFTER all definitions) ==========
window.updateSectionVisibility.__source = 'spa-infobox-orchestrator.js';
window.spaInitSectionOrchestrator.__source = 'spa-infobox-orchestrator.js';
window.hideAllSectionsOnInit.__source = 'spa-infobox-orchestrator.js';
window.spaDebugDump.__source = 'spa-infobox-orchestrator.js';
console.log('[SPA SRC] All orchestrator functions tagged with source');
