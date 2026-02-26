# Názvy GF polí (podľa gf-uni-registration.json)

- **spa_city**  
  Mesto (predvyplnené z výberu alebo GET parametra)

- **spa_program**  
  Program (predvyplnené z výberu alebo GET parametra)

- **spa_frequency**  
  Frekvencia (kľúč, predvyplnené podľa výberu alebo session)

---

## Údaje o účastníkovi

- **spa_member_name**  
  - (Úplné pole s viacerými časťami)
    - Predpona
    - Meno
    - Stred
    - Priezvisko
    - Prípona

- **spa_member_birthdate**  
  Dátum narodenia účastníka

- **spa_member_nin**  
  Rodné číslo účastníka (nepovinné)

- **spa_client_address**  
  Adresa bydliska (adresné pole)
    - Ulica
    - 2. riadok adresy
    - Mesto
    - Štát / kraj
    - PSČ / Poštové smerovacie číslo
    - Krajina

- **spa_client_email**  
  E-mail účastníka (nepovinné, pre dospelého)

- **spa_client_email_required**  
  E-mail účastníka (POVINNÝ pre dospelého)

- **spa_client_phone**  
  Telefónne číslo účastníka

- **spa_member_health_restrictions**  
  Zdravotné obmedzenia / alergie

---

## Údaje o zákonnom zástupcovi (rodič)

- **spa_guardian_name**  
  - (Úplné pole s viacerými časťami)
    - Predpona
    - Meno
    - Stred
    - Priezvisko
    - Prípona

- **spa_parent_email**  
  E-mail zákonného zástupcu

- **spa_parent_phone**  
  Telefónne číslo zákonného zástupcu

---

## Súhlasy

- **spa_consent_gdpr**  
  Súhlasím so spracovaním osobných údajov v súlade s GDPR.

- **spa_consent_health**  
  Súhlasím so spracovaním zdravotných údajov pre účely zabezpečenia tréningu.

- **spa_consent_statutes**  
  Súhlasím so Stanovami občianskeho združenia.

- **spa_consent_terms**  
  Súhlasím s podmienkami pre zápis do tréningového programu.

- **spa_consent_guardian**  
  Potvrdzujem pravdivosť uvedených údajov (vyhlásenie rodiča/zákonného zástupcu)

- **spa_consent_marketing**  
  Súhlas so zasielaním informácií o podujatiach a novinkách (marketingový súhlas, nepovinné)

---

## Payment Architecture (Stripe Ready)

### Pole pre platby

**spa_first_payment_amount** (GF ID: 63)
- Typ: Product field (single)
- Úloha: Technické produktové pole
- Skryté cez CSS (`spa-hidden-product`)
- Cena sa nastavuje server-side
- Slúži ako medzikrok pre Gravity Forms
- **Nie je zdroj pravdy ceny**

**spa_first_payment_total** (GF ID: 64)
- Typ: Total field
- Úloha: GF Total field
- Skryté cez CSS (`spa-hidden-total`)
- Používa sa pre Stripe feed
- **Nie je autorita ceny**

### Architektúra platby

**Zdroj pravdy**: Databáza (DB) je jediný zdroj pravdy pre cenu.

Tok platby:

1. **Výpočet ceny**: Cena sa vypočíta server-side z databázy
2. **Nastavenie do Product poľa**: Cena sa nastaví do Product field (`spa_first_payment_amount`)
3. **Výpočet Form total**: Gravity Forms vypočíta Form total
4. **Stripe feed**: Stripe feed použije Form total
5. **Verifikácia**: `AmountVerificationService` porovná cenu proti databáze
6. **Bezpečnosť**: Ak dôjde k nesúladu, registrácia sa zablokuje

**Bezpečnostná zásada**: Nikdy sa neverí hodnote z formulára ani zo Stripe bez server-side verifikácie proti databáze.