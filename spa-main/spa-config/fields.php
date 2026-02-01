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
    'spa_city' => 'input_1',
    'spa_program' => 'input_2',
    /* 'spa_registration_type' => 'input_4', */
    'spa_frequency' => 'input_31',
    'spa_resolved_type' => 'input_34',

    /* ==========================
       ÚČASTNÍK (MEMBER/CHILD) – IDENTIFIKÁCIA
       ========================== */
    'spa_member_name_first' => 'input_6.3',  // Meno účastníka
    'spa_member_name_last' => 'input_6.6',   // Priezvisko účastníka
    'spa_member_birthdate' => 'input_7',     // Dátum narodenia
    'spa_member_birthnumber' => 'input_8',   // Rodné číslo

    /* ==========================
       ÚČASTNÍK – KONTAKT
       ========================== */
    'spa_member_health_restrictions' => 'input_9', // Zdravotné obmedzenia
    'spa_client_email' => 'input_15',        // Email CHILD (nepovinný)
    'spa_client_email_required' => 'input_16', // Email ADULT (povinný)
    'spa_client_phone' => 'input_19',        // Telefón účastníka (adult)
    'spa_client_address' => 'input_17',      // Adresa

    /* ==========================
       RODIČ / GUARDIAN – IDENTIFIKÁCIA
       ========================== */
    'spa_guardian_name_first' => 'input_18.3', // Meno rodiča/zástupcu
    'spa_guardian_name_last' => 'input_18.6',  // Priezvisko rodiča/zástupcu
    'spa_parent_email' => 'input_12',          // Email rodiča
    'spa_parent_phone' => 'input_13',          // Telefón rodiča

    /* ==========================
       SÚHLASY
       ========================== */
    'spa_consent_gdpr' => 'input_35.1',      // GDPR súhlas
    'spa_consent_health' => 'input_35.2',    // Zdravotné údaje
    'spa_consent_statutes' => 'input_35.3',  // Stanovy OZ
    'spa_consent_terms' => 'input_35.4',     // Podmienky zápisu
    'spa_consent_guardian' => 'input_42.1',  // Potvrdenie zástupcu
    'spa_consent_marketing' => 'input_37.1', // Marketing (nepovinný)
    /* ==========================
       PREHĽAD REGISTRÁCIE
       ========================== */
    'spa_registration_summary' => 'input_28', // Prehľad registrácie

];