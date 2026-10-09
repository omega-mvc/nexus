# REGISTRO DELLE MODIFICHE - Conversione a PHP 8.4

> Registro cronologico di tutte le modifiche apportate alla libreria Flourish per renderla compatibile con PHP 8.4. Ogni modifica deve includere: data, file, righe/intervallo, tipo di modifica, motivazione, impatto e verifica.

## FORMATO ENTRATA

```markdown
### [YYYY-MM-DD HH:MM] - Titolo breve

- **File**: `nome.php`
- **Intervallo righe**: `XXX-YYY`
- **Tipo**: `fix BC` | `tipizzazione` | `style` | `config` | `test` | `refactor`
- **Descrizione**: Spiegazione dettagliata della modifica
- **Motivazione**: Perché necessario (BC PHP 8.x / PHPStan / type-coverage / phpcs)
- **Impatto**: `nessuno` | `minimo` | `potenziale` | `documentato`
- **Verifica**: Come verificato (`php -l`, `phpstan`, `phpcs`, `phpunit`, test manuale)
- **Issue/Rischio**: Note su regressioni o edge case
```

## MODIFICHE APPORTATE

### [2025-10-05 14:40] - Verifica iniziale stato compatibilità

- **Tipo**: `analisi`
- **Descrizione**: Analisi statica completa libreria per compatibilità PHP 8.4. Scansione pattern BC-breaking (create_function, each, /e preg_replace, split/ereg globali, accesso brace {}, get_magic_quotes, assert string-form, old-style constructors, proprietà dinamiche, deprecazioni 8.x).
- **Motivazione**: Determinare stato attuale e definire piano migrazione (non solo tipizzazione).
- **Impatto**: nessuno
- **Verifica**: `php -l` su 67 file .php (solo warning corretto nella modifica successiva), grep mirati su pattern critici.
- **Risultato**: Sintassi compatibile al 95%+, nessun blocker fatale identificato.

### [2025-10-05 14:41] - Fix continue targeting switch in Database

- **File**: `Database.php`
- **Intervallo righe**: `1396`
- **Tipo**: `fix BC`
- **Descrizione**: Modificato `continue;` all'interno di `switch` (annidato in `foreach`) a `continue 2;` per continuare correttamente il ciclo `foreach` esterno.
- **Motivazione**: PHP 7.3+ emette warning "continue targeting switch is equivalent to break"; comportamento inteso nel codice originale era continuare iterazione foreach (gestione carattere multi-byte 4-byte → sostituito con `?`, passa al carattere successivo). `continue 2` preserva semantica corretta su PHP 8.x.
- **Impatto**: minimo (corregge warning, preserva comportamento logico)
- **Verifica**: `php -l Database.php` → No syntax errors. `php -l *.php` → 67 file OK, 0 warnings.
- **Issue/Rischio**: Nessuno. Caso specifico chiaramente identificato (default case in switch dentro foreach su parsing UTF-8 MSSQL).

### [2025-10-05 14:42] - Creazione SCALETTA.md

- **File**: `SCALETTA.md`
- **Tipo**: `documentazione`
- **Descrizione**: Creato documento operativo dettagliato con fasi esatte, ordine esecuzione, criteri accettazione, configurazioni richieste (phpcs, phpstan livello 10 + phpstan-type-coverage 100%, phpunit).
- **Motivazione**: Fornire roadmap completa e verificabile per conversione a PHP 8.4 (non solo tipizzazione).
- **Impatto**: nessuno
- **Verifica**: File creato correttamente, contenuto completo.

### [2025-10-05 14:43] - Creazione MODIFICHE.md

- **File**: `MODIFICHE.md`
- **Tipo**: `documentazione`
- **Descrizione**: Creato registro modifiche con formato standardizzato per tracciare ogni cambiamento (BC fixes, tipizzazione, config, test).
- **Motivazione**: Tracciabilità, reversibilità e audit delle modifiche.
- **Impatto**: nessuno
- **Verifica**: File creato correttamente.

### [2025-10-05 14:44] - Fase 1: Fix BC minimi e pulizia

- **File**: `RecordSet.php`, `Core.php`
- **Intervallo righe**: RecordSet: ~511-520 (aggiunte propriet�); Core: 801
- **Tipo**: `fix BC` | `refactor`
- **Descrizione**: Dichiarate propriet� dinamiche reali in RecordSet (`sort_method`, `sort_direction`). Aggiunto charset esplicito UTF-8 in `htmlspecialchars()` in Core::expose(). 
- **Motivazione**: Eliminazione deprecazioni PHP 8.2+ e allineamento comportamento PHP 8.1.
- **Impatto**: minimo
- **Verifica**: `php -l` OK. PHPStan non segnala pi� propriet� dinamiche per RecordSet.

### [2025-10-05 14:45] - Verifica stato

- **Tipo**: `verifica`
- **Descrizione**: Verifica completa stato post-configurazione e Fase 1. `php -l`: 67 file, 0 errori/0 warning. PHPUnit: OK (1 test, 1 assertion). PHPCS: configurato. PHPStan livello 10 + type-coverage 100%: configurato correttamente (11591 errori rilevati su codebase legacy - attesi, da correggere incrementalmente).
- **Motivazione**: Confermare configurazioni operative e stato coerente con SCALETTA.
- **Impatto**: nessuno
- **Verifica**: Lint, PHPUnit, PHPStan.

## PENDENTI

- [x] Fase 0: `composer.json` (PHP ^8.4), dev deps (phpcs, phpstan, type-coverage, phpunit), configs.
- [x] Fase 1: Audit proprietà dinamiche + fix mirati, `htmlspecialchars()` UTF-8 in Core.
- [x] Refactoring strutturale (prima di PHPStan): motori DB estinti, mcrypt, 473 costanti facciata, polyfill/guard morti, collasso JSON, eliminazione Loader, rimozione prefisso `f` da classi/file/riferimenti (66 classi → `FlourishException` per `fException`), fix runtime PHP 8.x (openssl_seal/open, covarianza `Image::append`, `ArrayAccess` XML, `Money`, `REQUEST_METHOD`, `Validation::validate`).
- [ ] Fase 2-5: Tipizzazione completa per PHPStan livello 10 + type-coverage 100% sul codice rifattorizzato, test suite PHPUnit, verifica finale (PHPCS, PHPStan, PHPUnit).

### [2025-10-06 00:00] - Fase 3: Aggiunta declare(strict_types=1) e miglioramenti tipizzazione

- **File**: Tutti i file .php principali (67 file)
- **Tipo**: `tipizzazione` | `fix BC`
- **Descrizione**: Aggiunto `declare(strict_types=1)` a tutti i file della libreria. Miglioramenti incrementali di tipizzazione in FlourishException (riduzione errori da molti a 16), Text (riduzione a 9), Core (117), HTML, Date, RecordSet e altre classi core. Correzioni per gestire valori nullable/false da funzioni PHP.
- **Motivazione**: Avanzamento verso PHPStan livello 10 + type-coverage 100% secondo piano di migrazione.
- **Impatto**: minimo (migliora type safety)
- **Verifica**: `php -l` su tutti i file modificati OK. PHPStan: errori totali ~11511 (down da ~11591), progresso incrementale.
- **Risultato**: Base di codice con strict types abilitato, lavoro incrementale in corso.

### [2026-10-06 09:00] - Refactoring fase 1: typo fSchema, motori DB estinti, rimozione polyfill

- **File**: `Schema.php`, `Result.php`, `UnbufferedResult.php`, `Statement.php`, `SQLTranslation.php`, `Cryptography.php`, `Core.php`, `Cookie.php`, `Request.php`, `URL.php`, `Date.php`, `Time.php`, `Timestamp.php`, `Templating.php`, `ORM.php`, `ActiveRecord.php`, `Mailbox.php`, `SMTP.php`, `Image.php`
- **Tipo**: `fix BC` | `refactor`
- **Descrizione**: (a) Corretto typo `strotlower()` → `strtolower()` in `Schema::setColumnInfoOverride()` (fatal a runtime). (b) Rimossi tutti i rami per i motori estinti `mysql_*`/`mssql_*`/`sybase_*`/`sqlite_*` (ext rimosse da PHP 7.0/5.4) da Result/UnbufferedResult/Statement/SQLTranslation: rimangono solo pdo, mysqli, pgsql, oci8, sqlsrv, ibm_db2 (+ pdo_dblib per type 'mssql'). (c) `Cryptography`: rimosso mcrypt → `symmetricKeyEncrypt/Decrypt` su openssl AES-256-CFB con marker `Cryptography::symmetric2#` + HMAC-SHA1; `hashHMAC()` sempre `hash_hmac()`; `hashPassword()` sempre bcrypt; merge `verifySymmetricKeyEnvironment`/`verifyPublicKeyEnvironment` → `verifyOpenSSLEnvironment()`. (d) Rimossi tutti i polyfill/guard morti: fallback `define()` E_* e `E_STRICT` (Core), `get_magic_quotes_gpc` (Cookie/Request/URL), branch PHP 5.0/5.1 httponly, `stripSlashes()` orfano, bug float PHP 5.2.17 (Request), `$is_51` (Date/Time), `Timestamp::checkPHPVersion()`, tutti i call site interni di `Core::checkVersion()` (API pubblica mantenuta), workaround `stream_select` rotto (Mailbox/SMTP), `function_exists('memory_get_usage')` (Image), branch `checkVersion('8.0.0')` con Reflection API rimossa (ActiveRecord).
- **Motivazione**: Codice morto/estinto: tipizzarlo sarebbe inutile; i branch mysql/mssql/sqlite e mcrypt non possono eseguire su PHP 8.4.
- **Impatto**: `potenziale` (i payload crittografici legacy con marker `fCryptography::symmetric` vengono rifiutati con `EnvironmentException`: richiedono re-encrypt; hash bcrypt legacy restano verificabili)
- **Verifica**: `php -l` su tutti i file OK; round-trip encrypt/decrypt + `checkPasswordHash` verificati.

### [2026-10-06 10:00] - Refactoring fase 2: rimozione costanti facciata (473 → 0)

- **File**: 42 file (incl. `ORM.php`, `ActiveRecord.php`, `ORMColumn.php`, `ORMFile.php`, `Core.php`, ...)
- **Tipo**: `refactor`
- **Descrizione**: Rimossi 473 dichiarazioni `const string NAME = 'Class::method';` (costanti-facciata per callback). I ~99 utilizzi reali in posizione di valore sono stati convertiti in `array(self::class, 'method')` (i consumatori dichiarano `callable`/`?array`: la stringa era già un TypeError). Rimossa anche la branch morta `is_string($callback)` in `ORM::checkHookCallback()` (il parametro è `?array`).
- **Motivazione**: Zero utilizzi esterni verificati (rescan con word-boundary: 0 riferimenti `Class::CONST` fuori dalla classe dichiarante); le costanti puntavano a string-callback incompatibili con le firme `callable`.
- **Impatto**: `nessuno` (nessun riferimento esterno; const interne inutilizzate)
- **Verifica**: scansione `self::NAME` residua → solo falsi positivi ereditati; `php -l` OK; smoke `ORM::classize()/tablize()` OK.

### [2026-10-06 11:00] - Refactoring fase 3: collasso fJSON, eliminazione fLoader, motore mysql in Database

- **File**: `JSON.php` (ex fJSON), `Loader.php` (eliminato), `Database.php` (ex fDatabase)
- **Tipo**: `refactor`
- **Descrizione**: (a) `JSON` collassato a wrapper sottile su `json_*`: eliminato parser manuale pre-PHP-5.2.9 (~450 righe: `getElementType`, `makeUnicodeCodePoint`, `scalarize`, costanti `J_*`, `function_exists('json_*')`); API `encode/decode/output/sendHeader` invariata; `encode()` lancia `UnexpectedException` se `json_encode` fallisce. (b) Eliminato `Loader.php` (autoload PHP5/`__autoload` + funzioni costruttore inutilizzate): zero riferimenti esterni verificati; `composer dump-autoload` eseguito. (c) `Database`: rimossi tutti i rami `extension == 'mysql'|'mssql'|'sqlite'` (~328 righe rimosse) con pruner token-based; eliminato `handleErrors()` (solo mssql); rilevamento estensioni riscritto (sqlite solo via header `SQLite format 3` + firma SQLite 2.1 rifiutata esplicitamente); commenti/`$error` aggiornati.
- **Motivazione**: json ext è core dal 2008; fLoader inutilizzato; rami motori estinti non eseguibili. Tipo `mssql` resta supportato via PDO dblib (path `fixDblibMSSQLDriver` conservato).
- **Impatto**: `minimo`
- **Verifica**: `php -l` OK; grep zero `mysql_|mssql_|sybase_|sqlite_*` residui; smoke JSON round-trip OK.

### [2026-10-06 12:00] - Rimozione del prefisso `f` da classi, file e riferimenti

- **File**: tutti i 66 file `.php` della root + tests + docs + composer classmap
- **Tipo**: `refactor` | `fix BC`
- **Descrizione**: Rinominate tutte le classi `fX` → `X` (es. `fDatabase` → `Database`), `git mv` dei file, riscrittura di 3861 riferimenti (codice, callback stringa `'fClass::method'`, docblock `@link`/`@package`/`@changes`, test, `SCALETTA.md`/`MODIFICHE.md`) con regex a word-boundary ordinata per lunghezza (così `fORMDate` non viene toccato da `fORM`). Eccezione: **`fException` → `FlourishException`** (impossibile dichiarare `Exception` nel namespace globale). `composer dump-autoload` rigenerato. Puliti 9 residui prose (commenti/docblock) lasciando intatto solo l'indirizzo email `fContrib@netcarving.com` in `Email.php`.
- **Motivazione**: Requisito esplicito utente: "rimuovere il suffisso f dal nome delle classi, dei file e da qualsiasi utilizzo". Namespace rimandati a Fase 4.
- **Impatto**: `potenziale` (API pubblica rinominata: i consumatori devono aggiornare i riferimenti; le chiavi `$_SESSION['Session::*']` legacy decadono)
- **Verifica**: `php -l` 66 file OK; `class_exists` su tutte le 66 classi → 66/66 caricate; classmap senza residui `f*`; grep `\bf[A-Z]\w*` → solo email intatta; PHPUnit OK.

### [2026-10-06 13:00] - Fix indotti dalla rinomina: marker crittografici con offset rigidi

- **File**: `Cryptography.php`
- **Tipo**: `fix BC`
- **Descrizione**: La rinomina aveva accorciato i marker letterali (`fCryptography::pwd3#` 20 → `Cryptography::pwd3#` 19 char) mentre `substr($hash, 0, 20)`/`29` erano hardcoded: `checkPasswordHash()` restituiva sempre FALSE. Riscritto con `str_starts_with()` + `strlen(marker)`; aggiunta normalizzazione del prefisso legacy `fCryptography::` in `checkPasswordHash()`, `publicKeyDecrypt()` e `symmetricKeyDecrypt()` così hash/payload salvati prima della rinomina continuano a verificarsi/decrittarsi.
- **Motivazione**: Bug silenzioso introdotto dalla rinomina; BC per i dati già persistiti dai consumatori.
- **Impatto**: `documentato`
- **Verifica**: test round-trip nuovo + hash legacy `fCryptography::pwd3#` + payload legacy `fCryptography::symmetric2#` + rifiuto mcrypt legacy — tutti OK.

### [2026-10-06 14:00] - Fix runtime PHP 8.x emersi dai test funzionali

- **File**: `Cryptography.php`, `Image.php`, `XML.php`, `Money.php`, `Request.php`, `Upload.php`, `Validation.php`
- **Tipo**: `fix BC`
- **Descrizione**: (a) `openssl_seal()`/`openssl_open()` su PHP 8+ richiedono il cipher esplicito e la chiave pubblica come array: `publicKeyEncrypt/Decrypt` ora usano `aes-256-cbc` con IV esplicito (nuovo formato a 5 elementi `marker#ek#iv#ct#hmac`); il formato legacy a 4 elementi (RC4, rimosso da OpenSSL 3) viene rilevato e rifiutato con `EnvironmentException` che chiede re-encrypt. (b) `Image::append()` (che lancia sempre eccezione) dichiarata `never` per covarianza con `File::append(): File` — prima: fatal "Declaration must be compatible". (c) `XML`: `offsetExists()` era `string $offset` (violava `ArrayAccess`, fatal) → `mixed`; `offsetGet(): string` restituiva NULL → `?string`; `query(): array` restituiva `DOMNodeList` (TypeError) → `DOMNodeList`. (d) `Money::__construct()`: float passato a `str_replace()` (TypeError PHP 8) → cast `(string)`; variabile indefinita `$abbreviation` nel messaggio d'errore → `$currency`. (e) `Request::is*()`/`Upload`: `$_SERVER['REQUEST_METHOD']` assente (CLI) causava `strtolower(null)` TypeError → `?? ''`. (f) `Validation::validate()` terminava senza `return` con tipo dichiarato (TypeError) → firma `?array` + `return NULL` (preserva il vecchio valore implicito).
- **Motivazione**: Incompatibilità reali a runtime con PHP 8.x, non rilevabili da `php -l`.
- **Impatto**: `documentato` (formato ciphertext pubblico cambiato: 4 → 5 elementi; il legacy resta riconosciuto)
- **Verifica**: suite funzionale (Date/Time/Timestamp/Number/Money/Grammar/HTML/XML/URL/Validation/Text/JSON/Crypto) tutta verde con `zend.assertions=1`; `php -l` 0 errori; PHPUnit OK (1 test).

### [2026-10-06 14:30] - Stato verifica post-refactoring

- **Tipo**: `verifica`
- **Descrizione**: Refactoring strutturale completato (motori estinti, mcrypt, costanti facciata, polyfill, fJSON, fLoader, prefisso `f`). `php -l`: 66 file, 0 errori. Classi caricate: 66/66. PHPUnit: 1 test OK. Smoke funzionale completo: OK.
- **Motivazione**: Punto di svolta prima della fase di tipizzazione PHPStan (ordine concordato: prima refactoring, poi PHPStan).
- **Impatto**: nessuno
- **Verifica**: lint + autoload + PHPUnit + suite funzionale.

### [2026-10-06 15:00] - Allineamento documentazione e .gitignore

- **File**: `SCALETTA.md`, `.gitignore` (nuovo)
- **Tipo**: `docs`
- **Descrizione**: SCALETTA.md portata allo stato reale: spuntate Fase 0 (composer, deps QA, configs, tests dir), Fase 1 (proprietà dinamiche, htmlspecialchars UTF-8), Fase 2 (phpcs/phpstan/phpunit configs); conteggio file 67 → 66 (Loader eliminato); nota sullo stato del refactoring (rimozione prefisso `f` inclusa) e nuova sezione "STATO ESECUZIONE" che indica la Fase 3 (PHPStan) come prossimo passo. Creato `.gitignore` (vendor, cache PHPUnit/PHPCS, report).
- **Motivazione**: il documento operativo rifletteva lo stato pre-refactoring.
- **Impatto**: nessuno sul codice.
- **Verifica**: grep: nessun riferimento residuo a classi `f*` nei due .md (le occorrenze in MODIFICHE sono citazioni storiche volute).

### [2026-10-06 15:40] - Test suite PHPUnit + fix runtime emersi (Timestamp, Grammar, cast deprecations)

- **File**: `tests/Unit/` (9 nuovi file), `Timestamp.php`, `Grammar.php`, `ValidationException.php`, + 18 file con cast non canonici
- **Tipo**: `test` + `fix BC`
- **Descrizione**: (a) creati 9 test di regressione (JSON, Cryptography, Validation, Text, Grammar, HTML, URL, Date/Time/Timestamp, Money, XML) — 42 test/65 assertion, 0 deprecazioni. (b) `Timestamp::__construct` (riga 749): `preg_match()` con soggetto `int` (da `adjust()` che passa timestamp unix) → TypeError PHP 8 → cast `(string)`. (c) `Grammar::pluralize/singularize`: dichiarate `: string` ma con `$return_error=TRUE` ritornano `FALSE` → TypeError sotto strict_types → firma `string|false`. (d) Rimosse 8 deprecazioni PHP 8.5: 25 cast non canonici `(integer)`/`(boolean)` → `(int)`/`(bool)` in 18 file; 5 `array('self', 'method')` → `array(self::class, 'method')` (Grammar, Validation, ORMValidation, UTF8); `ValidationException::__construct` usava la forma callable deprecata `array($this, 'FlourishException::__construct')` → `parent::__construct(...$params)`.
- **Motivazione**: bug reali a runtime + deprecazioni PHP 8.5; la suite copre i path fixati dal refactoring.
- **Impatto**: nessuna variazione API; `pluralize/singularize` ora dichiarano correttamente `string|false`.
- **Verifica**: `vendor/bin/phpunit` → OK (42 tests, 65 assertions), 0 deprecations; `php -l` pulito su tutti i file toccati.

### [2026-10-08 22:00] - Fase PHPStan livello 10: Database, Filesystem, fascia ORM core (0 errori)

- **File**: `Database.php`, `Filesystem.php`, `Directory.php`, `File.php`, `ORMDatabase.php`, `ORMRelated.php`, `ORMValidation.php`, `ORMFile.php`, `ORMColumn.php`, `ORMOrdering.php`
- **Tipo**: `tipizzazione` + `refactor`
- **Descrizione**: Obiettivo `phpstan analyse` a livello 10 + 100% type-coverage sui file più caldi. Metodo: narrowing con `is_*`/`instanceof`, `ProgrammerException` per valori impossibili, rimozione di codice morto/condizioni sempre vere, docblock nativi corretti dove la legacy era errata, pattern ricorrenti (`compose()` = `array_map` tipizzato, helper `runQuery()`, normalizzazione `is_string ? : ''`). `Database.php` ripulito (~328 righe morte, motori pre-PHP 8.4 rimossi). Vietati `@phpstan-ignore`, baseline e `@var`/cast di sola copertura.
- **Motivazione**: riduzione blast PHPStan 3128 → 2598 nei primi 10 file; nessuna regola di silenziamento.
- **Impatto**: nessuno (API invariata; solo il tipo di ritorno di alcuni metodi dichiarato correttamente).
- **Verifica**: `php -l`; `phpstan analyse <file>` = 0 errori; blast full decrescente; `phpunit --testsuite Unit` 42/65 green dopo ogni commit.
- **Commit**: `362c03a` (ORMValidation+ORMRelated), `ec9508e` (ORMDatabase), `8377d38` (Filesystem+Database purge), `6087b2d` (ORMFile), `af1ef05` (ORMColumn), `cff18bd` (ORMOrdering).

### [2026-10-08 23:30] - Fase PHPStan: RecordSet e ActiveRecord a 0 errori (+10 file a zero)

- **File**: `RecordSet.php`, `ActiveRecord.php`
- **Tipo**: `tipizzazione` + `refactor`
- **Descrizione**: `RecordSet` (181 → 0): generici via `@implements IteratorAggregate<int, ActiveRecord>` / `ArrayAccess<int|string, mixed>` (i generici nativi in `implements` sono sintassi invalida), helper `runQuery()` e `getSingleClass()`, accumulatori query normalizzati, `getOrderBys()` con route non-nullable, `slice/count/getPages` con `(int)`. `ActiveRecord` (141 → 0): helper `relationshipString()` per gli array relationship, widening del docblock di `hash()` all'effettivo dominio, narrowing `__call` ecc., reshape onesto dei messaggi di validazione prima di `ORMValidation::replaceMessages()` (forma `string|array{name, errors}`), fix di bug legacy reali (es. `__construct` restituiva il risultato di `__call`, docblock `@return void` su `fetchResultFromUniqueKey` che ritorna `Result`). `ORM.php` lasciato intatto come da vincolo.
- **Motivazione**: blast 2598 → 2458; file a zero errori passano da 8 a 10.
- **Impatto**: nessuno (comportamento preservato; guardie rimosse solo dove provate morte da PHPStan e dal flusso reale).
- **Verifica**: `php -l`; per-file 0; blast 2458 senza nuove hotspot; phpunit 42/65; commit con rimozione del symlink `vendor` accidentalmente in staging.
- **Commit**: `7c2a981` (RecordSet), `2b5a2d8` (ActiveRecord).
- **Commit**: `79cea2291` (Image), `18f0376` (Mailbox).

### [2026-10-09 01:15] - Fase PHPStan: Number a 0 errori (13 file a zero)

- **File**: `Number.php`
- **Tipo**: `tipizzazione` + `refactor`
- **Descrizione**: `Number` (214 → 0), classe big-number bcmath. Scoperta chiave: **PHPStan 2.2.17 risolve il nome di classe `Number` nei docblock `@param/@return/@var` al pseudo-tipo builtin `number` (`float|int`)**, ombreggiando la classe → fix globale `\Number` con prefisso backslash nei docblock. `@phpstan-assert numeric-string` su `$this->value` (proprietà) viene **invalidato dalle chiamate successive** → pattern "due locali": copia in variabile locale + assert prima di ogni `bcadd/bcmul/bcdiv/bcsub/bcmod/bcpow/bcsqrt`; helper `assertNumericString()`. `normalize()` → `@return array{0: string, 1: string}`; `performDiv` con `&$remainder=''` (default `''` non `null`: `parameterByRef.unusedType`); `performMul(string|int, string|int)`; `(int)` su char digit in tutte le perform*; `setScale(?int)`/`fixScale(?int)`/`performPow(?int)`/`performSub(?int)`; callbacks format/unformat con guardia `is_callable` + `get_class` catturato; `parse/unformat` con `($decimal_pos)` precalcolato; `round/trunc/sqrt` con `intdiv`, `(int) $number[$length-1] >= 5`, `$p = ($p) ? $p . $x : (string) $x`.
- **Motivazione**: blast 1987 → ~1773; file a zero passano da 12 a 13.
- **Impatto**: nessuno (comportamento preservato; guardie `is_numeric` su parametri `int` morte rimosse; default `$remainder=''` neutro perché il by-ref è solo scritto mai letto).
- **Verifica**: `php -l`; per-file 0; blast decrescente; phpunit 42/65 green.
- **Commit**: `5ffb36a`.
- **Prossimo**: coda hotspot Email 170, SQLSchemaTranslation 169, Validation 125, Templating 107, SQLTranslation 106, Cache 91, Grammar 79, ORMMoney 78, UTF8 67, Pagination 67 (ORM 135 resta intoccabile).

### [2026-10-09 00:30] - Fase PHPStan: Image e Mailbox a 0 errori (12 file a zero)

- **File**: `Image.php`, `Mailbox.php`
- **Tipo**: `tipizzazione` + `refactor`
- **Descrizione**: `Image` (238 → 0): `@phpstan-type Modification` disgiuntivo su literal `operation`, guardie GD `imagecreatetruecolor`/greyscale, `(int)` legittimi su numeric-string per gli int GD sotto `strict_types`. `Mailbox` (233 → 0): alias ricorsivo `MailboxStructure` **rotto** (PHPStan 2.2 non supporta type alias ricorsivi → `typeAlias.circular`); `parts` tipizzati `list<array<string, mixed>>|null`, consumer (`handlePart`, `handleSMIME*`) su `array<array-key, mixed>`; S/MIME con `array<string, mixed> &$info` (nativo `array &$info`); restyle completo `parseStructure` con loop `array<string, string>` espliciti per `type_fields`/`disposition_fields`; `connect()` con `fsockopen` → variabile locale + `(float)` timeout + `usleep(100000)` per `sleep(0.1)`; `read()/write()` con guardia `is_resource`, `str_pad((string) $this->command_num++)`, `Core::getDebug($this->debug === TRUE)`; `listMessages` con `is_numeric` prima dei `(int)` (**PHPStan 2.2 vieta `(int) mixed`** → `cast.int`), envelope `is_array`, subject `preg_replace(...) ?? $subject`; `deleteMessages` via `is_array` (rimosso `settype`); `fetchMessageSource` con `$byte_count`; `end()` su array non-vuoto tipizzato `string` (niente `is_string` — `function.alreadyNarrowedType`).
- **Motivazione**: blast 2458 → 1987; file a zero passano da 10 a 12. Nessuna regola di silenziamento; tutti i cast premessi da narrowing.
- **Impatto**: nessuno (comportamento preservato; malformed-response paths ora throw `ProgrammerException` espliciti invece di warning silenziosi).
- **Verifica**: `php -l`; per-file 0; blast 1987 (`tail`: Number 214, Email 170, SQLSchemaTranslation 169, ORM 135, Validation 125, Templating 107, SQLTranslation 106, Cache 91, Grammar 79, ORMMoney 78, UTF8 67, Pagination 67); phpunit 42/65 green.
- **Commit**: `79cea2291` (Image), `18f0376` (Mailbox); verifica `vendor` non in stage pre-commit.
- **Prossimo**: coda hotspot Number 214, Email 170, SQLSchemaTranslation 169, Validation 125, Templating 107, SQLTranslation 106, Cache 91, Grammar 79… (ORM 135 resta intoccabile come da vincolo).
