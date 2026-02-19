/**
 * SPA Register GF – Age Warning
 *
 * Načíta birthdate z GF poľa spa_member_birthdate,
 * vypočíta vek a ak je mimo rozsahu, zobrazí varovanie.
 *
 * Zdroj rozsahu: data-age-min / data-age-max na .spa-summary-age-warning
 * (server vložil tieto atribúty cez PreRenderHooks::buildPriceSummary)
 *
 * Tento súbor NEOBSAHUJE žiadnu business logiku.
 * SESSION nie je tu čítaná. Iba UI upozornenie.
 */
(function () {
    'use strict';

    /**
     * Vypočítaj vek z reťazca vo formáte dd.mm.rrrr
     * @param {string} dateStr
     * @returns {number|null}
     */
    function calcAge(dateStr) {
        if (!dateStr) return null;
        const parts = dateStr.trim().split('.');
        if (parts.length !== 3) return null;
        const day   = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const year  = parseInt(parts[2], 10);
        if (isNaN(day) || isNaN(month) || isNaN(year)) return null;
        const birth = new Date(year, month, day);
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        return age;
    }

    /**
     * Nájdi birthdate input podľa name atribútu cez spaRegisterFields
     * @returns {HTMLInputElement|null}
     */
    function findBirthdateInput() {
        const fields = window.spaRegisterFields || {};
        const inputId = fields['spa_member_birthdate'];
        if (!inputId) return null;
        return document.querySelector('[name="' + inputId + '"]');
    }

    /**
     * Hlavná funkcia – skontroluje vek a zobrazí/skryje varovanie
     */
    function checkAge() {
        const warningEl = document.querySelector('.spa-summary-age-warning');
        if (!warningEl) return;

        const ageMin = parseFloat(warningEl.dataset.ageMin);
        const ageMax = warningEl.dataset.ageMax !== undefined
            ? parseFloat(warningEl.dataset.ageMax)
            : null;

        const inputEl = findBirthdateInput();
        if (!inputEl) return;

        const age = calcAge(inputEl.value);

        if (age === null) {
            warningEl.style.display = 'none';
            return;
        }

        const tooYoung = !isNaN(ageMin) && age < ageMin;
        const tooOld   = ageMax !== null && !isNaN(ageMax) && age > ageMax;

        warningEl.style.display = (tooYoung || tooOld) ? '' : 'none';
    }

    // ── Event listeners ──────────────────────────────────────────────────────

    document.addEventListener('input',  function (e) {
        const fields = window.spaRegisterFields || {};
        const bdInputId = fields['spa_member_birthdate'];
        if (bdInputId && e.target.name === bdInputId) {
            checkAge();
        }
    });

    document.addEventListener('change', function (e) {
        const fields = window.spaRegisterFields || {};
        const bdInputId = fields['spa_member_birthdate'];
        if (bdInputId && e.target.name === bdInputId) {
            checkAge();
        }
    });

    // Initial check po načítaní (pre prípad predvyplneného poľa)
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(checkAge, 400);
    });

    // Expose pre prípadné externé volanie
    window.spaCheckAge = checkAge;

})();