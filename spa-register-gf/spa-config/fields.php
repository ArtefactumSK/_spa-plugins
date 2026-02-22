<?php
/**
 * SPA – Gravity Forms Field Mapping (PHP)
 * Tento súbor mapuje logické názvy polí na konkrétne GF input_ID.
 * Zdroj pravdy: spa_system/forms/gf-uni-registration.json
 * PO KAŽDOM importe GF formulára je potrebné mapu obnoviť podľa gf-uni-registration.json.
 */

return [

    /* ==========================
       PLATOBNÉ ÚDAJE
       ========================== */

    'spa_payment_method'        => 'input_47',
    'spa_fakturovat_na_firmu'   => 'input_48.1',
    'spa_vyska_prvej_platby'    => 'input_55',

    /* ==========================
       FAKTURAČNÉ ÚDAJE
       ========================== */

    'spa_company_name'              => 'input_50',
    'spa_company_ico'               => 'input_52',
    'spa_company_dic'               => 'input_57',
    'spa_company_icdph'             => 'input_58',
    'spa_fakturacna_adresa'         => 'input_54.1',
    'spa_company_address'           => 'input_53',
    'spa_company_address_street'    => 'input_53.1',
    'spa_company_address_city'      => 'input_53.3',
    'spa_company_address_postcode'  => 'input_53.5',

    /* ==========================
       ÚČASTNÍK (MEMBER) – IDENTIFIKÁCIA
       ========================== */

    'spa_member_name_first'     => 'input_6.3',
    'spa_member_name_last'      => 'input_6.6',
    'spa_member_birthdate'      => 'input_7',
    'spa_member_birthnumber'    => 'input_8',

    /* ==========================
       ÚČASTNÍK – ADRESA A KONTAKT
       ========================== */

    // Celé adresné pole (GF address field)
    'spa_client_address'            => 'input_17',
    'spa_client_address_street'     => 'input_17.1',
    'spa_client_address_city'       => 'input_17.3',
    'spa_client_address_postcode'  => 'input_17.5',
    'spa_client_email'              => 'input_15',
    'spa_client_email_required'     => 'input_16',
    'spa_client_phone'              => 'input_19',
    'spa_member_health_restrictions'=> 'input_9',

    /* ==========================
       RODIČ / GUARDIAN – IDENTIFIKÁCIA A KONTAKT
       ========================== */

    'spa_guardian_name_first'   => 'input_18.3',
    'spa_guardian_name_last'    => 'input_18.6',
    'spa_parent_email'          => 'input_12',
    'spa_parent_phone'          => 'input_13',

    /* ==========================
       SÚHLASY
       ========================== */

    'spa_consent_gdpr'          => 'input_35.1',
    'spa_consent_health'        => 'input_35.2',
    'spa_consent_statutes'      => 'input_35.3',
    'spa_consent_terms'         => 'input_35.4',
    'spa_consent_guardian'      => 'input_42.1',
    'spa_consent_marketing'     => 'input_37.1',
];
