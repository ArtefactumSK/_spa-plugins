/**
 * SPA Infobox Wizard – Core State Management
 * CENTRALIZOVANÝ STATE (BEZ DOM / FETCH)
 */


// ⚠️ GLOBÁLNE PREMENNÉ (prístupné všetkým súborom)
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
    program_age: '',
    program_type: null  // 'child' | 'adult' | null
};


window.spaErrorState = {
    invalidCity: false,
    invalidProgram: false,
    errorType: null,  // 'state' | 'validation'
    formInvalid: false  // GF validation error flag
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
 * SETTER: Centralizovaný zápis window.currentState (SINGLE SOURCE OF TRUTH)
 */

// V spa-infobox-state.js (HNEĎ PO INIT BLOKOV)
window.setSpaState = function(newState, reason) {
    const oldState = window.currentState;
    
    if (newState !== oldState) {
        window.currentState = newState;
        console.log('[SPA State] Transition:', {
            from: oldState,
            to: newState,
            reason: reason || 'unknown',
            callStack: new Error().stack.split('\n').slice(2, 4).join('\n')
        });
    }
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
