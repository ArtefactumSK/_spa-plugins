(function() {
    'use strict';

    if (typeof spaConfig === 'undefined') return;
    
    // Guard: beží len v GF kontexte
    if (!document.querySelector('.gform_wrapper')) return;

    document.addEventListener('DOMContentLoaded', init);
    
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('gform_post_render', init);
    }

    function init() {
        watchProgramChange();
        watchNameFields();
    }

    function watchProgramChange() {
        const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
        if (!programField) return;

        programField.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            
            if (!option || !option.value) {
                resetResolvedType();
                return;
            }

            const target = option.getAttribute('data-target');
            if (!target) return;

            const resolvedType = (target === 'adult') ? 'adult' : 'child';
            
            setResolvedType(resolvedType);
            autoFillEmailIfChild(resolvedType);
        });
    }

    function watchNameFields() {
        const menoField = document.querySelector('[name*="meno"]');
        const priezviskoField = document.querySelector('[name*="priezvisko"]');
        
        if (menoField) menoField.addEventListener('blur', checkAutoFillEmail);
        if (priezviskoField) priezviskoField.addEventListener('blur', checkAutoFillEmail);
    }

    function checkAutoFillEmail() {
        const hiddenField = document.querySelector(`[name="${spaConfig.fields.spa_resolved_type}"]`);
        if (!hiddenField || hiddenField.value !== 'child') return;
        
        autoFillEmailIfChild('child');
    }

    function setResolvedType(type) {
        const hiddenField = document.querySelector(`[name="${spaConfig.fields.spa_resolved_type}"]`);
        
        if (hiddenField) {
            hiddenField.value = type;
        }

        if (typeof jQuery !== 'undefined') {
            jQuery(document).trigger('gform_post_render');
        }
    }

    function resetResolvedType() {
        const hiddenField = document.querySelector(`[name="${spaConfig.fields.spa_resolved_type}"]`);
        
        if (hiddenField) {
            hiddenField.value = '';
        }

        if (typeof jQuery !== 'undefined') {
            jQuery(document).trigger('gform_post_render');
        }
    }

    function autoFillEmailIfChild(resolvedType) {
        if (resolvedType !== 'child') return;

        const emailField = document.querySelector(`[name="${spaConfig.fields.spa_client_email}"]`);
        if (!emailField || emailField.value !== '') return;

        const menoField = document.querySelector('[name*="meno"]');
        const priezviskoField = document.querySelector('[name*="priezvisko"]');
        
        if (!menoField || !priezviskoField) return;

        const meno = menoField.value.toLowerCase().trim();
        const priezvisko = priezviskoField.value.toLowerCase().trim();

        if (meno && priezvisko) {
            emailField.value = `${meno}.${priezvisko}@piaseckyacademy.sk`;
        }
    }

})();