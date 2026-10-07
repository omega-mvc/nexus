# SCALETTA ESATTA - Conversione libreria Flourish a PHP 8.4

> Documento operativo: definisce fasi, ordine di esecuzione, criteri di accettazione e deliverable per portare la libreria PHP 7.1-legacy a PHP 8.4 (con focus su compatibilità BC, qualità e tipizzazione completa).

## 1. OBIETTIVI

- Rendere la libreria pienamente compatibile con **PHP 8.4** (non limitarsi alla tipizzazione).
- Eliminare warning/deprecazioni PHP 8.2/8.3/8.4.
- Stabilire una baseline di qualità obbligatoria tramite **PHPStan livello 10** con **type-coverage al 100%**.
- Applicare standard di codifica verificabile con **PHP_CodeSniffer (phpcs)**.
- Introdurre/validare test con **PHPUnit**.
- Tracciare ogni modifica in modo reversibile e documentato (MODIFICHE.md).

## 2. PREMESSE E STATO ATTUALE

- Versione target: **PHP 8.4+** (compatibile anche con 8.5 in ambiente corrente).
- Analisi già eseguita (sintattica): **66 file .php** (67 originali − `fLoader.php` eliminato) passano `php -l` su PHP 8.5. Unico warning corretto: `continue` in `switch` (Database.php:1396) → sistemato in `continue 2`.
- **Rimozione prefisso `f` completata**: tutte le classi/file rinominati (`fDatabase` → `Database`, `fException` → `FlourishException` per collisione con la classe built-in `Exception`, ecc.), 3861 riferimenti riscritti, classmap Composer rigenerata.
- Nessun uso di funzioni rimosse (core): `create_function`, `each()`, `/e` in `preg_replace`, `split()`/`ereg()` globali, accesso brace `$var{...}`, `assert()` string-form, `get_magic_quotes_*` non protetti.
- Costruttori: solo `__construct()`, nessun old-style constructor per nome classe.
- Eccezioni/ereditarietà OK. Uso limitato di `eval()` (ORM.php:390) per creazione classi dinamiche - accettabile e verificabile.
- Possibili sorgenti di deprecazione PHP 8.2+: **proprietà dinamiche** (classi senza `__set/__get`/`#[AllowDynamicProperties]` che assegnano proprietà non dichiarate). Da verificare con PHPStan e runtime.
- Comportamento PHP 8.1: `htmlspecialchars()` senza charset in `Core.php:801` passa da default ISO-8859-1 a UTF-8. Da verificare impatto su output.

## 3. FASE 0 - AMBIENTE E PREPARAZIONE

**Obiettivo**: allineare `composer.json` a PHP 8.4, creare struttura per tool di QA.

### 3.1 Aggiornamento `composer.json`

- [x] Impostare `require.php` a `"php": "^8.4"` (o `">=8.4"`) mantenendo coerenza con target.
- [x] Verificare `autoload.classmap` (mantiene copertura attuale su `.`).
- [x] Aggiungere `require-dev` con dipendenze QA/test.
- [x] Aggiungere script Composer utili: `analyse`, `cs`, `cs-fix`, `test`, `coverage`, `check` (opzionali ma utili).

### 3.2 Installazione dipendenze dev

Dipendenze richieste:
- [x] `squizlabs/php_codesniffer` (PHPCS) – analisi/style
- [x] `phpstan/phpstan` – analisi statica (level 10)
- [x] `phpstan/phpstan-type-coverage` – verifica type coverage 100%
- [x] `phpunit/phpunit` – test suite

**Azione**: installare via Composer in `require-dev`.

### 3.3 Struttura file di configurazione

- [x] `phpcs.xml` (o `phpcs.xml.dist`) – regole PHPCS
- [x] `phpstan.neon` (o `phpstan.neon.dist`) – config PHPStan livello 10 + type-coverage 100%
- [x] `phpunit.xml.dist` – config PHPUnit
- [x] Creazione directory test: `tests/` (unit/integration) con `bootstrap.php` se necessario
- [x] Verifica `.gitignore` (vendor, cache, report) – creato in questa fase

## 4. FASE 1 - FIX MINIMI BC (BLOCCHI CRITICI → NON CRITICI)

**Obiettivo**: azzerare errori fatali su PHP 8.4 e ridurre warning sistematici.

### 4.1 Fix già applicati
- [x] `Database.php:1396` – `continue` in switch → `continue 2` (mantiene semantica loop esterno)

### 4.2 Proprietà dinamiche (PHP 8.2 deprecation)

Analizzare classi che creano proprietà non dichiarate. Strategie:
- **Preferito**: dichiarare proprietà (`protected/public/private $prop`) – migliora tipizzazione e type-coverage.
- **Transitorio/justificato**: aggiungere `#[\AllowDynamicProperties]` su classi che usano storage dinamico (es. DTO-like, ActiveRecord con attributi dinamici) – da usare solo dove dichiarazione completa non è praticabile senza impattare API pubblica. Motivare in MODIFICHE.md.

Azioni:
- [x] Audit completo assegnazioni `$this->prop` non dichiarate (per classe).
- [x] Applicare fix mirati con motivazione per ogni classe.
- [x] Verificare `ActiveRecord` (ha `__get`, props core dichiarati) – confermare nessun caso residuo.

### 4.3 htmlspecialchars() – default charset PHP 8.1

`Core.php:801`: `htmlspecialchars((string)..., ENT_QUOTES)` senza terzo parametro. Da PHP 8.1 default UTF-8.
- [x] Valutare se necessario esplicitare charset (coerenza con resto codebase: `FlourishException`, `HTML`, `XML` usano `UTF-8`). 
- [x] Decidere: mantenere comportamento atteso (esplicitare `UTF-8`) O accettare nuovo default – documentare scelta in MODIFICHE.md.
- [x] Applicare modifica coerente e testabile.

### 4.4 Altri edge cases comportamentali

- [ ] Revisione `count()` su possibili non-countable (verifica con PHPStan).
- [ ] Revisione confronti loose (`==`/`!=`) in punti critici (DB, validazioni, ActiveRecord) – non obbligatorio fix sistematico ma annotare rischi e correggere casi problematici rilevati.
- [ ] Verifica `eval()` in `ORM.php:390`: validare stringa generata (class name safe), documentare sicurezza/limiti.

## 5. FASE 2 - CONFIGURAZIONE STRUMENTI QA

### 5.1 PHP_CodeSniffer (phpcs)

Obiettivi: definire standard coerente con stile libreria esistente (legacy, no namespace, snake_case o convenzioni storiche di Flourish). Evitare stravolgimenti inutili.

Configurazione `phpcs.xml`:
- [x] Standard base: `PSR-12` con eccezioni giustificate per codice legacy (o standard personalizzato minimale).
- [x] Include: `*.php` in root (e eventuali sottodir utili)
- [x] Escludi: `vendor/`, `tests/_output/`, `build/`, cache
- [x] Sniff critici abilitati; regole troppo invasive disabilitate con commento/justificazione
- [x] Verificare funzionamento: `vendor/bin/phpcs --standard=phpcs.xml`

### 5.2 PHPStan livello 10 + type-coverage 100%

**Requisito obbligatorio**: `level: 10` e `type_coverage: max: 100%` (configurato per essere rispettato al 100%).

Plugin: `phpstan/phpstan-type-coverage`. Configurazione `phpstan.neon`:
- [x] `level: 10`
- [x] `paths`: includere tutti i file `.php` della libreria (root + eventuali necessari), escludere `vendor/`
- [x] `bootstrapFiles` se necessario
- [x] `type_coverage`:
  - `return_type: 100`
  - `param_type: 100`
  - `property_type: 100`
  - `constant_type: 100` (ove applicabile)
  - `declare: 100`
- [x] `checkMissingCallableSignature: true`, `checkUninitializedProperties: true`, `checkBenevolentUnionTypes: true`, `checkImplicitMixed: true`
- [ ] `strictRules` abilitati ove sensati (estensione `phpstan-strict-rules` NON installata – da valutare)
- [x] Considerare `baseline.neon` **solo in caso eccezionale e temporaneo** con documentazione/obiettivo di rimozione. Obiettivo finale: nessuna baseline, 100% type-coverage. (Nessuna baseline creata.)

**Note su livello 10**: richiede tipizzazione completa (parametri, return, proprietà, costanti, generics se presenti). Per libreria legacy senza namespace/tipi, è il carico maggiore ma coerente con "non solo tipizzazione".

### 5.3 PHPUnit

Configurazione `phpunit.xml.dist`:
- [x] Bootstrap appropriato
- [x] Test suite `Unit` (e `Integration` se utile)
- [ ] Coverage configurabile (opzionale ma utile)
- [x] Target PHP 8.4

## 6. FASE 3 - ANALISI STATICA E PULIZIA INCREMENTALE

**Approccio**: correggere errori PHPStan per livelli crescenti? O puntare direttamente a livello 10 con type-coverage 100% partendo da classi core e propagando.

### 6.1 Baseline iniziale (strategia)
- [ ] Eseguire PHPStan livello 0→5 per mappare errori (facoltativo) o direttamente livello 10 per vedere blocchi.
- [ ] **Non creare baseline permanente** a meno di blocchi insormontabili da documentare. Preferire fix incrementali per file/moduli.
- [ ] Priorità: `Core`, `FlourishException`, `Database`, `ActiveRecord`/`ORM`, `RecordSet`, `Request`, `URL`, `Date`, `Email`, `HTML`, `JSON`, `File`, `Directory`.

### 6.2 Tipizzazione proprietà/classi
- [ ] Dichiarare tutte le proprietà di istanza (elimina dynamic properties deprecation).
- [ ] Aggiungere tipi proprietà (PHP 7.4+ typed properties, pienamente supportati in 8.4).
- [ ] Tipizzare costanti se rilevante.

### 6.3 Tipizzazione metodi
- [ ] Parametri: type hints completi (`string|int|array|...|null`, `mixed` solo quando inevitabile e giustificato).
- [ ] Return types: completi per tutti i metodi pubblici/protected/private (`void`, union, `static`, `never`, `mixed` minimale).
- [ ] `__construct`, `__get`, `__set`, `__call`, `__toString`, `__wakeup`/`__sleep` tipizzati correttamente.
- [ ] Metodi con parametri opzionali/null: usare nullable `?Type` o union `Type|null` coerenti.

### 6.4 Strictness & PHP 8.4 idiomi
- [ ] Verificare segnature compatibili con override (LSP) – PHPStan livello 10 aiuta.
- [ ] Usare `#[\Override]` dove opportuno (PHP 8.3+) – valutare aggiunta incrementale (non obbligatoria ma consigliata).
- [ ] Evitare `mixed` non necessario; preferire union types espliciti.

## 7. FASE 4 - TEST SUITE (PHPUnit)

**Obiettivo**: avere copertura minima per core paths e prevenire regressioni su migrazione.

### 7.1 Struttura test
- [ ] Creare `tests/Unit/` per classi isolate
- [ ] Creare `tests/Integration/` per interazioni (DB, File, Email, ActiveRecord/ORM) se applicabile
- [ ] `tests/bootstrap.php` (autoload Composer, setup env)

### 7.2 Test critici (prioritari)
- [ ] `Core`, `FlourishException` (error handling)
- [ ] `Database` (escape, query building, mssql branch corretto dopo fix continue)
- [ ] `ActiveRecord`/`ORM` (identity map, CRUD, relazioni)
- [ ] `Request`/`URL`/`Cookie` (input, slashes, magic_quotes guards)
- [ ] `Date`/`Time`, `JSON`, `HTML`/`XML` (escaping, formattazione)
- [ ] `File`/`Directory`/`Filesystem`

### 7.3 Criteri test
- [ ] Test esistenti: se presenti, portarli a funzionare su PHP 8.4.
- [ ] Nuovi test: coprire casi BC e regressioni (es. fix continue 2, htmlspecialchars).
- [ ] Eseguire suite verde (`vendor/bin/phpunit`).

## 8. FASE 5 - VERIFICA FINALE E CRITERI DI ACCETTANZA

### 8.1 Checklist accettazione

- [ ] **PHPStan livello 10**: 0 errori.
- [ ] **PHPStan type-coverage**: **100%** su tutte le metriche configurate (return/param/property/constant/declare).
- [ ] **PHPCS**: 0 errori (warnings gestiti/risolti o documentati).
- [ ] **PHPUnit**: tutti i test passano (0 failures, 0 errors).
- [ ] **Lint**: `php -l` su tutti i file .php della libreria → nessun warning/error.
- [ ] **Compatibilità runtime**: test smoke su scenari d'uso tipici (es. bootstrap minimale, connessione/ORM, rendering HTML).
- [ ] **Deprecazioni**: nessuna deprecazione PHP 8.4 emessa in test/smoke (verificabile con `error_reporting=E_ALL`).

### 8.2 Documentazione
- [ ] `MODIFICHE.md` aggiornato per ogni blocco di modifiche (file, righe, motivazione, impatto BC).
- [ ] Note su breaking changes (se introdotti) chiaramente documentati.
- [ ] Motivazioni per eccezioni (es. `#[AllowDynamicProperties]` usato) riportate in MODIFICHE.md.

### 8.3 Ordine di esecuzione consigliato

1. Fase 0 (composer/dev deps + configs)
2. Fase 1 (BC minimi)
3. Fase 2 (configs validate)
4. Fase 4 (test base) – stabilire baseline test
5. Fase 3 (tipizzazione + PHPStan/type-coverage, iterativo file per file)
6. Fase 5 (verifica finale + pulizia)

## 9. NOTE TECNICHE IMPORTANTI

- **Non solo tipizzazione**: la conversione include correzioni comportamentali (BC), rimozione deprecazioni, allineamento strictness PHP 8.x e miglioramento robustezza.
- **100% type-coverage**: obiettivo stringente ma richiesto. Se necessario, usare `mixed` con giustificazione esplicita in commento e documentazione, minimizzandolo.
- **Backward compatibility API pubblica**: mantenere firme pubbliche esterne ove possibile; modifiche interne solo se necessarie a correttezza/typing su 8.4.
- **Eval (ORM)**: non rimuovere senza alternativa robusta (dinamica class generation). Verificare con PHPStan in modo sicuro e testare.

## 10. STATO ESECUZIONE

**Stato attuale** (aggiornato 2026-10-06): **refattorizzazione strutturale completata**; avvia Fase 3 (PHPStan) sul codice rifattorizzato.

Completato:
- Fase 0 (composer PHP ^8.4, dipendenze QA, configs, test dir, .gitignore).
- Fase 1: fix BC (`continue 2`, proprietà dinamiche `RecordSet`, `htmlspecialchars` UTF-8).
- Refattorizzazione "prima del PHPStan" come richiesto:
  - typo fatale `strotlower` → `strtolower` (Schema);
  - rimozione motori DB estinti (`mysql_*`/`mssql_*`/`sqlite` v2) da Database/Statement/Result/UnbufferedResult/SQLTranslation – tenuti pdo, mysqli, pgsql, oci8, sqlsrv, ibm_db2 (+ pdo_dblib);
  - rimosso mcrypt da Cryptography → openssl AES-256-CFB (formato `symmetric2#`, marker legacy ancora verificati);
  - rimossi **473 costanti-facciata** (const = 'Class::method') → `array(self::class, 'method')`;
  - rimossi guard/polyfill morti (magic quotes, define E_*, `checkVersion('5.x')`, `__autoload`);
  - `JSON` collassato a wrapper `json_*` (parser manuale PHP <5.2 rimosso, 733→118 righe);
  - `Loader` eliminato (funzioni costruttore inutilizzate, nessun uso esterno);
  - **rimosso prefisso `f` da classi, file e tutti i riferimenti** (66 classi, `fException` → `FlourishException`);
  - fix runtime PHP 8 trovati dai test funzionali (Money str_replace float, Request `REQUEST_METHOD` unset, Validation `?array`, `Image::append(): never`, `XML` ArrayAccess, marker crittografici + `openssl_seal/open` PHP 8).
- Verifica: `php -l` 66/66 OK; caricamento classi 66/66 senza fatal; PHPUnit 1 test OK; suite funzionale e smoke OK.

Restano: **Fase 3** (PHPStan livello 10 + type-coverage 100% incrementale), espansione test (Fase 4), verifica finale (Fase 5).

### 10.1 Progresso Fase 3 (aggiornato 2026-10-08)

Metodo per file: docblock precisi + `@phpstan-type`; guard su return falsificabili (`?? $var`, `=== FALSE`); inline `@var` per shape di righe DB / narrowing dopo guard; pattern read-modify-write per array `array<string, Shape>` scritti a chiave dinamica; callable firmati `callable(mixed...): mixed` (o bare `callable` dove passano `array(Class, 'method')`). Vietati: `@phpstan-ignore`, baseline, `assert()`, cast/widen solo per silenziare.

Completati a **0 errori**:
- `HTML.php`, `Core.php`, `Schema.php`.

Baseline iniziale (dopo refactoring): 6937 errori / 52 file. Dopo `Schema`/`HTML`/`Core`: **5223** errori complessivi.

In corso (sotto-agenti paralleli, un gruppo di file ciascuno): Database+Statement+Result+UnbufferedResult; ORMRelated/ORMValidation/ORMDatabase/ORMSchema/ORMColumn/ORMFile/ORMOrdering; ORM/ORMDate/ORMJSON/RecordSet/CRUD/Pagination/Request/XML; Validation/SQLTranslation/Templating/Cache/Grammar; Number/Money/ORMMoney; Image/Upload/File/Directory/Filesystem; Mailbox/Email/SMTP/Messaging; Cryptography/Timestamp/Time/Date/URL/Authorization/Session/FlourishException/ValidationException/Buffer; SQLSchemaTranslation.php; ActiveRecord.php.

Gate di accettazione: PHPStan livello 10 = 0 su tutti i file; `vendor/bin/phpunit` verde (42 test / 65 asserzioni); type-coverage 100%.