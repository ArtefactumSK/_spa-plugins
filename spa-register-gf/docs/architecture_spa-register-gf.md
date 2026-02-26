spa-register-gf/
│
├── spa-register-gf.php                     ← vstupný bod pluginu
│
├── spa-config/
│   ├── fields.json                         ← [EXISTUJE] logical keys → GF field id
│   └── fields.php                          ← [EXISTUJE] PHP mirror
│
├── src/
│   ├── Bootstrap/
│   │   └── Plugin.php                      ← init, autoload, hook wiring
│   │
│   ├── Services/
│   │   ├── SessionService.php              ← read-only SESSION prístup
│   │   ├── FieldMapService.php             ← načítanie fields.json, resolve()
│   │   ├── AmountVerificationService.php   ← prepočet ceny z DB, blokujúci mismatch
│   │   ├── UserCreationService.php         ← rozvetvenie child / adult
│   │   ├── UserCreationChildHelper.php     ← logika vytvárania child + parent
│   │   ├── UserCreationAdultHelper.php     ← logika vytvárania adult klienta
│   │   └── RegistrationService.php        ← CPT spa_registration + postmeta
│   │
│   ├── Domain/
│   │   └── RegistrationPayload.php         ← DTO (logical keys, nie GF identifikátory)
│   │
│   ├── Validation/
│   │   ├── ValidationResult.php            ← value object: isValid, errors[]
│   │   ├── AbstractScopeValidator.php      ← zdieľaná logika (sanitize, formát check)
│   │   ├── ChildScopeValidator.php         ← povinné polia pre scope child
│   │   └── AdultScopeValidator.php         ← povinné polia pre scope adult
│   │
│   ├── Infrastructure/
│   │   ├── GFFormFinder.php                ← nájde formulár cez CSS class
│   │   ├── GFEntryReader.php               ← číta entry výhradne cez FieldMapService
│   │   └── Logger.php                      ← wrapper: spa_log() alebo error_log()
│   │
│   └── Hooks/
│       ├── PreRenderHooks.php              ← gform_pre_render
│       ├── ValidationHooks.php             ← gform_pre_validation + gform_validation
│       └── SubmissionHooks.php             ← gform_after_submission
│
├── uninstall.php                           ← [EXISTUJE]
└── README.md                               ← [EXISTUJE]



### Event lifecycle – finálny prehľad
PAGE LOAD
  └── initSession() [action: init, priority 1]
        session_start() ak PHP session nebeží

gform_pre_render [filter, priority 10]
  └── GFFormFinder::guard($form)          → false → return $form bez zmeny
  └── SessionService::tryCreate()
  └── Predvyplnenie (logical keys → FieldMapService → GF field value filter):
        SESSION: spa_program ← session.program_id
        SESSION: spa_resolved_type ← session.scope
        SESSION: spa_frequency ← session.frequency_key
        GET fallback (len ak session neexistuje / program_id = 0):
          spa_city ← $_GET['city']
          spa_program ← $_GET['program']
  └── return $form

gform_pre_validation [filter, priority 10]
  └── GFFormFinder::guard($form)          → false → return $form bez zmeny
  └── SessionService::tryCreate()
        → null: add_filter(gform_validation, forceSessionError)  → Logger::warning
        → expired: add_filter(gform_validation, forceExpiredError) → Logger::warning
  └── return $form

gform_validation [filter, priority 10]
  └── GFFormFinder::guard($form)          → false → return $validationResult bez zmeny
  └── Ak GF sám zistil chyby → return (neprekrývame)
  └── SessionService::tryCreate()
        → null alebo expired → blockWithMessage("Vráťte sa na selector")
  └── $scope = SessionService::getScope()  ← VÝHRADNE zo SESSION
  └── GFEntryReader::buildPayload() z $_POST
  └── ChildScopeValidator alebo AdultScopeValidator podľa $scope
        → chyby → addFieldError (cez FieldMapService) → is_valid = false
  └── GF Product field existuje (spa_first_payment_amount, GF ID: 63)
  └── AmountVerificationService::verify($session)
        → cena sa porovnáva proti DB cez AmountVerificationService
        → mismatch → blockWithMessage("Cena sa zmenila") → is_valid = false
  └── Stripe feed používa "Form total" (GF Total field), nie konkrétne product pole.
  └── return $validationResult

gform_after_submission [action, priority 10]
  └── GFFormFinder::guard($form)          → false → return
  └── SessionService::tryCreate() + isExpired() guard
        → problém → Logger::error + redirect
  └── $scope = SessionService::getScope() ← VÝHRADNE zo SESSION
  └── AmountVerificationService::verify()
        → mismatch → do_action('spa_registration_amount_mismatch') → return
  └── GFEntryReader::buildPayload($entry)
  └── Ak je payment_method = online_payment:
        → registrácia zostáva v stave "pending"
        → aktivácia po Stripe webhook potvrdení (len poznámka, neimplementuj)
  └── UserCreationService::createForScope($payload, $scope)
        → zlyhanie → Logger::error + do_action('spa_registration_failed') → return
  └── RegistrationService::create($payload, $userIds, $session)
        → zlyhanie → Logger::error + do_action('spa_registration_failed') → return
  └── do_action('spa_registration_completed', $registrationId, $userIds, $session)
  └── Logger::info('submission_complete', [...])