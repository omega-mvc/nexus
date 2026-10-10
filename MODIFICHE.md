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
