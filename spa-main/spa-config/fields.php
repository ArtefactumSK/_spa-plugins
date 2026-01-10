<?php
/**
 * SPA – Gravity Forms Field Mapping (PHP)
 * Tento súbor mapuje logické názvy polí na konkrétne GF input_ID.
 * Upravuje sa PO IMPORTE GF formulára na novom webe.
 */

return [

    /* ==========================
       VÝBER PROGRAMU
       ========================== */
    'spa_city'    => 'input_1',   // ✅ OPRAVENÉ: reálne GF field ID
    'spa_program' => 'input_2',   // ✅ OPRAVENÉ: reálne GF field ID    
    'spa_registration_type' => 'input_4',
    'spa_frequency' => 'input_31',
    'spa_resolved_type' => 'input_34',
    'spa_participant_first_name' => 'input_6', // Meno dieťaťa
    'spa_participant_last_name'  => 'input_7', // Priezvisko dieťaťa

    /* ==========================
       ÚČASTNÍK – KONTAKT
       ========================== */
    'spa_client_email' => 'input_15',
    'spa_client_phone' => 'input_19',

    /* ==========================
       ÚČASTNÍK – ADRESA
       (GF Address – array)
       ========================== */
    'spa_client_address' => 'input_17',

    /* ==========================
       SÚHLASY
       ========================== */
    'spa_consent_gdpr'      => 'input_22',
    'spa_consent_health'    => 'input_24',
    'spa_consent_statutes'  => 'input_21',
    'spa_consent_terms'     => 'input_23',
    'spa_consent_guardian'  => 'input_25',
    'spa_consent_marketing' => 'input_26',

];