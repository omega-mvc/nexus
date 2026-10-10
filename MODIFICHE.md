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
- **Descrizione**: `RecordSet` (181 → 0): generici via `@implements IteratorAggregate<int, ActiveRecord>` / `ArrayAccess<int|string, mixed>` (i generici nativi in `implements` sono sintassi invalida), helper `runQuery()` e `getSingleClass()`, accumulatori query normalizzati, `getOrderBys()` con route non-nullable, `slice/count/getPages` con `(int)`. `ActiveRecord` (141 → 0): helper `relationshipString()` per gli array relationship, widening del docblock di `hash()` all'effettivo dominio, narrowing `__call` ecc., reshape onesto dei messaggi di validazione prima di `ORMValidation::replaceMessages()` (forma `string|array{name, errors}`), fix di bug legacy reali (es. `__construct` restituiva il risultato di `__call`, docblock `@return void` su `fetchResultFromUniqueKey` che ritorna `Result`). `ORM.php` rimandato (nessun vincolo esiste: vedi la voce di chiarimento del 2026-10-10).
- **Motivazione**: blast 2598 → 2458; file a zero errori passano da 8 a 10.
- **Impatto**: nessuno (comportamento preservato; guardie rimosse solo dove provate morte da PHPStan e dal flusso reale).
- **Verifica**: `php -l`; per-file 0; blast 2458 senza nuove hotspot; phpunit 42/65; commit con rimozione del symlink `vendor` accidentalmente in staging.
- **Commit**: `7c2a981` (RecordSet), `2b5a2d8` (ActiveRecord).
- **Commit**: `79cea2291` (Image), `18f0376` (Mailbox).

### [2026-10-09 12:49] - Fase PHPStan: Email a 0 errori (14 file a zero)

- **File**: `Email.php` (+ `stubs/com.stub`, `phpstan.neon`)
- **Tipo**: `tipizzazione` + `refactor` + `config`
- **Descrizione**: `Email` (170 → 0) in tre round. Proprietà email tipizzate (`to_emails`/`cc_emails`/`bcc_emails` → `array<int, string>` puri, `from_email`/`reply_to_email`/`sender_email`/`bounce_to_email` → `?string`), guardie `is_array` morte rimosse; `extrapolateFileInfo` riscritto **dai by-ref al return** `array{0: string, 1: string, 2: string|null}` (fix `parameterByRef.unusedType`), `addAttachment`/`addRelatedFile` da `mixed $contents` a `string|File`; `buildMultiAddressHeader` accetta `array<int,string>|string`; setters ritornano `Email|null` con `$name ?? ''`; `validate()` con loop `$email = $this->$field`; S/MIME con guardie `tempnam`/`file_get_contents` + `ProgrammerException`/`EnvironmentException` e flags `openssl_pkcs7_encrypt` `0` al posto di `NULL`; send() Windows con `$from`/`$parameters`/`$matches[0]`/`ini_set(sendmail_from)` ristrutturati; nuovo `com.stub` per la classe `COM` (Windows-only `com_dotnet`) via `scanFiles` in phpstan.neon; `$fqdn` → `static private ?string $fqdn`; `$message_id` → `private string` inizializzata nel costruttore; `sanitizeComposeArgs` per `vsprintf` con valori non-scalari.
- **Motivazione**: blast ~1773 → ~1603; file a zero passano da 13 a 14. Nessuna regola di silenziamento.
- **Impatto**: nessuno (comportamento preservato; code-path `addCustomHeader($array)` morto rimosso; `getFQDN` usa variabile locale).
- **Verifica**: `php -l`; per-file 0; blast decrescente; phpunit 42/65 green.
- **Commit**: `be79609`.
- **Prossimo**: coda hotspot Validation 125, Templating 107, SQLTranslation 106, Cache 91, Grammar 79, ORMMoney 78, UTF8 67, Pagination 67 (ORM 135 incluso in coda).

### [2026-10-09 21:01] - Fase PHPStan: SQLSchemaTranslation a 0 errori (15 file a zero)

- **File**: `SQLSchemaTranslation.php`
- **Tipo**: `tipizzazione` + `refactor`
- **Descrizione**: `SQLSchemaTranslation` (169 → 0), il più grande file tradotto (~5500 righe). Normalizzazione delle righe query via `fetchRowArray`/`fetchAllRowArrays`/`stringValue` nei rami DB2/MSSQL/Oracle/PostgreSQL/SQLite (iterazione `Result` → `array|stdClass` risolta); `cast.string` su `$row[...]` mixed eliminati (→ `stringValue`); offset opzionali `$data['column_name']` con `?? ''` nei rami `add_primary_key`/`drop_column`/`drop_foreign_key`; variabili Oracle `$constraint_name`/`$not_null`/`$not_null_result` inizializzate a monte con guardie `countReturnedRows()`; `string|null` da `preg_replace`/`preg_split` risolti con `?? fallback`/`?: array()`; by-ref `&$extra_statements` (`array<int, string>`) sistemati convertendo ogni `array_merge` in `foreach` append, filtro `BEGIN`/`COMMIT` via `array_filter` e ritorni `array_shift(...) ?? ''` nei metodi SQLite.
- **Motivazione**: blast ~1603 → 1427; file a zero passano da 14 a 15. Nessuna regola di silenziamento.
- **Impatto**: nessuno (comportamento preservato; solo tipizzazione; fix dialetto rimandati come da roadmap).
- **Verifica**: `php -l`; per-file 0; blast decrescente; phpunit 42/65 green.
- **Commit**: `8ae19a5`.
- **Prossimo**: coda hotspot Validation 125, Templating 107, SQLTranslation 106, Cache 91, Grammar 79, ORMMoney 78, UTF8 67, Pagination 67 (ORM 135 incluso in coda).

### [2026-10-09 01:15] - Fase PHPStan: Number a 0 errori (13 file a zero)

- **File**: `Number.php`
- **Tipo**: `tipizzazione` + `refactor`
- **Descrizione**: `Number` (214 → 0), classe big-number bcmath. Scoperta chiave: **PHPStan 2.2.17 risolve il nome di classe `Number` nei docblock `@param/@return/@var` al pseudo-tipo builtin `number` (`float|int`)**, ombreggiando la classe → fix globale `\Number` con prefisso backslash nei docblock. `@phpstan-assert numeric-string` su `$this->value` (proprietà) viene **invalidato dalle chiamate successive** → pattern "due locali": copia in variabile locale + assert prima di ogni `bcadd/bcmul/bcdiv/bcsub/bcmod/bcpow/bcsqrt`; helper `assertNumericString()`. `normalize()` → `@return array{0: string, 1: string}`; `performDiv` con `&$remainder=''` (default `''` non `null`: `parameterByRef.unusedType`); `performMul(string|int, string|int)`; `(int)` su char digit in tutte le perform*; `setScale(?int)`/`fixScale(?int)`/`performPow(?int)`/`performSub(?int)`; callbacks format/unformat con guardia `is_callable` + `get_class` catturato; `parse/unformat` con `($decimal_pos)` precalcolato; `round/trunc/sqrt` con `intdiv`, `(int) $number[$length-1] >= 5`, `$p = ($p) ? $p . $x : (string) $x`.
- **Motivazione**: blast 1987 → ~1773; file a zero passano da 12 a 13.
- **Impatto**: nessuno (comportamento preservato; guardie `is_numeric` su parametri `int` morte rimosse; default `$remainder=''` neutro perché il by-ref è solo scritto mai letto).
- **Verifica**: `php -l`; per-file 0; blast decrescente; phpunit 42/65 green.
- **Commit**: `5ffb36a`.
- **Prossimo**: coda hotspot Email 170, SQLSchemaTranslation 169, Validation 125, Templating 107, SQLTranslation 106, Cache 91, Grammar 79, ORMMoney 78, UTF8 67, Pagination 67 (ORM 135 incluso in coda).

### [2026-10-09 00:30] - Fase PHPStan: Image e Mailbox a 0 errori (12 file a zero)

- **File**: `Image.php`, `Mailbox.php`
- **Tipo**: `tipizzazione` + `refactor`
- **Descrizione**: `Image` (238 → 0): `@phpstan-type Modification` disgiuntivo su literal `operation`, guardie GD `imagecreatetruecolor`/greyscale, `(int)` legittimi su numeric-string per gli int GD sotto `strict_types`. `Mailbox` (233 → 0): alias ricorsivo `MailboxStructure` **rotto** (PHPStan 2.2 non supporta type alias ricorsivi → `typeAlias.circular`); `parts` tipizzati `list<array<string, mixed>>|null`, consumer (`handlePart`, `handleSMIME*`) su `array<array-key, mixed>`; S/MIME con `array<string, mixed> &$info` (nativo `array &$info`); restyle completo `parseStructure` con loop `array<string, string>` espliciti per `type_fields`/`disposition_fields`; `connect()` con `fsockopen` → variabile locale + `(float)` timeout + `usleep(100000)` per `sleep(0.1)`; `read()/write()` con guardia `is_resource`, `str_pad((string) $this->command_num++)`, `Core::getDebug($this->debug === TRUE)`; `listMessages` con `is_numeric` prima dei `(int)` (**PHPStan 2.2 vieta `(int) mixed`** → `cast.int`), envelope `is_array`, subject `preg_replace(...) ?? $subject`; `deleteMessages` via `is_array` (rimosso `settype`); `fetchMessageSource` con `$byte_count`; `end()` su array non-vuoto tipizzato `string` (niente `is_string` — `function.alreadyNarrowedType`).
- **Motivazione**: blast 2458 → 1987; file a zero passano da 10 a 12. Nessuna regola di silenziamento; tutti i cast premessi da narrowing.
- **Impatto**: nessuno (comportamento preservato; malformed-response paths ora throw `ProgrammerException` espliciti invece di warning silenziosi).
- **Verifica**: `php -l`; per-file 0; blast 1987 (`tail`: Number 214, Email 170, SQLSchemaTranslation 169, ORM 135, Validation 125, Templating 107, SQLTranslation 106, Cache 91, Grammar 79, ORMMoney 78, UTF8 67, Pagination 67); phpunit 42/65 green.
- **Commit**: `79cea2291` (Image), `18f0376` (Mailbox); verifica `vendor` non in stage pre-commit.
- **Prossimo**: coda hotspot Number 214, Email 170, SQLSchemaTranslation 169, Validation 125, Templating 107, SQLTranslation 106, Cache 91, Grammar 79… (ORM 135 incluso in coda).

### [2026-10-10 03:06] - Fase PHPStan: Validation e ValidationException a 0 errori (17 file a zero)

- **File**: `Validation.php`, `ValidationException.php`, `tests/Unit/ValidationTest.php`
- **Tipo**: `fix BC` + `tipizzazione` + `refactor` + `test`
- **Descrizione**: Ricostruzione del lavoro di conversione semi-abbandonato su `Validation.php` (125 → 0) e correzione dei bug introdotti, più chiusura di `ValidationException.php` (11 → 0) resa necessaria dalle modifiche. Interventi principali:
  - **Bug BC ripristinato**: `addOnlyOneRule()` era stato fuso in `addOneOrMoreRule()` (ogni regola *one-or-more* aggiungeva anche una *only-one*, cambiando la semantica) → i due metodi sono nuovamente separati e indipendenti.
  - **BC `addFileUploadRule`**: l'indice ora è `string|int|Upload|null` (garantisce compatibilità con gli indici numerici di `$_FILES`), con `ProgrammerException` sui tipi impossibili.
  - Firma (`string $field, string ...$fields`) per `addBooleanFields`/`addDateFields`/`addEmailFields`/`addEmailHeaderFields`/`addFloatFields`/`addIntegerFields`/`addRequiredFields`/`addURLFields` (rimozione di rami `func_get_args()`+`is_array` morti); `addConditionalRule` con `string|array`; `addRegexReplacement`/`addStringReplacement` con `$replace ?? ''`.
  - `compose()` valida che gli argomenti di `vsprintf` siano scalari (`ProgrammerException`); nuovo helper `stringValue()`; `castToStrings()` con `ProgrammerException` su oggetti non-stringabili.
  - **Fix bug reale presente su HEAD**: `array_map($this->makeFieldName, $fields)` → `array_map(array($this, 'makeFieldName'), $fields)` (la proprietà non è callable).
  - `makeFieldName()` semplificato (`isset($this->field_names[$field])`); docblock nativi su tutte le proprietà e su `validate()` (`?array<string, string>`); i blocchi di sostituzione `preg_replace`/`str_replace` di `validate()` riscritti come loop per-chiave per preservare `array<string, string>`; parametro by-ref dei `check*` da `mixed &$messages` ad `array &$messages` con `@param array<string, string> &$messages`.
  - **ValidationException**: aggiunti gli alias `@phpstan-type MessageText string|array{name: string, errors: array<array-key, string>}` e `@phpstan-type Messages array<array-key, MessageText>` (stessa forma già usata e a 0 in `ORMValidation`); nuovo primitivo pubblico `removeFieldName(string): string` (string → string, provabile) e `removeFieldNames()` riscritto come *read-modify-write* in-place con docblock concreto (`Messages`); `__construct` da `func_get_args()` a `(string $message='', mixed ...$args)` (il secondo argomento può essere non-array, quindi non tipizzabile `array`); `formatErrorArray()` con parametro `array<array-key, mixed>` e guardie `is_string`/`is_array` verso `list<string>`.
  - **Test**: 6 nuovi test di regressione in `ValidationTest.php` per la separazione *one-or-more*/*only-one* (incluso il caso `$values = [a, b]` che fallirebbe con il bug) — suite da 42 a 48 test.
- **Motivazione**: completare l'hotspot `Validation` in coda e rimuovere i bug funzionali ereditati dalla sessione interrotta, senza scorciatoie di silenziamento (`@phpstan-ignore`, baseline, `assert()`, cast/@var solo per tacere). La decomposizione di `removeFieldNames` evita il `return.type` che affligge le funzioni generiche che ricostruiscono array.
- **Impatto**: `minimo`. Comportamento pubblico preservato; `addOnlyOneRule`/`addOneOrMoreRule` tornano alla semantica attesa; `addFileUploadRule` accetta indici numerici; `formatErrorArray` ignora i valori non-stringa/non-array (contratto documentato: messaggi stringa); `removeFieldNames` preserva le chiavi e appiattisce il solo annidamento `name`/`errors`.
- **Verifica**: `php -l` pulito; `vendor/bin/phpstan analyse Validation.php ValidationException.php --no-progress` → 0 errori; `vendor/bin/phpstan analyse ActiveRecord.php --no-progress` → 0 errori (caller di `removeFieldNames`); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: in `removeFieldNames` l'annidamento è a un solo livello (coerente con lo shape dichiarato `errors: array<array-key, string>`); le 2 segnalazioni residue di `ORMValidation.php` riga 512 (`Number::gt/lt`) sono preesistenti e non legate a questa modifica.
- **Prossimo**: coda hotspot Templating 107, SQLTranslation 106, Cache 91, Grammar 79, ORMMoney 78, UTF8 67, Pagination 67 (ORM 135 incluso in coda).

### [2026-10-10 03:16] - Chiarimento: nessun vincolo su `ORM.php`

- **File**: `MODIFICHE.md`
- **Tipo**: `documentazione`
- **Descrizione**: Rimossa l'affermazione scorretta secondo cui `ORM.php` sarebbe "intoccabile / lasciato intatto come da vincolo", presente dalla voce `[2026-10-08 22:00]` (commit `fd480ea`) e ripetuta in tutte le voci "Prossimo" successive. Verifica effettuata: **non esiste alcun ordine** (né dell'utente, né in `SCALETTA.md`, né in `phpstan.neon`/`composer.json`/`.gitignore`) che escluda `ORM.php`. L'unico riferimento reale in `SCALETTA.md` (riga 204) chiede di **non rimuovere l'`eval()`** di generazione dinamica classi (`ORM.php:390`) senza alternativa robusta e di verificarlo con PHPStan/test — è una cautela, non un divieto. `ORM.php` è quindi riammesso nella coda degli hotspot (135 errori, attualmente il più grande rimasto).
- **Motivazione**: evitare che un'assunzione priva di fonte continui a propagarsi e a rimandare il file più critico.
- **Impatto**: nessuno sul codice; cambia solo il perimetro di lavorazione (ORM incluso).
- **Verifica**: `grep` su `SCALETTA.md`/`MODIFICHE.md`/config; `git log -S` per l'origine (prima comparsa in `fd480ea`).
- **Issue/Rischio**: resta la cautela nota sull'`eval()` di `ORM.php:390` (da verificare con PHPStan e coprire con test, non da rimuovere a cuor leggero).

### [2026-10-10 05:22] - Fase PHPStan: ORM.php a 0 errori (18 file a zero)

- **File**: `ORM.php`, `ActiveRecord.php`, `ORMColumn.php`, `ORMFile.php`, `ORMOrdering.php`
- **Tipo**: `tipizzazione` + `refactor` + `fix BC`
- **Descrizione**: `ORM` (135 → 0), il più grande hotspot rimasto dopo `SQLSchemaTranslation`.
  - Le 15 proprietà statiche `array` (senza value-type) sono state tipizzate con forme precise: le mappe di callback `array<string, array<string, callable(mixed ...): mixed>>` (o `list<...>` per `hook_callbacks`/`inspect_callbacks`/`reflect_callbacks`), `array<string, string>` per `class_database_map`/`class_table_map`/`record_names`, `array<string, array<string, string>>` per `column_names`/`related_class_names`, e `$cache` come shape `array{parseMethod: array<string, array{0: string, 1: string}>, getActiveRecordMethod: array<string, callable|false>, getActiveRecordStaticMethod: array<string, callable|false>, objectify: array<string, bool>}`; aggiunta la chiave `getActiveRecordStaticMethod` all'inizializzatore di `$cache` e al default di `reset()` (era scritta in lettura ma assente dal default).
  - `parseMethod()` → `@return array{0: string, 1: string}`; `getClass()` riorganizzato con `is_object`/`is_string`/`ProgrammerException` (prima ritornava `mixed` pur dichiarando `string`); `getActiveRecordMethod`/`getActiveRecordStaticMethod`/`getRecordSetMethod` corretti da `@return string|null` a `@return callable(mixed ...): mixed|null` (ritornavano sempre una callback, mai una stringa); `getRecordName`/`tablize` con guardia `preg_replace(...) ?? $class` e `Grammar::pluralize/singularize` con guardia `=== FALSE` → `ProgrammerException`; `getRelatedClass` con `class_exists` prima di `new ReflectionClass`; `objectify` con guardia sul tipo di `$value` prima di `new Date/Time/Timestamp`; `scalarize` non emette più `(string)` su un oggetto (ramo che lancia `ProgrammerException`).
  - Le 9 funzioni `register*` (callback): docblock `@param callable(mixed ...): mixed|array<string|object>|string $callback` e native `callable|array|string $callback` — stesso pattern di `FlourishException::registerCallback` — così da accettare sia closure sia `array('Class', 'method')` sia `'Class::method'`; dopo ogni `explode('::')` inserita la guardia `if (!is_callable($callback)) { throw new ProgrammerException(...); }` che restringe il valore allo slot `callable` delle mappe.
  - Il `static $valid_hooks` locale di `registerHookCallback` (PHPStan tipizza i locali `static` come `mixed`) è diventato la costante di classe `private const array VALID_HOOKS` (24 hook).
  - **Callers allineati** (effetto delle tipizzazioni): `ActiveRecord` — docblock di `$callback_cache`/`$static_callback_cache` estesi a `callable(mixed ...): mixed|string|false` (usati anche come cache di callback) e rimossi due `is_string` divenuti sempre-veri su `parseMethod`/`$signatures`; `ORMColumn`/`ORMFile`/`ORMOrdering` — rimossi i `is_string($subject) ? $subject : ''` (sempre veri) attorno a `Grammar::underscorize`.
  - **Non toccato**: la generazione dinamica con `eval()` delle classi ActiveRecord (nessuna alternativa robusta; resta coperta da PHPStan/test).
- **Motivazione**: chiudere l'hotspot ORM rimuovendo le tipizzazioni generiche che propagavano `mixed`; nessuna scorciatoia di silenziamento (no `@phpstan-ignore`, baseline, `assert()`, cast/@var solo per tacere). I docblock corretti (`callable|null` invece di `string|null`) riflettono il comportamento reale.
- **Impatto**: `minimo`. Comportamento pubblico preservato; `getActiveRecordMethod`/`getActiveRecordStaticMethod`/`getRecordSetMethod` dichiarano ora il vero tipo di ritorno; le guardie sui callback rendono esplicito con `ProgrammerException` un caso prima silenzioso.
- **Verifica**: `php -l` pulito su tutti i file toccati; `vendor/bin/phpstan analyse ORM.php --no-progress` → 0 errori; analisi dei caller (`ActiveRecord`, `ORMColumn`, `ORMDate`, `ORMFile`, `ORMMoney`, `ORMOrdering`, `ORMRelated`, `ORMValidation`, `ORMDatabase`) → 113 errori, **tutti già presenti nel baseline** (119) e nessuno nuovo (6 risolti); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: `ORMColumn.php`/`ORMFile.php`/`ActiveRecord.php`/`ORMOrdering.php` sono tornati al loro baseline a 0 (nessuna regressione); `ORMDate` (38) e `ORMMoney` (78) restano non portati come da coda (`ORMMoney` è tra gli hotspot).
- **Prossimo**: coda hotspot Templating 107, SQLTranslation 106, Cache 91, Grammar 79, ORMMoney 78, UTF8 67, Pagination 67 (da portare: `ORMDate` e `ORMMoney` inclusi).

### [2026-10-10 13:03] - Fase PHPStan: Templating.php a 0 errori (19 file a zero)

- **File**: `Templating.php`
- **Intervallo righe**: `1-1621`
- **Tipo**: `tipizzazione` + `refactor` + `fix BC`
- **Descrizione**: `Templating` (107 → 0), hotspot successivo dopo `ORM`.
  - Proprietà tipizzate: `$instances` → `array<string, Templating>`, `$elements` → `array<string, mixed>`, `$minification_prefix` → `?string`; `$buffered_id_sequence` da variabile statica locale (tipata `mixed` da PHPStan) a proprietà `static private int`.
  - Costruttore con `?string $root` e guardia su `$_SERVER['DOCUMENT_ROOT']`; `__get()` con `@return array{Templating, string}`.
  - `enableMinification(string $mode, Directory|string $cache_directory, Directory|string|null $path_prefix=NULL)`: guardia `realpath() === FALSE`/`is_writable()` → `EnvironmentException`, `$path_prefix` derivato da `$_SERVER['DOCUMENT_ROOT'] ?? ''` con guardia `is_string`; `enablePHPShortTags(string $mode, Directory|string $cache_directory)`.
  - `encode()`/`prepare()`: guardia `!is_array($value) && !is_string($value)` → `ProgrammerException` prima di `HTML::encode()`/`HTML::prepare()`.
  - `get()`/`delete()`: normalizzazione onesta degli argomenti array (chiave `int` → il valore è il nome elemento; chiave `string` mantenuta come chiave) con `ProgrammerException` sui valori non stringa; il nome elemento nella ricorsione è `string`.
  - `filter()`: riscrittura in read-modify-write su variabile locale (`$element_values`) per evitare accesso a offset su `mixed`.
  - `fixShortTags()`: guardie `realpath()`/`file_get_contents()` → `ProgrammerException`; `$token_regex` inizializzata; `preg_match()` spostato nella condizione; `@param array<int, mixed>` con guardia `is_string` per-ciascun-valore.
  - `handleMinified()`: guardie `is_string` su path/valori; `preg_quote($this->minification_prefix ?? '', '#')`; guardie su `file_get_contents()`/`filemtime()`.
  - `minify()`/`minifyCode()`/`minifyLiteral()`: firme by-ref `string &$part`, `string &$buffer`, `array &$stack`, `string $type`; ogni `preg_replace(...)` riassegnato a `$part`/`$chunk` ora `?? $part`/`?? $chunk` (il ritorno è `?string`).
  - `placeElement()`: `?string $file_type=NULL` (prima obbligatorio); costruzione dei gruppi tramite variabile `$current_group` e `/** @var list<array{type: string, location: string, values: list<mixed>}> $value_groups */`; guardia `instanceof self` nel ramo `'Templating'`; guardia `is_string` nel ramo `'php'`; `$group_type` locale nel `default` (prima `$type` poteva non esistere).
  - `placeCSS()`/`placeJS()`/`placeRSS()`: path/media/title estratti in variabili locali con guardie `is_string` → `ProgrammerException` prima dell'`echo` (nessun cast).
  - `placeBufferedCallback()`: `@param array<int, string> $match`.
  - `set()`: firma `string|array $element`; ramo array con `ProgrammerException` se `$value !== NULL`.
  - `verifyValue()`: ramo array riscritto con `if` annidati (`is_array($v) && !isset($v['path'])` su `mixed` induceva PHPStan a un falso "is_array sempre falso"); `$path = preg_replace(...) ?? $path`; guardia `is_string` prima di `pathinfo()`.
- **Motivazione**: chiudere l'hotspot `Templating` (107) senza tecniche di silenziamento (nessun `@phpstan-ignore`, baseline, `assert()`, cast o widening puramente soppressivi). Il `?? $part` è usato solo dove `preg_replace` può davvero restituire `null` in caso di errore runtime.
- **Impatto**: `minimo`. API pubblica invariata (solo allargamenti: `set()` accetta array come già indicato dal docblock, `placeElement()` ha `$file_type` opzionale). Gli input impossibili ora sollevano `ProgrammerException` esplicite invece di errori fatali/`TypeError`.
- **Verifica**: `php -l Templating.php` pulito; `vendor/bin/phpstan analyse Templating.php --no-progress` → 0 errori; nessun riferimento esterno a `Templating` nel codice della libreria (grep case-insensitive); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: `verifyValue()` è riorganizzato a `if` annidati unicamente per il narrowing di PHPStan su `mixed` (nessun cambiamento semantico). Nessuna regressione nota.
- **Prossimo**: coda hotspot SQLTranslation 106, Cache 91, Grammar 79, ORMMoney 78, UTF8 67, Pagination 67 (da portare: `ORMDate` 38 e `ORMMoney` inclusi).

### [2026-10-10 16:08] - Fase PHPStan: SQLTranslation.php a 0 errori (20 file a zero)

- **File**: `SQLTranslation.php`
- **Intervallo righe**: `1-983`
- **Tipo**: `tipizzazione` + `refactor` + `fix BC`
- **Descrizione**: `SQLTranslation` (106 → 0), hotspot successivo a `Templating`.
  - Proprietà `$schema_info` tipizzata con la forma precisa `array{national_columns?: array<string, list<string>>, national_types?: array<string, array<string, string>>}` (elimina la propagazione di `mixed` in `fixMSSQLNationalColumns`).
  - `__construct(Database $database)`: assegnazione diretta `$this->database = $database;` seguita da `inject($this)`; costruzione dei tipi per SQLite.
  - `compose()`: sostituzioni validate; `parseTableAliases(string $sql): array` con `@return array<string, string>` e guardia su `preg_match(...) === FALSE`.
  - `__get(string $method): array{0: self, 1: string}`; `enableCaching` con guardia `is_scalar` su `$key_token`; `createSQLiteFunctions` con `$functions` inizializzata e narrowing `PDO`.
  - Callback SQLite con parametri `int|float` e docblock (`abs`, `mod`, `round`, ecc.).
  - `fixMSSQLNationalColumns()`: la ricostruzione della cache scatta se manca **almeno una** delle due chiavi (`!isset(national_columns) || !isset(national_types)`); il `foreach` sul risultato query ora guarda `is_array($row)` (→ `ProgrammerException`), legge `$row['table'|'schema'|'column'|'type'] ?? NULL` e valida con `is_string` prima di costruire le mappe.
  - Branch regex di `fixMSSQLNationalColumns`: `($match[1] ?? '')`/`($match[2] ?? '')`; `$table_aliases[$table] ?? NULL` + `continue` se sconosciuto; variabile dedicata `$column_value` (non più riassegnazione di `$column`, che corrompeva la forma dell'array); accumulo `$to_fix[$table][] = $column_value`.
  - Tail: `array_unique($columns, SORT_REGULAR)`; `$reverse_table_aliases[$table] ?? NULL` + `continue`; `$column_name` locale; `$national_types[$table][$column_name]`.
  - `$from_clause = preg_replace(...) ?? $from_clause` (loop sub-select) e `$sql = preg_replace(...) ?? $sql`.
  - `fixOracleEmptyStrings()`: `?? $sql` su tutte le riassegnazioni `preg_replace`.
  - `translate()`: `Core::debug(..., $this->debug ?? FALSE)`.
  - `translateBasicSyntax()`: `$regex = array();` inizializzata; `version_compare(..., '3', '>=')`; `return preg_replace(...) ?? $sql;`.
  - `translateCastClauses()`: `$regex = array();` inizializzata nel loop; `preg_replace(...) ?? $match[2]`.
  - `translateLimitOffsetToRowNumber()`: cast espliciti `$limit = (int) $match[7]; $offset = (int) ($match[8] ?? 0);` usati al posto delle stringhe numeriche nelle somme/concatenazioni; ramo finale `else { $replacement = $match[0]; }` per garantire l'inizializzazione.
- **Motivazione**: chiudere l'hotspot `SQLTranslation` (106) senza tecniche di silenziamento (nessun `@phpstan-ignore`, baseline, `assert()`, cast o widening puramente soppressivi). I `?? $...` sono usati solo dove `preg_replace` può realmente restituire `null`; i `(int)` sono cast legittimi su stringhe numeriche per aritmetica.
- **Impatto**: `minimo`. Comportamento pubblico invariato. Le condizioni impossibili (riga query non-array, info colonna non-stringa, alias tabella sconosciuto) ora sollevano/ignorano in modo esplicito invece di generare warning o `TypeError`. `translateLimitOffsetToRowNumber()` è invocato solo per `mssql`/`oracle`/`db2`; il nuovo `else` è difensivo.
- **Verifica**: `php -l SQLTranslation.php` pulito; `vendor/bin/phpstan analyse SQLTranslation.php --no-progress --error-format=raw` → 0 errori; caller `Database.php` verificati (`new SQLTranslation($this)` a riga 1795, `inject(mixed)` a riga 2396 con docblock `@param SQLTranslation`); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota; `Database.php` risulta già a 0 errori.
- **Prossimo**: coda hotspot Cache 91, Grammar 79, ORMMoney 74, UTF8 67, Pagination 67 (da portare: `ORMDate` 36 e `ORMMoney` inclusi).

### [2026-10-10 16:56] - Fase PHPStan: Cache.php a 0 errori (21 file a zero)

- **File**: `Cache.php`
- **Intervallo righe**: `1-1004`
- **Tipo**: `tipizzazione` + `refactor` + `fix comportamento` (bug ordine argomenti Redis `setex`)
- **Descrizione**: `Cache` (91 → 0), hotspot successivo a `SQLTranslation`.
  - Introdotto il tipo `@phpstan-type FileCacheStore array<string, array{value: string, expire: int}>` per la cache su file.
  - Proprietà `$config` tipizzata `array<string, mixed>`; `$data_store` tipizzato `FileCacheStore|Database|Memcache|Memcached|Redis|null`.
  - `__construct()`: guardie `is_string` sul path per i tipi `directory` e `file`; per `file` la cache letta da disco viene validata e ricostruita nel tipo `FileCacheStore` (le voci non conformi vengono scartate), con `ProgrammerException` se il contenuto non è un array.
  - Introdotta una **layer di helper privati** che restringe `$data_store`/`$config` ai tipi attesi invece di propagare `mixed` in tutto il file: `getDatabase(): Database`, `getMemcache(): Memcache|Memcached`, `getRedis(): Redis`, `getFileStore(): array`, `getPath(): string`, `queryDatabase(Statement|string $statement, mixed ...$params): Result`. Ogni helper solleva `ProgrammerException` se il tipo non è quello previsto.
  - Tutti i `switch ($this->type)` (`add`, `clear`, `delete`, `get`, `set`) sono chiusi da un ramo finale con `ProgrammerException` (tipo cache non valido), garantendo che ogni percorso restituisca un valore.
  - Percorsi I/O resi sicuri: `scandir(...) === FALSE`, `fopen(...) === FALSE`, `fgets(...) === FALSE`, `fread(...) === FALSE` gestiti esplicitamente (skip/early-return), con `EnvironmentException` dove il fallimento è fatale (`scandir`, apertura file cache); `(int)` legittimo su `trim($expiration_date)` per il confronto temporale in `deleteExpired()`.
  - `serialize()`: ramo `string` con cast `(string)` solo su scalar/null e `ProgrammerException` per oggetti non convertibili; ramo callable con guardia `is_callable` e `is_string` sul risultato.
  - `unserialize()`: guardia `is_callable` con `ProgrammerException`.
  - `get()`: dopo lo `switch`, `$value` è considerata valorizzata solo se `isset($value) && is_string($value)`, altrimenti ritorna il default.
  - Valori di ritorno `mixed` normalizzati con cast `(bool)` dove l'estensione restituisce booleani (`setnx`, `set`, `setex`, `get` Redis; `add`).
  - `save()` e i metodi `file`/`directory` usano `getPath()` per accedere al path configurato in modo type-safe.
- **Fix comportamento**: corretto l'ordine degli argomenti di `Redis::setex()` in `set()`: era `setex($key, $value, $ttl)`, ora `setex($key, $ttl, $value)` (firma corretta `setex(string $key, int $seconds, string $value)`). Nella versione precedente il valore della cache veniva passato come TTL e la scadenza come payload.
- **Motivazione**: chiudere l'hotspot `Cache` (91) senza tecniche di silenziamento (nessun `@phpstan-ignore`, baseline, `assert()` o cast puramente soppressivi). I cast/guardie sono giustificati dal tipo reale delle API delle estensioni e dai percorsi I/O.
- **Impatto**: `basso`. Comportamento pubblico invariato a meno del bug fix Redis. Le condizioni impossibili (data store di tipo errato, serializer non callable, contenuto file non valido) ora sollevano `ProgrammerException` in modo esplicito invece di generare `TypeError`/warning.
- **Verifica**: `php -l Cache.php` pulito; `vendor/bin/phpstan analyse Cache.php --no-progress --error-format=raw` → 0 errori; `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota.
- **Prossimo**: coda hotspot Grammar 79, ORMMoney 74, UTF8 67, Pagination 67, Upload 65.

### [2026-10-10 17:29] - Fase PHPStan: Grammar.php a 0 errori (22 file a zero)

- **File**: `Grammar.php`
- **Intervallo righe**: `1-858`
- **Tipo**: `tipizzazione` + `refactor`
- **Descrizione**: `Grammar` (79 → 0), hotspot successivo a `Cache`.
  - Proprietà `$cache` tipizzata con la forma precisa `array{camelize: array{0: array<string, string>, 1: array<string, string>}, humanize: array<string, string>, pluralize: array<string, string|false>, singularize: array<string, string|false>, underscorize: array<string, string>}`.
  - Regole tipizzate `array<string, string>`: `$camelize_rules`, `$humanize_rules`, `$plural_to_singular_rules`, `$singular_to_plural_rules`, `$underscorize_rules`.
  - Nuova proprietà privata `$quantity_words` (`array<int, string>|null`) che sostituisce la `static $replacements` locale (le static locali restano `mixed` per PHPStan).
  - `camelize()`: l'hack `$upper = (int)(bool) $upper` è sostituito da `$cache_key = $upper ? 1 : 0` (chiave tipizzata `0|1` per l'indice shape della cache); `preg_replace(...) ?? $string` e `preg_replace_callback(...) ?? $string`.
  - `camelizeCallback()`: `@param array<int, string> $match`.
  - `compose()`: gli argomenti sono normalizzati con un `array_map` che restituisce `bool|float|int|string|null` (i non-scalari diventano `get_debug_type(...)`), stesso pattern già usato in `Database.php`, così `vsprintf` riceve un `array<bool|float|int|string|null>`.
  - `humanize()`: soggetto estratto in `$subject` e `preg_replace_callback(...) ?? $subject`.
  - `inflectOnQuantity()`: se `$plural_form === NULL` si valida il risultato di `pluralize()` (guardia `=== FALSE` con `ProgrammerException`); dopo il `sizeof` su array, la quantità è validata con `is_numeric()` + cast `(int)` (cast legittimo su valore numerico) con `ProgrammerException` per valori non numerici; le forme in parole usano la nuova proprietà `$quantity_words`; `str_replace('%d', (string) $quantity, $output)`.
  - `joinArray()`: `@param array<int, string> $strings`; il risultato del callback utente è validato (`is_string` → `ProgrammerException`); rimossi i `break;` irraggiungibili dopo i `return`; rimosso il `settype()` ridondante (il parametro è già `array`); `array_pop` ora tipizzato `string` nel ramo `default`.
  - `registerJoinArrayCallback()`: il callback è memorizzato con `Closure::fromCallable($callback)`, che accetta sia le stringhe `'Classe::metodo'` sia gli array, rendendo superfluo il vecchio `explode('::', ...)` e compatibile con il tipo `callable(mixed ...): mixed`.
  - `pluralize()`/`singularize()`: concatenazione con `preg_replace(...) ?? $singular_noun`/`?? $plural_noun`.
  - `splitLastWord()`: `@return array{0: string, 1: string}`.
  - `stem()`: la cascata `string|null` era causata dalla sola `preg_replace` di Step 1a, ora `?? $word`.
  - `underscorize()`: `preg_replace(...) ?? $string` nel ramo human-friendly e nel loop di conversione camelCase.
- **Motivazione**: chiudere l'hotspot `Grammar` (79) senza tecniche di silenziamento (nessun `@phpstan-ignore`, baseline, `assert()` o cast puramente soppressivi). I `?? $...` servono solo dove `preg_replace`/`preg_replace_callback` possono restituire `null`; il cast `(int)` è su un valore già validato `is_numeric`.
- **Impatto**: `basso`. Comportamento pubblico invariato; `inflectOnQuantity()` ora solleva `ProgrammerException` per quantità non numeriche (prima comportamento indefinito), coerentemente con il contratto documentato (`int` o `array`). Il formato interno di `$join_array_callback` diventa un `Closure` (proprietà privata), nessun cambiamento osservabile.
- **Verifica**: `php -l Grammar.php` pulito; `vendor/bin/phpstan analyse Grammar.php --no-progress --error-format=raw` → 0 errori; `vendor/bin/phpstan analyse` → 769 errori totali, nessun nuovo errore introdotto nei caller `Image.php` e `ORMValidation.php`; `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota.
- **Prossimo**: coda hotspot ORMMoney 74, UTF8 67, Pagination 67, Upload 65, Request 64.

### [2026-10-10 17:42] - Fase PHPStan: ORMMoney.php a 0 errori (23 file a zero)

- **File**: `ORMMoney.php`
- **Intervallo righe**: `1-608`
- **Tipo**: `tipizzazione` + `refactor`
- **Descrizione**: `ORMMoney` (74 → 0), hotspot successivo a `Grammar`.
  - Proprietà statiche tipizzate: `$currency_columns` (`array<string, array<string, string>>`, class → currency column → value column) e `$money_columns` (`array<string, array<string, string|false>>`, class → money column → currency column oppure `FALSE`).
  - Docblock dei parametri per riferimento (`&$values`, `&$old_values`, `&$related_records`, `&$cache`, `&$parameters`, `&$metadata`, `&$signatures`, `&$validation_messages`) portati a `array<array-key, mixed>`, la stessa convenzione già usata nei file chiusi (`ORMColumn`, `ORMJSON`). Il by-ref `mixed` delle funzioni `ActiveRecord::assign()`/`ActiveRecord::hasOld()` richiede `array<array-key, mixed>` e non `array<string, mixed>`, altrimenti PHPStan perde il tipo delle chiavi e segnala `parameterByRef.type`.
  - `compose()`: argomenti normalizzati con `array_map` a `bool|float|int|string|null` (i non-scalari diventano `get_debug_type(...)`), stesso pattern di `Database.php`/`Grammar.php`, così `vsprintf` riceve un array valido.
  - `encodeMoneyColumn()`: valore non-`Money` normalizzato a stringa (scalari) con `ProgrammerException` per i non-scalari, prima di `HTML::prepare()`.
  - `prepareMoneyColumn()`: ramo "empty" che restituisce `(string)` per scalari o `''` (preserva il caso `'0'`, che resta `'0'`); `$remove_zero_fraction = (bool) $parameters[0]`; valore non-`Money` normalizzato a stringa con `ProgrammerException` per i non-scalari.
  - `objectifyMoney()` e `objectifyMoneyWithCurrency()`: la guardia composta `!is_string(...) && !is_numeric(...) && !is_object(...)` è stata ristrutturata in una normalizzazione esplicita (oggetti `Money`/`Stringable` → stringa, altri scalari numerici → stringa, il resto esce invariato) che permette di chiamare `trim()` e il costruttore `Money` con un `string`; la valuta è normalizzata a `string|null` preservando la semantica legacy (`!$currency && $currency !== '0' && $currency !== 0 → NULL`).
  - `setMoneyColumn()`: `$currency_column` letto con `?? FALSE` e guardia `ProgrammerException` se non stringa (il metodo è registrato solo quando la currency column esiste).
  - `reflect()`: `$signatures` tipizzato `array<array-key, mixed>`, quindi `$column` (chiave di `$money_columns`) è `string` e non `mixed` nelle concatenazioni.
- **Motivazione**: chiudere l'hotspot `ORMMoney` (74) senza tecniche di silenziamento (nessun `@phpstan-ignore`, baseline, `assert()` o cast puramente soppressivi).
- **Impatto**: `basso`. Comportamento pubblico invariato; per valori non-scalari/non-`Stringable` in una colonna money ora viene sollevata `ProgrammerException` (prima l'operazione andava in `TypeError`), coerente con lo stile di guardia adottato negli altri file.
- **Verifica**: `php -l ORMMoney.php` pulito; `vendor/bin/phpstan analyse ORMMoney.php --no-progress --error-format=raw` → 0 errori; `vendor/bin/phpstan analyse` → 695 errori totali (da 769, −74, nessun nuovo errore introdotto altrove); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota.
- **Prossimo**: coda hotspot UTF8 67, Pagination 67, Upload 65, Request 64, Money 55.

### [2026-10-10 18:18] - Fase PHPStan: UTF8.php a 0 errori (24 file a zero)

- **File**: `UTF8.php`
- **Intervallo righe**: `1-1629`
- **Tipo**: `tipizzazione` + `refactor` + `bugfix`
- **Descrizione**: `UTF8` (67 → 0), hotspot successivo a `ORMMoney`.
  - Proprietà statiche `$lower_to_upper`, `$mb_lower_to_upper_fix`, `$mb_upper_to_lower_fix`, `$upper_to_lower`, `$utf8_to_ascii` tipizzate `array<string, string>`.
  - **Bugfix chiavi duplicate**: in `$lower_to_upper` le chiavi `'ǆ'`, `'ǉ'`, `'ǌ'` erano definite due volte; rimosso il primo valore duplicato, mantenendo l'ultimo (semantica PHP), eliminando l'errore PHPStan e il valore morto.
  - `chr()`: normalizzazione esplicita del code point (`is_int`/`is_numeric` + `(int)`, con `ProgrammerException` per valori non numerici) e sostituzione di `chr(bindec(...))` con `pack('C', bindec(...))`: PHPStan richiede `int<0,255>` per `chr()` e nessun cast lo soddisfa, mentre `pack('C')` produce lo stesso byte. `ord($first ?? '')` e concatenazione difensiva `$first . ($second ?? '') ...`.
  - `clean()`: firma `mixed $value` con docblock condizionale `@return ($value is array ? array<array-key, mixed> : string)`; nel ramo non-array guardia `ProgrammerException` per non-scalari e cast a stringa. Preserva il percorso array e i chiamanti (`Cookie`, `Request`).
  - `explode()`: `@return array<int, string>`; condizione resa `$delimiter !== NULL && $delimiter !== ''` (equivalente alla precedente, che PHPStan segnalava come `alwaysTrue`).
  - `iconv()`: il risultato `string|false` è verificato e in caso di fallimento solleva `ProgrammerException`.
  - `ireplace()`/`replace()`: nuovo helper privato `toStringOrStrings(mixed): string|array<int, string>` che valida e normalizza input stringa/array; `ireplace` costruisce i pattern con `preg_quote` e ritorna `preg_replace(...) ?? $string`.
  - `istr()`: `$pos` da `strpos` è riportato a `0` quando `FALSE`, preservando la coercizione storica di `substr`.
  - `len()`: i ritorni `int|false` di `iconv_strlen` e `preg_match_all` sono convertiti con `=== FALSE ? 0 : $x`.
  - `ltrim()`/`rtrim()`/`trim()`: guardia `$charlist === NULL || $charlist === ''` (preserva il caso `'0'`) e `preg_replace(...) ?? $string`.
  - `ord()`: inizializzato `$bin = ''` prima dello `switch` e `dechex((int) bindec($bin))`.
  - `pad()`: la mappa dei tipi di padding spostata in una proprietà statica tipizzata `array<string, int> $pad_types` (un `static` locale resta `mixed`); il risultato di `self::sub()` è assegnato solo se stringa.
  - `ucfirst()`: i risultati di `self::sub()` sono racchiusi con `is_string(...) ? ... : ''`.
  - `ucwords()`: `preg_replace_callback(...) ?? $string`; `ucwordsCallback()` con `@param array<int, string> $match`.
  - **Bugfix `wordwrap()`**: rimosso il modificatore `e` dal pattern di `preg_split` (non valido da PHP 7, faceva fallire `preg_split` per stringhe non-ASCII); il ritorno `array|false` è gestito con early-return e i risultati di `self::sub()` sono normalizzati a stringa.
- **Motivazione**: chiudere l'hotspot `UTF8` (67) senza tecniche di silenziamento, correggendo nel contempo due difetti reali (chiavi duplicate e modificatore `e` di `wordwrap`).
- **Impatto**: `basso/medio`. Comportamento pubblico invariato per gli input validi; per valori non scalari passati a `clean()`/`replace()`/`ireplace()`/`chr()` ora viene sollevata `ProgrammerException` invece di un `TypeError`/warning. `chr()` con code point numerici e `wordwrap()` per stringhe non-ASCII ora funzionano correttamente.
- **Verifica**: `php -l UTF8.php` pulito; `vendor/bin/phpstan analyse UTF8.php --no-progress --error-format=raw` → 0 errori; `vendor/bin/phpstan analyse` → 627 errori totali (da 695, −68, nessun nuovo errore; `Request.php` scende da 64 a 63); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota.
- **Prossimo**: coda hotspot Pagination 67, Upload 65, Request 63, Money 55.

### [2026-10-10 18:32] - Fase PHPStan: Pagination.php a 0 errori (25 file a zero)

- **File**: `Pagination.php` (+ docblock in `URL.php`)
- **Intervallo righe**: `1-713`
- **Tipo**: `tipizzazione` + `refactor` + `fix`
- **Descrizione**: `Pagination` (67 → 0), hotspot successivo a `UTF8`.
  - Proprietà statiche tipizzate: `$filters` → `array<int, string>`; `$templates` → `array<string, array{type: string, size: int, pieces: array<string, string>}>` (elimina la propagazione di `mixed` in `showTemplatedLinks`); proprietà d'istanza `$data` → `array<array-key, mixed>`.
  - `defineTemplate(string $name, string $type, int $size, array $pieces)`: `$pieces` docblock `array<string, string>`; la validazione `preg_match('#^\d+$#D', $size)` (impossibile/ridondante su un `int`) è sostituita da `if ($size < 0)`.
  - `extendRecordSetInfo()`: `$data` `array<array-key, mixed>`, `$class` `string|array<int, string>`; `array_map(array('ORM','getRecordName'), $class)` sostituito da una closure tipizzata `static fn(string $record_class): string => ORM::getRecordName($record_class)`.
  - `printRecordSetInfo()`/`showRecordSetLinks()`: `$parameters` `array<array-key, mixed>`; estrazione di template e data delegata al nuovo helper `extractTemplateAndData(array $parameters): array{0: string, 1: array<array-key, mixed>}`; `$per_page = $object->getLimit()` (nullable) verificato con guardia → `ProgrammerException` se il `RecordSet` non ha un limite.
  - `showTemplatedLinks()`: docblock `@return void` corretto in `@return boolean` e tipo nativo `: bool` (prima il metodo dichiarava `mixed` ma il docblock `void`, causando errori a cascata nei chiamanti); inizializzate `$start_separator`/`$end_separator` a `FALSE` prima del ramo `if`; `$data` `array<array-key, mixed>`.
  - `printPiece()`: `$name` ora `string`; `$data` `array<array-key, mixed>`; normalizzazione difensiva di `$value` (valore della variabile di template) prima dei filtri (`is_scalar` + `strlen((string) $value)`) e prima di `HTML::encode((string) $value)`; `$piece = preg_replace(...) ?? $piece`.
  - Costruttore `__construct(mixed $records, mixed $per_page=NULL, mixed $page=NULL)`: rimosso il duplicato/errato `@param RecordSet $records` nel docblock (che rendeva `$records instanceof RecordSet` sempre vero); nuovo helper `normalizeInteger(mixed $value, string $name): int` per normalizzare `$records`/`$per_page` con `ProgrammerException` su valori non interi; la validazione della pagina conserva `ValidationException` e accetta `int`, `float` interi e stringhe di cifre (come il `preg_match` originale).
  - `printInfo()`/`showLinks()`: nuovo helper `getDefaultTemplate(array $data): string` con guardia `is_string` sul valore `__template__`.
  - `set(string|array $key, mixed $value=NULL)`: il parametro `$key` torna ad accettare un array (comportamento originale), risolvendo il ramo `is_array($key)` divenuto "sempre falso".
  - `URL.php`: `@param string|array $value` di `replaceInQueryString()` allargato a `string|int|float|array` (la firma nativa è `mixed` e il metodo fa `settype($value, 'array')`, quindi accetta scalari); i chiamanti in `Pagination` passano numeri di pagina.
- **Motivazione**: chiudere l'hotspot `Pagination` (67) senza tecniche di silenziamento. I `?? $...` sono usati solo dove `preg_replace` può realmente restituire `null`; `(string)`/`(int)` sono normalizzazioni legittime su scalari.
- **Impatto**: `basso`. Comportamento pubblico invariato per gli input validi. I casi impossibili (`RecordSet` senza limite, template non-stringa, data non-array, pagina non intera) ora sollevano `ProgrammerException`/`ValidationException` esplicite invece di warning o `TypeError`.
- **Verifica**: `php -l Pagination.php` e `php -l URL.php` puliti; `vendor/bin/phpstan analyse Pagination.php URL.php --no-progress --error-format=raw` → 0 errori su `Pagination.php` (nessun nuovo errore su `URL.php`, che resta a 25); `vendor/bin/phpstan analyse` → 560 errori totali (da 627, −67); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota; `URL.php` resta a 25 errori (verrà chiuso nel proprio file).
- **Prossimo**: coda hotspot Upload 65, Request 63, Money 55, SMTP 44, XML 42, File 37, ORMDate 36.

### [2026-10-10 19:04] - Fase PHPStan: Upload.php a 0 errori (26 file a zero)

- **File**: `Upload.php`
- **Intervallo righe**: `1-694`
- **Tipo**: `tipizzazione` + `refactor` + `fix`
- **Descrizione**: `Upload` (65 → 0), hotspot successivo a `Pagination`.
  - Introdotti i tipi `@phpstan-type UploadFileEntry array{name: string, type: string, tmp_name: string, error: int, size: int}`, `UploadImageDimensions array{min_width: int, min_height: int, max_width: int, max_height: int}` e `UploadImageRatio array{width: float, height: float, allow_excess_dimension: string}`.
  - Ripristinate le costanti di classe `check`, `count` e `filter` (presenti nell'originale `fUpload`, usate come callback) dichiarate `const string ... = 'Upload::...'` per rispettare la type coverage 100% (in linea con `Email.php`).
  - `$_FILES` è tipizzato da PHPStan come `array<mixed>`: ogni accesso passa ora per un narrowing locale (`is_array` + `ProgrammerException` sui casi impossibili) prima di leggere le colonne `name`/`type`/`tmp_name`/`error`/`size`.
  - Nuovi helper privati statici: `normalizeString(mixed): string`, `normalizeInt(mixed): int` (convertono gli scalari provenienti da `$_FILES`) e `normalizeFileSize(mixed): int` (`is_numeric` → `(int)`, altrimenti stringa → `Filesystem::convertToBytes`, altrimenti `ProgrammerException`) usato da `setMaxSize()` e `validateField()`, che prima passavano a `convertToBytes` valori `string|false` (da `ini_get`) o `mixed` (da `$_POST['MAX_FILE_SIZE']`).
  - `check()`: `$_SERVER['CONTENT_TYPE']` letto con `?? NULL` e verificato `is_string` prima dello `stripos`; rimosso `isset($_FILES)` (superglobale sempre definita per PHPStan).
  - `count()`/`filter()`: narrowing esplicito di `$_FILES[$field]`; `filter()` restituisce `array<int, int|string>|NULL` e aggiorna `$_FILES[$field]` con un `unset` per colonna verificato `isset($file_array[$column]) && is_array(...)`.
  - `extractFileUploadArray()`: costruisce una `UploadFileEntry` normalizzata (chiavi tipizzate) sia nel ramo a indice singolo sia nel ramo con `$index`; guardie `ValidationException` per campi non-array e indice non `int|string`/inesistente; niente più accesso `'name'` su `mixed`.
  - `move()`: consuma la entry tipizzata, guardia `ProgrammerException` se `Filesystem::createObject()` restituisce un `Directory` (impossibile dopo `move_uploaded_file`); docblock `@return File|Image|NULL`.
  - `setImageDimensions()`: rimosse le `is_numeric()` ridondanti (i parametri sono nativi `int`, PHPStan le segnalava `alwaysTrue`); restano i controlli `< 0`.
  - `setImageRatio()`: docblock `numeric` → `mixed`; i valori sono validati `is_numeric` e memorizzati come `(float)` nella shape `UploadImageRatio`.
  - `setMaxSize()`: `ini_get()` verificato `=== FALSE` con `ProgrammerException` prima di normalizzare la dimensione.
  - `validate()`: docblock ripulito dalla coppia di `@param` duplicati/erronei; aggiunto `return NULL` finale.
  - `validateField()`: tipo di ritorno corretto `string` → `?string` (il metodo restituiva `NULL` nei rami opzionali, con `TypeError` latente a runtime) più `return NULL` finale; `getimagesize()` verificato `=== FALSE` con `ValidationException` prima di `(int) $info[0]`/`(int) $info[1]` (niente più `list()` su `array|false`); `file_get_contents()` con `=== FALSE → NULL`; `$this->mime_type_message` verificato non-`NULL` con `ProgrammerException`.
- **Motivazione**: chiudere l'hotspot `Upload` (65) senza tecniche di silenziamento (nessun `@phpstan-ignore`, baseline, `assert()` o cast puramente soppressivi). Le guardie `ProgrammerException` coprono gli stati impossibili dedotti dal flusso (`check()`/`count()` hanno già validato).
- **Impatto**: `basso`. Comportamento pubblico invariato per gli input validi; ripristinate le 3 costanti di callback presenti nell'originale; `validateField()` non genera più `TypeError` latente nei rami opzionali; i casi impossibili ora sollevano eccezioni esplicite.
- **Verifica**: `php -l Upload.php` pulito; `vendor/bin/phpstan analyse Upload.php --no-progress --error-format=raw` → 0 errori; `vendor/bin/phpstan analyse` → 495 errori totali (da 560, −65, nessun nuovo errore; caller `ORMFile.php` verificato); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota.
- **Prossimo**: coda hotspot Request 63, Money 55, SMTP 44, XML 42, File 37, ORMDate 36.

### [2026-10-10 19:40] - Fase PHPStan: Request.php a 0 errori (27 file a zero)

- **File**: `Request.php`
- **Intervallo righe**: `1-1023`
- **Tipo**: tipizzazione PHPStan level 10
- **Descrizione**:
  - Nuovo helper privato `coerceToString(mixed): string` (stringhe invariate; scalari/`NULL` con `(string)`; oggetti `Stringable` con `(string)`; altrimenti `ProgrammerException`) usato da `encode()` e `prepare()`: prima `HTML::encode()`/`HTML::prepare()` ricevevano `mixed` e potevano restituire un array da un metodo dichiarato `string`.
  - `cast()`: rimossa la condizione morta `$cast_to === null` (già intercettata dal `return` iniziale), semplificato il ramo oggetti (senza `__toString`/`Stringable` ora `ProgrammerException` invece di un cast che va in errore), aggiunte guardie `ProgrammerException` per `int|string|NULL` prima di `new Date()`, `new Time()`, `new Timestamp()` (i costruttori portati accettano `int|object|string|null`).
  - `check()`/`get()`: il `strpos($key, '[')` è ora catturato in `$bracket_pos` e testato direttamente, così `substr()` riceve un `int` invece di `int<0,max>|false`.
  - `filter()`: narrowing di `self::$backup_files` e dei backup dei superglobali con `is_array` prima del `foreach`; chiavi `(string)` per `strpos`/`preg_replace`; `$key` verificato `is_int|is_string` prima di indicizzare `$_FILES`; lettura delle colonne `type`/`tmp_name`/`error`/`size` con `is_array(...) ? (... ?? NULL) : NULL`.
  - `isAjax()` e `isGet()/isPost()/isPut()/isHead()/isDelete()`: lettura di `$_SERVER` con `?? NULL`/`?? ''` più guardia `is_string` prima di `strtolower`.
  - `initPutDelete()`: `file_get_contents('php://input')` verificato `=== FALSE` e `self::$put_delete` inizializzato ad array prima di `parse_str`.
  - `getAcceptLanguages()`/`getAcceptTypes()`/`processAcceptHeader()`: docblock `@return array<string, float>` (mappa `{valore} => {q}`).
  - `getBestAcceptLanguage()`/`getBestAcceptType()`: parametro nativo riportato a `mixed $filter=array()` (come l'originale) per mantenere vivo il fallback `func_get_args()`; l'array passa a `pickBestAcceptItem()` via `array_values()`.
  - `getValid()`: docblock `@param array<int|string, mixed>` (compatibile con i chiamanti che passano `array<mixed>`) e `array_unique(..., SORT_REGULAR)`.
  - `overrideAction()`: guardia `is_array($global)`; chiavi `(string)`; `str_replace('%action%', (string) $found, ...)`.
  - `pickBestAcceptItem()`: docblock `@param array<int, mixed>`; `key()` confrontato con `NULL` (non `false`, valore che `key()` non restituisce mai); opzioni non scalari saltate e `(string)`; `$item` già `string`; ritorno finale diretto.
  - `validateCSRFToken()`: `Session::get()` verificato `is_array`; i token normalizzati in una lista di stringhe prima di `in_array`/`array_diff`.
- **Motivazione**: chiudere l'hotspot `Request` (63) senza tecniche di silenziamento (nessun `@phpstan-ignore`, baseline, `assert()` o cast puramente soppressivi). Le guardie `ProgrammerException` coprono gli stati impossibili dedotti dal flusso.
- **Impatto**: `basso`. Comportamento pubblico invariato per gli input validi; `encode()`/`prepare()` ora garantiscono realmente un `string`; i valori non convertibili sollevano eccezioni esplicite invece di `Error`/`TypeError`.
- **Verifica**: `php -l Request.php` pulito; `vendor/bin/phpstan analyse Request.php --no-progress --error-format=raw` → 0 errori; `vendor/bin/phpstan analyse` → 432 errori totali (da 495, −63, nessun nuovo errore; caller `CRUD.php` verificato e rimasto a 14); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota.
- **Prossimo**: coda hotspot Money 55, SMTP 44, XML 42, File 37, ORMDate 36.

### [2026-10-10 20:03] - Fase PHPStan: Money.php a 0 errori (28 file a zero)

- **File**: `Money.php`
- **Intervallo righe**: `1-639`
- **Tipo**: tipizzazione PHPStan level 10
- **Descrizione**:
  - Aggiunto `@phpstan-type MoneyCurrency array<string, int|string>` e tipizzato `self::$currencies` di conseguenza (prima `@var integer`, errato rispetto al tipo nativo `array`).
  - `getCurrencies()`: docblock `@return array<int, string>`.
  - `getCurrencyInfo()`: docblock condizionale `@return ($element is null ? MoneyCurrency : ($element is 'precision' ? int : string))`; il corpo legge `$currency_info`, valida la chiave e restituisce `(int)` per `'precision'` e `(string)` per gli altri elementi.
  - `getDefaultCurrency()`: il tipo nativo passa da `string` a `?string` (l'originale restituiva `NULL` quando nessuna valuta di default era impostata; comportamento preservato).
  - `registerFormatCallback()`/`registerUnformatCallback()`: la stringa `'Classe::metodo'` è ora convertita con `Closure::fromCallable()` invece di `explode('::', ...)`, che produceva una `list<string>` non assegnabile alla proprietà `callable`.
  - Docblock: tutte le occorrenze di `Number` nei PHPDoc sono state qualificate come `\Number` (il tipo non qualificato veniva risolto da PHPStan come `float|int`, generando errori e falsi `instanceof`).
  - `__construct()`: docblock `@param \Number|string $amount`, `@param string|null $currency`; dopo l'unformat/`str_replace` il valore è normalizzato con una guardia `ProgrammerException` prima di `new Number()` (il callback di unformat poteva restituire `mixed`).
  - `__get()`: docblock `@return array{0: $this, 1: string}` (era `callable(mixed...):mixed`).
  - `allocate()`: `@return array<int, Money>`; i ratio da `func_get_args()` normalizzati con `array_map` + `normalizeNumberInput()`.
  - `mul()`: il moltiplicando è normalizzato con `normalizeNumberInput()`; `@param \Number|string|integer`.
  - `format()`: il risultato del callback è passato a `stringify()`, che garantisce il ritorno `string`.
  - `makeMoney()`: docblock `@param mixed`; il ramo oggetto ora solleva `ProgrammerException` se l'oggetto non è convertibile (niente più cast invalido), con `get_debug_type()` nei messaggi.
  - Nuovi helper privati `normalizeNumberInput(mixed): \Number|string` (Number/string invariati, int/float con `(string)`, altrimenti `ProgrammerException`) e `stringify(mixed): string` (stringa invariata; `Stringable`/scalari/`NULL` con `(string)`; altrimenti `ProgrammerException`).
- **Motivazione**: chiudere l'hotspot `Money` (55) senza tecniche di silenziamento (nessun `@phpstan-ignore`, baseline, `assert()` o cast puramente soppressivi).
- **Impatto**: `basso`. Comportamento pubblico invariato per gli input validi; `getCurrencyInfo()` ora tipizza precisamente gli elementi; `getDefaultCurrency()` è dichiarato `?string` come il comportamento reale.
- **Verifica**: `php -l Money.php` pulito; `vendor/bin/phpstan analyse Money.php --no-progress --error-format=raw` → 0 errori; `vendor/bin/phpstan analyse` → 377 errori totali (da 432, −55, nessun nuovo errore); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: `makeMoney()` accetta ancora oggetti con `__toString` a runtime; `@param mixed` documenta l'accettazione reale. Nessuna regressione nota.
- **Prossimo**: coda hotspot SMTP 44, XML 42, File 37, ORMDate 36, Cryptography 30.

### [2026-10-10 20:22] - Fase PHPStan: SMTP.php a 0 errori (29 file a zero)

- **File**: `SMTP.php`
- **Intervallo righe**: `1-628`
- **Tipo**: `tipizzazione` + `refactor` + `fix comportamento` (fix `sleep(0.1)` e rimozione del ramo morto di `select()`)
- **Descrizione**: `SMTP` (44 → 0), hotspot successivo a `Money`.
  - Rimossa la proprietà privata `$auth_methods` (mai letta né assegnata: `connect()` usa solo la variabile locale); proprietà `$debug` da `?bool` a `bool` con default `FALSE` (il valore `null` era equivalente a `FALSE` in `Core::getDebug()`/`Core::debug()`, che ora richiedono `bool`); docblock di `$line_ending` corretto da `@var integer` a `@var string`.
  - `__get()`: docblock `@return array{0: $this, 1: string}` (era `callable(mixed...):mixed`, incompatibile col valore effettivamente ritornato).
  - `connect()`: la connessione è assegnata solo se `fsockopen()` non restituisce `FALSE` (il tipo `resource|false` non è più assegnato alla proprietà `resource|null`); timeout convertito con `(float)` per `fsockopen()` e `(int)` per `stream_set_timeout()`; `(int) $match[0][1]` per `max_size`; guardia `ProgrammerException` se `$this->password` è `NULL` (dopo il controllo su `$this->username`, così `Cryptography::hashHMAC()` e `base64_encode()` ricevono una stringa); `randomString('32', ...)` corretto in `randomString(32, ...)`.
  - `find()`: `@param array<int, string> $response` e `@return array<int, array<int|string, string>>` (il tipo del valore di ritorno di `preg_match` non è `array<int, string>` ma `array<int|string, string>`): risolve a cascata tutti gli accessi `$match[0][1]`.
  - `handleErrors()`: `@param array<int, string> $response` (risolve `preg_match($regex, $line)` e `join("\n", $errors)`).
  - `read()`: `@param integer|string|null $expect` (il ramo `$expect !== NULL` non è più "sempre vero") e `@return array<int, string>`; la connessione è verificata con `is_resource()` (`ProgrammerException` se chiusa) prima di `feof()`/`fgets()`; rimosso il ramo morto `if (is_string($result))` (dipendeva dal vecchio hack PHP 5.2 già eliminato dal port, quindi `select()` non restituisce più una stringa).
  - `select()`: `@return int|false` (era `boolean|string`) e tipo nativo `: int|false`; connessione verificata con `is_resource()` per soddisfare `stream_select()`.
  - `send()`: `@param array<int, string> $to` (risolve le concatenazioni `"RCPT TO:<" . $email`); `write()`: `@return array<int, string>`.
- **Fix comportamento**: `sleep(0.1)` in `connect()` (attesa tra i tentativi di `stream_socket_enable_crypto()`) sostituito da `usleep(100000)`: `sleep()` accetta solo `int` e con `strict_types=1` la chiamata andava in `TypeError` (in precedenza dormiva 0 s); ora attende realmente 100 ms come da intento originale.
- **Motivazione**: chiudere l'hotspot `SMTP` (44) senza tecniche di silenziamento (nessun `@phpstan-ignore`, baseline, `assert()` o cast puramente soppressivi). I `(int)`/`(float)` sono conversioni legittime del timeout; le guardie `is_resource()`/`ProgrammerException` esplicitano condizioni altrimenti impossibili.
- **Impatto**: `basso`. Comportamento pubblico invariato per gli input validi. Il parametro `read()` è documentato `int|string|null` come la logica effettivamente gestita. `select()` ora ritorna `int|false` (i chiamanti usavano solo la verità del valore).
- **Verifica**: `php -l SMTP.php` pulito; `vendor/bin/phpstan analyse SMTP.php --no-progress --error-format=raw` → 0 errori; `vendor/bin/phpstan analyse` → 333 errori totali (da 377, −44, nessun nuovo errore); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota. `usleep(100000)` introduce l'attesa di 100 ms che il codice intendeva già effettuare (prima era un no-op di fatto).
- **Prossimo**: coda hotspot XML 42, File 37, ORMDate 36, Cryptography 30, Timestamp 27.

### [2026-10-10 20:48] - Fase PHPStan: XML.php a 0 errori (30 file a zero)

- **File**: `XML.php`
- **Intervallo righe**: `1-773`
- **Tipo**: `tipizzazione` + `refactor`
- **Descrizione**: `XML` (42 → 0), hotspot successivo a `SMTP`.
  - Docblock del costruttore: `@param File|string|DOMElement $source` e `@param numeric|boolean $http_timeout`; rimossi i due `@param` duplicati. L'aggiunta di `DOMElement` e `boolean` risolve sia il falso `instanceof DOMElement` sempre falso (il tipo è usato internamente da `xpath()` con `new XML($element)`) sia il falso `is_bool($http_timeout)` sempre falso (il secondo argomento può essere il booleano `$fix_entities_encoding`).
  - `file_get_contents($source, FALSE, $context)`: il secondo parametro `$use_include_path` è `bool` (era `0`); nel ramo file la lettura è verificata con `if ($source === FALSE) { throw new ExpectedException(...); }` (il percorso originale è conservato in `$path`).
  - `$exception_message` inizializzata a `''` (era `NULL`, poi passata a `str_replace()`).
  - Nuovo helper privato `dom(): DOMAttr|DOMElement` (solleva `ProgrammerException` se `$this->__dom` è `NULL`), usato al posto degli accessi diretti a `$this->__dom` in `__get()`, `__isset()`, `__toString()`, `getName()`, `getNamespace()`, `getPrefix()`, `getText()`, `query()`, `toXML()` e nel ramo `$child` di `xpath()`; elimina tutti gli errori `property.nonObject` sul tipo `DOMAttr|DOMElement|null`.
  - Nuovo helper statico `offsetToString(mixed): string` (guardia su scalari/`Stringable`, altrimenti `ProgrammerException`) usato in `offsetExists()`.
  - La mappa dei metodi callable è passata da `static` locale (tipata `mixed`) a proprietà statica tipizzata `array<string, bool>` `$methods`; i metodi magici `__get()`, `__isset()`, `__set()`, `__unset()` ora dichiarano il parametro `string $name`.
  - `getName()` e `getNamespace()` usano `?? ''` (le proprietà `DOMNode::$localName` e `$namespaceURI` sono `?string`).
  - Il ramo `DOMElement` del costruttore assegna `$xml = TRUE`; prima di `dom_import_simplexml()` è aggiunta la guardia `!$xml instanceof SimpleXMLElement` → `ProgrammerException` (stato impossibile, perché in quel caso `$this->__dom` è già valorizzato).
  - `fixEntitiesEncoding()`: `preg_replace(...) ?? $xml` sulla rimozione del BOM e sulla riscrittura della dichiarazione di encoding.
  - `query()`: docblock `@return DOMNodeList<DOMNameSpaceNode|DOMNode>` (è il tipo reale del ritorno di `DOMXPath::query`); `ownerDocument` verificato prima di `new DOMXPath()`.
  - `toXML()`: riscritto con guardie esplicite su `ownerDocument` (`ProgrammerException` se nullo) e su `saveXML()` (se `FALSE` → `ProgrammerException`), con il `parentNode` normalizzato in una variabile.
  - `offsetGet()`: il nodo restituito da `item(0)` è verificato non nullo prima di leggere `nodeValue`.
  - `xpath()`: docblock `@return array<int|string, mixed>|string|XML|null`; il ramo `$first_only` gestisce `current($output)` con `=== FALSE ? NULL : $first`.
- **Motivazione**: chiudere l'hotspot `XML` (42) senza tecniche di silenziamento (nessun `@phpstan-ignore`, baseline, `assert()` o cast puramente soppressivi). Le guardie `dom()`/`offsetToString()` e i `ProgrammerException` esplicitano condizioni altrimenti impossibili; i `?? $...` coprono i reali ritorni `null` di `preg_replace`.
- **Impatto**: `basso`. Comportamento pubblico invariato per gli input validi. `__get/__isset/__set/__unset` ora richiedono un nome `string` (come da firma canonica dei metodi magici PHP); `getNamespace()`/`getName()` ritornano `''` invece di `null` dove prima il tipo dichiarato `string` avrebbe prodotto un `TypeError`; `toXML()` solleva `ProgrammerException` in caso di serializzazione fallita invece di restituire `false`.
- **Verifica**: `php -l XML.php` pulito; `vendor/bin/phpstan analyse XML.php --no-progress --error-format=raw` → 0 errori; `vendor/bin/phpstan analyse` → 291 errori totali (da 333, −42, nessun nuovo errore); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota. Il tipo `@param File|string|DOMElement` documenta l'uso interno reale; `boolean` sul secondo argomento documenta l'overload storico.
- **Prossimo**: coda hotspot File 37, ORMDate 36, Cryptography 30, Timestamp 27, URL/Time/Date 25.


### [2026-10-10 21:24] - Fase PHPStan: File.php a 0 errori (31 file a zero)

- **File**: `File.php` (+ tipi `TraceEntry` condivisi in `Filesystem.php`)
- **Intervallo righe**: `File.php` 1-1425; `Filesystem.php` docblock di classe, `$deleted_map`, `hookDeletedMap()`, `updateDeletedMap()`
- **Tipo**: tipizzazione + refactor + bugfix
- **Descrizione**:
  - Aggiunto `@phpstan-type TraceEntry array{file?: string, line?: int, class?: string, type?: string, function?: string, args?: list<mixed>}` e `@implements Iterator<int, string|false>` sull'intestazione della classe.
  - Proprietà: `$current_line` → `@var false|string` (inizializzata `FALSE`, non più `NULL`); `$deleted` → `@var list<TraceEntry>|null`; `$file_handle` → `@var resource|null` (rimosso il `false` impossibile, il `NULL` iniziale torna valido).
  - Nuovi helper privati: `copyPermissions()` (guarda il `fileperms()` `false` con `ProgrammerException`, usato da `__clone()` e `duplicate()`) e `stringify()` (scalare/`Stringable`/`null` → stringa, altrimenti `ProgrammerException`, usato da `append()`).
  - `determineMimeType()`: guardie su `fopen()`/`fread()`.
  - Corretto l'uso della variabile non definita `$_4_4` in `determineMimeTypeByContents()` (aggiunta `$_4_4 = substr($content, 4, 4);`), che rendeva irraggiungibile il riconoscimento dei QuickTime `moov`/`mdat`.
  - Costruttore: `realpath()` catturato e verificato (`ProgrammerException` se `FALSE`) prima di `hookFilenameMap()`/`hookDeletedMap()`.
  - `__get()`: parametro `string $method` e ritorno `array{0: $this, 1: string}`.
  - `count()` e `next()`: guardie su `fopen()`/`fread()`.
  - `current()`: tipo di ritorno corretto da `array` a `string|false` (il valore reale è la riga corrente).
  - `delete()`: ritorno nativo `void` (era `mixed` con docblock `@return void`), rimossi i `return` con valore.
  - `getMTime()`: `filemtime()` verificato; `getSize()`: `Filesystem::formatFilesize((int) $size, ...)`.
  - `output()`: `$filename` normalizzato a stringa (scalare) con `ProgrammerException` per tipi non validi prima dell'header.
  - `read()`: `file_get_contents()` verificato; `rename()`: `realpath($info['dirname'])` verificato prima di `Directory::makeCanonical()`.
  - `rewind()`: guardia `is_resource()`.
  - `Filesystem.php`: `$deleted_map` tipizzato `array<string, list<TraceEntry>|null>`, `hookDeletedMap()` ritorna `list<TraceEntry>|null`, `updateDeletedMap()` accetta `list<TraceEntry>|null` (condivisione precisa del tipo di backtrace con `Core::backtrace()`).
- **Motivazione**: chiudere l'hotspot `File` (37) senza tecniche di silenziamento (nessun `@phpstan-ignore`, baseline, `assert()` o cast puramente soppressivi). Le guardie esplicitano i reali ritorni `false`/`null` delle funzioni PHP; `ProgrammerException` copre gli stati impossibili.
- **Impatto**: `basso`. Comportamento pubblico invariato per gli input validi. `File::delete()` ora è `void` (come da docblock, già ritornava `null`/niente); `File::current()` ritorna la riga (stringa) invece del tipo `array` dichiarato per errore; il riconoscimento dei file QuickTime `moov`/`mdat` ora funziona.
- **Verifica**: `php -l File.php` e `php -l Filesystem.php` puliti; `vendor/bin/phpstan analyse File.php Filesystem.php --no-progress --error-format=raw` → 0 errori; `vendor/bin/phpstan analyse` → 254 errori totali (da 291, −37, nessun nuovo errore, `Filesystem.php` resta a zero); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota. `File::delete()` con tipo `void` è coerente con l'uso interno (nessun chiamante usa il valore di ritorno).
- **Prossimo**: coda hotspot ORMDate 36, Cryptography 30, Timestamp 27, Time/Date 26, URL/Authorization 25.

### [2026-10-10 21:37] - Fase PHPStan: ORMDate.php a 0 errori (32 file a zero)

- **File**: `ORMDate.php`
- **Intervallo righe**: `1-590`
- **Tipo**: tipizzazione + refactor + bugfix
- **Descrizione**:
  - `compose()`: normalizzati gli argomenti variadici con `array_map` (non-scalari → `get_debug_type()`) prima di `vsprintf()`, come già fatto in `Database.php`/`Grammar.php`.
  - `configureTimezoneColumn()`: corretto il bug della variabile non definita `$data_type` → `$timestamp_data_type` nel messaggio di eccezione.
  - `inspect()`: docblock `@param array<array-key, mixed> &$metadata` (convenzione condivisa con `ORMColumn`/`ORMMoney`/`ORMValidation`).
  - Tutti i parametri by-ref dei callback ORM (`&$values`, `&$old_values`, `&$related_records`, `&$cache`, `&$validation_messages`) e `$parameters` tipizzati come `array<array-key, mixed>` (richiesto per il passaggio a `ActiveRecord::assign()`/`hasOld()`).
  - `objectifyTimestampWithTimezone()`: riscritto il guard di ingresso con narrowing esplicito (oggetto non-`Stringable` → return; float → stringa; non string/int → return) e normalizzazione del fuso orario a `string|null` prima di `new Timestamp()`; rimosso il ramo `instanceof Timestamp` diventato ridondante (già coperto da `Stringable`).
  - `validateTimezoneColumns()`: il valore del fuso orario è normalizzato/verificato con `is_string()` prima di `Timestamp::isValidTimezone()`.
- **Motivazione**: chiudere l'hotspot `ORMDate` (36) senza tecniche di silenziamento; le guardie esplicitano i ritorni reali delle funzioni e gli stati impossibili con return anticipato.
- **Impatto**: `basso`. Comportamento pubblico invariato per input validi; corretto il messaggio di eccezione (variabile non definita) e resa robusta la conversione timestamp/fuso orario.
- **Verifica**: `php -l ORMDate.php` pulito; `vendor/bin/phpstan analyse ORMDate.php --no-progress --error-format=raw` → 0 errori; `vendor/bin/phpstan analyse` → 218 errori totali (da 254, −36, nessun nuovo errore); `vendor/bin/phpunit --testsuite Unit` → OK (48 test, 74 assertion).
- **Issue/Rischio**: nessuna regressione nota.
- **Prossimo**: coda hotspot Cryptography 30, Timestamp 27, URL 25, Time 25, Date 25, Authorization 24.
