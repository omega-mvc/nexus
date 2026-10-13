## AGENTS.md

### Regole operative (RESTRICTIVE)

L'agente **deve** rispettare queste regole in questa repository.

#### 1) Test, analisi e lint
- **PHPStan**: **BANNATO** in modo automatico a ogni singolo `git mv` o spostamento di file. Può essere eseguito **SOLO** se l'utente lo richiede esplicitamente, o alla conclusione di un intero blocco di spostamenti concordato.
- **PHPUnit**: **BANNATO** in modo automatico. Può essere eseguito **SOLO** se l'utente lo richiede esplicitamente.
- **PHP_CodeSniffer (phpcs)**: **BANNATO** in modo automatico. È un **passaggio FINALE** da eseguire esclusivamente quando l'utente lo richiede esplicitamente.

#### 2) Spostamenti (packaging)
- Per spostamenti puri (`git mv`): eseguire **SOLO** `git mv` → `composer dump-autoload` → `php -l`.
- Non modificare mai il contenuto dei file durante un `git mv` (rename 100%).

#### 3) Logging
- Registrare ogni fase in `MODIFICHE.md` **solo** se concordato, o evitare di scrivere entry incomplete. Non inventare stato.

#### 4) Verifica minima
- Dopo un move valido, controllare la risoluzione via `ReflectionClass` è sufficiente. Non lanciare tool lenti per "sicurezza".

#### 5) Decisioni
- Non assumere, chiedi se ambiguo. Rispetta le scelte dell'utente.
