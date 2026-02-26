## GF UNI REGISTRATION – FIELD MAP

## Základné informácie
- **Form ID**: 3
- **Title**: UNI Registrácia do SPA
- **CSS class**: spa-register-gf
- **Page count**: 1

---

## Page 1

### Field: info_price_summary
- **GF Field ID**: 28
- **Type**: html
- **Required**: false
- **CSS Class**: info_price_summary spa-section-common
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: payment_method
- **GF Field ID**: 47
- **Type**: radio
- **Required**: false
- **CSS Class**: payment_method
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: HTML Hotovosť
- **GF Field ID**: 59
- **Type**: html
- **Required**: false
- **CSS Class**: gfield_description
- **Conditional Logic**: {"enabled":true,"actionType":"show","logicType":"all","rules":[{"fieldId":"47","operator":"is","value":"cash_payment"}]}
- **Inputs**: none

---

### Field: HTML Faktúra
- **GF Field ID**: 61
- **Type**: html
- **Required**: false
- **CSS Class**: gfield_description
- **Conditional Logic**: {"enabled":true,"actionType":"show","logicType":"all","rules":[{"fieldId":"47","operator":"is","value":"invoice_payment"}]}
- **Inputs**: none

---

### Field: Faktúrovať na firmu?
- **GF Field ID**: 48
- **Type**: checkbox
- **Required**: false
- **CSS Class**: (prázdne)
- **Conditional Logic**: {"enabled":true,"actionType":"show","logicType":"all","rules":[{"fieldId":"47","operator":"is","value":"invoice_payment"}]}
- **Inputs**:
  - **48.1** – label: Faktúrovať na firmu?; name: (prázdne)

---

### Field: HTML Online Platba
- **GF Field ID**: 62
- **Type**: html
- **Required**: false
- **CSS Class**: gfield_description
- **Conditional Logic**: {"enabled":true,"actionType":"show","logicType":"all","rules":[{"fieldId":"47","operator":"is","value":"online_payment"}]}
- **Inputs**: none

---

### Field: HTML Platba 48h pred tréneingom
- **GF Field ID**: 60
- **Type**: html
- **Required**: false
- **CSS Class**: (prázdne)
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

## Payment Fields

### Field: spa_first_payment_amount
- **GF Field ID**: 63
- **Type**: product (single)
- **Required**: false
- **CSS Class**: spa-hidden-product
- **Admin Label**: spa_first_payment_amount
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**:
  - **63.1** – label: Meno; name: (prázdne)
  - **63.2** – label: Cena; name: (prázdne)
  - **63.3** – label: Množstvo; name: (prázdne)
- **Poznámky**:
  - Používa sa ako technické Product pole
  - Skryté cez CSS (spa-hidden-product)
  - Cena nastavovaná server-side
  - Stripe používa Form total (nie toto pole)
  - Produktové pole nie je zdroj pravdy pre Stripe
  - Cena sa verifikuje cez AmountVerificationService

---

### Field: spa_first_payment_total
- **GF Field ID**: 64
- **Type**: total
- **Required**: false
- **CSS Class**: spa-hidden-total
- **Admin Label**: spa_first_payment_total
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none
- **Poznámky**:
  - Používa sa výhradne pre Stripe
  - Skryté cez CSS (spa-hidden-total)
  - Stripe feed používa GF total z tohto poľa

---

### Field: Fakturačné údaje (sekcia)
- **GF Field ID**: 49
- **Type**: section
- **Required**: false
- **CSS Class**: (prázdne)
- **Conditional Logic**: {"enabled":true,"actionType":"show","logicType":"all","rules":[{"fieldId":"48","operator":"is","value":"invoice_tocompany"}]}
- **Inputs**: none

---

### Field: company_name
- **GF Field ID**: 50
- **Type**: text
- **Required**: true
- **CSS Class**: company_name
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: company_ico
- **GF Field ID**: 52
- **Type**: text
- **Required**: true
- **CSS Class**: company_dic
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: company_dic
- **GF Field ID**: 57
- **Type**: text
- **Required**: true
- **CSS Class**: company_dic
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: company_icdph
- **GF Field ID**: 58
- **Type**: text
- **Required**: false
- **CSS Class**: company_dic
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: Fakturačná adresa (prepínač)
- **GF Field ID**: 54
- **Type**: checkbox
- **Required**: false
- **CSS Class**: (prázdne)
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**:
  - **54.1** – label: Iná fakturačná adresa ako bydliska; name: (prázdne)

---

### Field: company_address
- **GF Field ID**: 53
- **Type**: address
- **Required**: false
- **CSS Class**: company_address
- **Conditional Logic**: {"enabled":true,"actionType":"show","logicType":"all","rules":[{"fieldId":"54","operator":"is","value":"invoice_address"}]}
- **Inputs**:
  - **53.1** – label: Ulica; autocompleteAttribute: address-line1
  - **53.2** – label: 2. riadok adresy; autocompleteAttribute: address-line2; isHidden: true
  - **53.3** – label: Mesto; autocompleteAttribute: address-level2
  - **53.4** – label: Štát / kraj; autocompleteAttribute: address-level1; isHidden: true
  - **53.5** – label: PSČ / Poštové smerovacie číslo; autocompleteAttribute: postal-code
  - **53.6** – label: Krajina; autocompleteAttribute: country-name

---

### Field: ÚDAJE O ÚČASTNÍKOVI TRÉNINGOV (sekcia)
- **GF Field ID**: 32
- **Type**: section
- **Required**: false
- **CSS Class**: spa-section-common
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: spa_member_name
- **GF Field ID**: 6
- **Type**: name
- **Required**: true
- **CSS Class**: spa-field-name
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**:
  - **6.2** – label: Predpona; autocompleteAttribute: honorific-prefix; inputType: radio; choices exist
  - **6.3** – label: Meno; autocompleteAttribute: given-name
  - **6.4** – label: Stred; autocompleteAttribute: additional-name; isHidden: true
  - **6.6** – label: Priezvisko; autocompleteAttribute: family-name
  - **6.8** – label: Prípona; autocompleteAttribute: honorific-suffix; isHidden: true

---

### Field: spa_member_birthdate
- **GF Field ID**: 7
- **Type**: date
- **Required**: true
- **CSS Class**: spa-field-birthdate
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: spa_member_birthnumber
- **GF Field ID**: 8
- **Type**: text
- **Required**: false
- **CSS Class**: spa-field-birth-number
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: spa_client_address
- **GF Field ID**: 17
- **Type**: address
- **Required**: true
- **CSS Class**: spa-field spa-client-address
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**:
  - **17.1** – label: Ulica; autocompleteAttribute: address-line1
  - **17.2** – label: 2. riadok adresy; autocompleteAttribute: address-line2; isHidden: true
  - **17.3** – label: Mesto; autocompleteAttribute: address-level2
  - **17.4** – label: Štát / kraj; autocompleteAttribute: address-level1; isHidden: true
  - **17.5** – label: PSČ / Poštové smerovacie číslo; autocompleteAttribute: postal-code
  - **17.6** – label: Krajina; autocompleteAttribute: country-name; isHidden: true

---

### Field: spa_client_email
- **GF Field ID**: 15
- **Type**: email
- **Required**: false
- **CSS Class**: spa-field-client-email spa-email-narrow
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: spa_client_email_required
- **GF Field ID**: 16
- **Type**: email
- **Required**: false
- **CSS Class**: spa_client_email_required spa-section-adult spa-email-narrow
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: spa_client_phone
- **GF Field ID**: 19
- **Type**: phone
- **Required**: false
- **CSS Class**: spa-field spa-client-phone spa-section-common
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: spa_member_health_restrictions
- **GF Field ID**: 9
- **Type**: textarea
- **Required**: false
- **CSS Class**: spa-field-health
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: ÚDAJE O RODIČOVI / ZÁKONNOM ZÁSTUPCOVI (sekcia)
- **GF Field ID**: 33
- **Type**: section
- **Required**: false
- **CSS Class**: spa-section-child
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: spa_guardian_name
- **GF Field ID**: 18
- **Type**: name
- **Required**: true
- **CSS Class**: spa-field spa-guardian-name
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**:
  - **18.2** – label: Predpona; autocompleteAttribute: honorific-prefix; inputType: radio; choices exist
  - **18.3** – label: Meno; autocompleteAttribute: given-name
  - **18.4** – label: Stred; autocompleteAttribute: additional-name; isHidden: true
  - **18.6** – label: Priezvisko; autocompleteAttribute: family-name
  - **18.8** – label: Prípona; autocompleteAttribute: honorific-suffix; isHidden: true

---

### Field: spa_parent_email
- **GF Field ID**: 12
- **Type**: email
- **Required**: true
- **CSS Class**: spa-parent-email
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: spa_parent_phone
- **GF Field ID**: 13
- **Type**: phone
- **Required**: true
- **CSS Class**: spa-parent-phone
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: SÚHLASY K REGISTRÁCII (sekcia)
- **GF Field ID**: 38
- **Type**: section
- **Required**: false
- **CSS Class**: spa-section-common
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**: none

---

### Field: Vyjadrenie súhlasu
- **GF Field ID**: 35
- **Type**: checkbox
- **Required**: true
- **CSS Class**: (prázdne)
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**:
  - **35.1** – label: Súhlasím so spracovaním osobných údajov v súlade s GDPR.; name: (prázdne)
  - **35.2** – label: Súhlasím so spracovaním zdravotných údajov pre účely zabezpečenia tréningu.; name: (prázdne)
  - **35.3** – label: Súhlasím so Stanovami občianskeho združenia.; name: (prázdne)
  - **35.4** – label: Súhlasím s podmienkami pre zápis do tréningového programu.; name: (prázdne)

---

### Field: spa_consent_guardian
- **GF Field ID**: 42
- **Type**: checkbox
- **Required**: false
- **CSS Class**: spa-single-approval spa-section-child
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**:
  - **42.1** – label: Potvrdzujem pravdivosť uvedených údajov.; name: (prázdne)

---

### Field: spa_consent_marketing
- **GF Field ID**: 37
- **Type**: consent
- **Required**: false
- **CSS Class**: (prázdne)
- **Conditional Logic**: (žiadne / prázdne)
- **Inputs**:
  - **37.1** – label: Súhlas; name: (prázdne)
  - **37.2** – label: Text; name: (prázdne); isHidden: true
  - **37.3** – label: Popis; name: (prázdne); isHidden: true

---

## SUMMARY TABLE

| GF ID | Label | Type | Required | Page | Conditional |
|-------|-------|------|----------|------|-------------|
| 28 | info_price_summary | html | false | 1 | nie |
| 47 | Vyberte spôsob platby | radio | false | 1 | nie |
| 59 | HTML Hotovosť | html | false | 1 | áno |
| 61 | HTML Faktúra | html | false | 1 | áno |
| 48 | Faktúrovať na firmu? | checkbox | false | 1 | áno |
| 62 | HTML Online Platba | html | false | 1 | áno |
| 60 | HTML Platba 48h pred tréneingom | html | false | 1 | nie |
| 63 | Výška prvej platby | product (single) | false | 1 | nie |
| 64 | Celkom | total | false | 1 | nie |
| 49 | Fakturačné údaje | section | false | 1 | áno |
| 50 | Názov firmy (daňový subjekt) | text | true | 1 | nie |
| 52 | IČO | text | true | 1 | nie |
| 57 | DIČ | text | true | 1 | nie |
| 58 | IČDPH | text | false | 1 | nie |
| 54 | Fakturačná adresa | checkbox | false | 1 | nie |
| 53 | Fakturačná adresa | address | false | 1 | áno |
| 32 | ÚDAJE O ÚČASTNÍKOVI TRÉNINGOV | section | false | 1 | nie |
| 6 | Meno a priezvisko účastníka | name | true | 1 | nie |
| 7 | Dátum narodenia účastníka | date | true | 1 | nie |
| 8 | Rodné číslo účastníka | text | false | 1 | nie |
| 17 | Adresa bydliska | address | true | 1 | nie |
| 15 | E-mail účastníka | email | false | 1 | nie |
| 16 | E-mail účastníka | email | false | 1 | nie |
| 19 | Telefónne číslo účastníka | phone | false | 1 | nie |
| 9 | Zdravotné obmedzenia alebo alergie | textarea | false | 1 | nie |
| 33 | ÚDAJE O RODIČOVI / ZÁKONNOM ZÁSTUPCOVI | section | false | 1 | nie |
| 18 | Meno a priezvisko zákonného zástupcu | name | true | 1 | nie |
| 12 | E-mail zákonného zástupcu | email | true | 1 | nie |
| 13 | Kontaktný telefón | phone | true | 1 | nie |
| 38 | SÚHLASY K REGISTRÁCII | section | false | 1 | nie |
| 35 | Vyjadrenie súhlasu | checkbox | true | 1 | nie |
| 42 | Vyhlásene rodiča / zákonného zástupcu | checkbox | false | 1 | nie |
| 37 | &nbsp; | consent | false | 1 | nie |
