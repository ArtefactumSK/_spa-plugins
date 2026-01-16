/**
 * SPA Infobox Wizard – sekcie
 */


/**
     * Skrytie všetkých sekcií pri inicializácii
     */
window.hideAllSectionsOnInit = function() {    
function hideAllSectionsOnInit() {
    console.log('[SPA Init] ========== HIDING ALL SECTIONS ==========');
    
    // ⭐ GUARD: Neexecutuj ak už boli sekcie skryté
    if (window.spa_sections_hidden) {
        console.log('[SPA Init] Sections already hidden, skipping');
        return;
    }
    
    // Skry pole "Kto bude účastníkom tréningov?"
    const registrationTypeField = document.querySelector('.gfield--input-type-radio');
    if (registrationTypeField) {
        registrationTypeField.style.display = 'none';
        console.log('[SPA Init] Hidden: Registration type field (input_4)');
    }
    
    // ⭐ Skry EMAIL polia
    const childEmailField = document.querySelector('input[name="input_15"]')?.closest('.gfield');
    const adultEmailField = document.querySelector('input[name="input_16"]')?.closest('.gfield');
    
    if (childEmailField) {
        childEmailField.style.display = 'none';
        console.log('[SPA Init] Hidden: Child email field (input_15)');
    }
    if (adultEmailField) {
        adultEmailField.style.display = 'none';
        console.log('[SPA Init] Hidden: Adult email field (input_16)');
    }
    
    // Skry všetky sekcie podľa CSS tried
    const sections = [
        'spa-section-common',
        'spa-section-adult',
        'spa-section-child'
    ];

    sections.forEach(cssClass => {
        // ⭐ Nájdi VŠETKY sekcie s danou triedou (nie len prvú!)
        const allSections = document.querySelectorAll(`.${cssClass}`);
        
        console.log(`[SPA Init] Found ${allSections.length} sections with class: ${cssClass}`);
        
        allSections.forEach(section => {
            window.toggleSection(section, false);
            console.log(`[SPA Init] ❌ Hidden: ${cssClass} (ID: ${section.id || 'no-id'})`);
        });
    });
    
    // ⭐ Označ že sekcie boli skryté
    window.spa_sections_hidden = true;
    console.log('[SPA Init] ========== SECTIONS HIDDEN COMPLETE ==========');
}
};

/**
 * RIADENIE VIDITEĽNOSTI SEKCIÍ
 */
function updateSectionVisibility() {
    console.log('[SPA Section Control] ========== UPDATE START ==========');
    console.log('[SPA Section Control] State:', {
        city: wizardData.city_name,
        program: wizardData.program_name,
        frequency: window.spaFormState.frequency
    });

    // ⭐ DVE RÔZNE PODMIENKY:
    // 1. Pre zobrazenie poľa input_4: mesto + program (neprázdne hodnoty!)
    const programSelected = !!(
        wizardData.city_name && 
        wizardData.city_name.trim() !== '' &&
        wizardData.program_name && 
        wizardData.program_name.trim() !== ''
    );
    
    // 2. Pre zobrazenie sekcií: mesto + program + frekvencia
    const allSelected = !!(
        wizardData.city_name && 
        wizardData.program_name && 
        window.spaFormState.frequency
    );

    console.log('[SPA Section Control] Conditions:', {
        programSelected,
        allSelected
    });

    // ⭐ ZÍSKAJ age_min priamo z programu (nie z data atribútu)
    let isChildProgram = false;

    // ⭐ Deklaruj birthNumberField na začiatku (potrebné neskôr)
    const birthNumberField = document.querySelector('input[name="input_8"]');

    // Pokús sa získať age_min z vybraného programu
    const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    if (programField && programField.value) {
        const selectedOption = programField.options[programField.selectedIndex];
        const ageMin = parseInt(selectedOption.getAttribute('data-age-min'));
        
        if (!isNaN(ageMin)) {
            isChildProgram = ageMin < 18;
        }
    }

    // Fallback: skontroluj data-is-child atribút
    if (!programField || !programField.value) {
        isChildProgram = birthNumberField?.getAttribute('data-is-child') === 'true';
    }

    console.log('[SPA Section Control] Program type:', {
        isChildProgram,
        source: programField?.value ? 'age_min' : 'data-is-child'
    });

    // ⭐ ZAPÍŠ RESOLVED TYPE DO HIDDEN FIELD (input_34)
    const resolvedTypeField = document.querySelector('input[name="input_34"]');
    if (resolvedTypeField) {
        const resolvedValue = isChildProgram ? 'child' : 'adult';
        resolvedTypeField.value = resolvedValue;
        console.log('[SPA Section Control] Resolved type written to input_34:', resolvedValue);
    } else {
        console.warn('[SPA Section Control] ⚠️ Hidden field input_34 NOT FOUND');
    }

    // ⭐ POLE "Kto bude účastníkom tréningov?" (input_4)
    // Zobrazuje sa hneď po výbere PROGRAMU (nie až po frekvencii)
    const registrationTypeField = document.querySelector('.gfield--input-type-radio');
    
    if (registrationTypeField) {
        if (programSelected) {  // ← ZMENA: stačí program
            // 1. NAJPRV ZOBRAZ pole
            registrationTypeField.style.display = '';
            console.log('[SPA Section Control] Registration type field: VISIBLE (program selected)');
            
            // 2. ⭐ POČKAJ NA RENDER a POTOM OZNAČ radio button
            setTimeout(() => {
                // Nájdi všetky radio buttony
                const allRadios = document.querySelectorAll('input[name="input_4"]');
                
                console.log('[SPA Section Control] Found radio buttons:', allRadios.length);
                
                let childRadio = null;
                let adultRadio = null;
                
                // Identifikuj ich podľa value/label
                allRadios.forEach(radio => {
                    const value = radio.value.toLowerCase();
                    const label = radio.parentElement?.textContent?.toLowerCase() || '';
                    
                    if (value.includes('dieťa') || value.includes('diet') || 
                        label.includes('dieťa') || label.includes('diet') || 
                        label.includes('mladší')) {
                        childRadio = radio;
                    }
                    
                    if (value.includes('dospel') || value.includes('18+') || 
                        label.includes('dospel') || label.includes('18+')) {
                        adultRadio = radio;
                    }
                });
                
                console.log('[SPA Section Control] Radio identification:', {
                    childRadio: !!childRadio,
                    adultRadio: !!adultRadio,
                    isChildProgram: isChildProgram
                });
                
                // OZNAČ správny radio button a DISABLE druhý
                if (isChildProgram && childRadio) {
                    childRadio.checked = true;
                    childRadio.disabled = false;
                    if (adultRadio) {
                        adultRadio.checked = false;
                        adultRadio.disabled = true; // ⭐ DISABLE adult pre CHILD program
                    }
                    console.log('[SPA Section Control] ✅ CHILD radio CHECKED, ADULT disabled');
                } else if (!isChildProgram && adultRadio) {
                    adultRadio.checked = true;
                    adultRadio.disabled = false;
                    if (childRadio) {
                        childRadio.checked = false;
                        childRadio.disabled = true; // ⭐ DISABLE child pre ADULT program
                    }
                    console.log('[SPA Section Control] ✅ ADULT radio CHECKED, CHILD disabled');
                }
            }, 100);
            
        } else {
            // Skry pole ak nie je program vybraný
            registrationTypeField.style.display = 'none';
            
            // ⭐ ENABLE všetky radio buttony pri resete
            const allRadios = document.querySelectorAll('input[name="input_4"]');
            allRadios.forEach(radio => {
                radio.checked = false;
                radio.disabled = false; // ⭐ ENABLE pri skrytí
            });
            
            console.log('[SPA Section Control] Registration type field: HIDDEN (no program)');
        }
    }
    
    
    // ⭐ Použijeme querySelectorAll aby sme našli VŠETKY sekcie
    const commonSections = document.querySelectorAll('.spa-section-common');
    const adultSections = document.querySelectorAll('.spa-section-adult');
    const childSections = document.querySelectorAll('.spa-section-child');

    console.log('[SPA Section Control] Sections found:', {
        common: commonSections.length,
        adult: adultSections.length,
        child: childSections.length
    });


    // LOGIKA ZOBRAZOVANIA: stačí programSelected (mesto + program)
    if (programSelected) {
        // 1. SPOLOČNÁ SEKCIA: vždy zobrazená (VŠETKY commonSections)
        commonSections.forEach(section => {
            window.toggleSection(section, true);
        });
        console.log('[SPA Section Control] ✅ Common sections: VISIBLE');
        
        // 2. CHILD alebo ADULT sekcie podľa age_from
        if (isChildProgram) {
            childSections.forEach(section => toggleSection(section, true));
            adultSections.forEach(section => toggleSection(section, false));
            console.log('[SPA Section Control] ✅ Child sections: VISIBLE');
            console.log('[SPA Section Control] ❌ Adult sections: HIDDEN');
        } else {
            adultSections.forEach(section => toggleSection(section, true));
            childSections.forEach(section => toggleSection(section, false));
            console.log('[SPA Section Control] ✅ Adult sections: VISIBLE');
            console.log('[SPA Section Control] ❌ Child sections: HIDDEN');
        }
    } else {
        // Skry VŠETKY sekcie
        commonSections.forEach(section => toggleSection(section, false));
        adultSections.forEach(section => toggleSection(section, false));
        childSections.forEach(section => toggleSection(section, false));
        console.log('[SPA Section Control] ❌ All sections: HIDDEN (no program selected)');
    }
    

    console.log('[SPA Section Control] ========== UPDATE END ==========');
}

 
/**
     * Zobraz/skry sekciu + všetky nasledujúce polia až po ďalšiu sekciu
     */
function toggleSection(sectionElement, show) {
    if (!sectionElement) return;

    sectionElement.style.display = show ? 'block' : 'none';

    let nextElement = sectionElement.nextElementSibling;

    while (nextElement) {
        if (nextElement.classList.contains('gfield--type-section')) {
            break;
        }

        // ⭐ ŠPECIÁLNE: Telefón účastníka (input_19) - VŽDY zobraz ak je AKÝKOĽVEK program vybraný
        const phoneField = nextElement.querySelector('input[name="input_19"]');
        if (phoneField) {
            const phoneWrapper = phoneField.closest('.gfield');
            if (phoneWrapper) {
                // Skontroluj GLOBÁLNY stav programu (nie len lokálny show parameter)
                const isProgramSelected = !!(wizardData.program_name && wizardData.program_name.trim() !== '');
                
                if (isProgramSelected) {
                    // Program vybraný - VŽDY ZOBRAZ
                    phoneWrapper.style.display = '';
                    phoneWrapper.style.visibility = 'visible';
                    phoneWrapper.style.opacity = '1';
                    phoneField.disabled = false;
                    console.log('[SPA toggleSection] Phone field: FORCED VISIBLE (program selected globally)');
                } else {
                    // Žiadny program - SKRY
                    phoneWrapper.style.display = 'none';
                    phoneField.disabled = true;
                    console.log('[SPA toggleSection] Phone field: HIDDEN (no program)');
                }
            }
            nextElement = nextElement.nextElementSibling;
            continue;
        }
        
        // ⭐ ŠPECIÁLNE: Email CHILD (input_15) - zobraz LEN ak show=true A je CHILD program
        const childEmailField = nextElement.querySelector('input[name="input_15"]');
        if (childEmailField) {
            const childEmailWrapper = childEmailField.closest('.gfield');
            const birthNumberField = document.querySelector('input[name="input_8"]');
            const isChildProgram = birthNumberField?.getAttribute('data-is-child') === 'true';
            
            if (childEmailWrapper) {
                if (show && isChildProgram) {
                    childEmailWrapper.style.display = '';
                    childEmailWrapper.style.visibility = 'visible';
                    childEmailWrapper.style.opacity = '1';
                    childEmailField.disabled = false;
                    console.log('[SPA toggleSection] Email CHILD: VISIBLE');
                } else {
                    childEmailWrapper.style.display = 'none';
                    childEmailField.disabled = true;
                    childEmailField.value = '';
                    console.log('[SPA toggleSection] Email CHILD: HIDDEN');
                }
            }
            nextElement = nextElement.nextElementSibling;
            continue;
        }
        
        // ⭐ ŠPECIÁLNE: Email ADULT (input_16) - zobraz LEN ak show=true A je ADULT program
        const adultEmailField = nextElement.querySelector('input[name="input_16"]');
        if (adultEmailField) {
            const adultEmailWrapper = adultEmailField.closest('.gfield');
            const birthNumberField = document.querySelector('input[name="input_8"]');
            const isChildProgram = birthNumberField?.getAttribute('data-is-child') === 'true';
            
            if (adultEmailWrapper) {
                if (show && !isChildProgram) {
                    adultEmailWrapper.style.display = '';
                    adultEmailWrapper.style.visibility = 'visible';
                    adultEmailWrapper.style.opacity = '1';
                    adultEmailField.disabled = false;
                    console.log('[SPA toggleSection] Email ADULT: VISIBLE');
                } else {
                    adultEmailWrapper.style.display = 'none';
                    adultEmailField.disabled = true;
                    adultEmailField.value = '';
                    console.log('[SPA toggleSection] Email ADULT: HIDDEN');
                }
            }
            nextElement = nextElement.nextElementSibling;
            continue;
        }

        // ⭐ ŠPECIÁLNE: Rodné číslo (input_8) sa zobrazuje LEN pre CHILD
        const birthNumberField = nextElement.querySelector('input[name="input_8"]');
        if (birthNumberField) {
            const birthNumberWrapper = birthNumberField.closest('.gfield');
            const isChildProgram = birthNumberField.getAttribute('data-is-child') === 'true';
            
            if (birthNumberWrapper) {
                if (show && isChildProgram) {
                    // Zobraz LEN pre CHILD
                    birthNumberWrapper.style.display = 'block';
                    birthNumberField.disabled = false;
                    console.log('[SPA toggleSection] Birth number: VISIBLE (CHILD program)');
                } else {
                    // Skry pre ADULT alebo ak sekcia sa skrýva
                    birthNumberWrapper.style.display = 'none';
                    birthNumberField.disabled = true;
                    birthNumberField.value = ''; // Vyčisti hodnotu
                    console.log('[SPA toggleSection] Birth number: HIDDEN (ADULT program)');
                }
            }
            
            nextElement = nextElement.nextElementSibling;
            continue;
        }

        // Zobraz/skry ostatné elementy
        nextElement.style.display = show ? 'block' : 'none';
        
        // ⭐ ENABLE/DISABLE všetky polia v elemente (OKREM input_8)
        if (show) {
            // Pri zobrazení ENABLE všetky polia
            const inputs = nextElement.querySelectorAll('input:not([name="input_8"]), select, textarea');
            inputs.forEach(input => {
                input.disabled = false;
                input.style.opacity = '1';
                input.style.pointerEvents = 'auto';
            });
        } else {
            // Pri skrytí DISABLE všetky polia
            const inputs = nextElement.querySelectorAll('input:not([name="input_8"]), select, textarea');
            inputs.forEach(input => {
                input.disabled = true;
            });
        }
        
        nextElement = nextElement.nextElementSibling;
    }
    
    console.log('[SPA toggleSection]', show ? 'ENABLED' : 'DISABLED', 'fields in section');
}