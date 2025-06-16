# 🔗 Sistema Bootstrap CRM Re.De Consulting

## 📋 Panoramica

Il file `bootstrap.php` è il **ponte di comunicazione** tra il sistema di autenticazione isolato e tutti i moduli del CRM.

## 🎯 Principio Fondamentale

**Il sistema auth in `/auth/` NON viene MAI modificato quando si aggiungono o modificano moduli.**

```
auth/           → ISOLATO, mai toccato
bootstrap.php   → PONTE, unico punto di modifica
moduli/*        → Usano solo bootstrap
```

## 📁 Struttura

```
crm/
├── index.php              → Include bootstrap.php
├── dashboard.php          → Include bootstrap.php
├── core/
│   └── bootstrap.php      → PONTE tra auth e moduli
├── auth/                  → Sistema auth ISOLATO
│   ├── login.php
│   ├── logout.php
│   ├── Auth.php
│   └── config.php
└── modules/
    ├── operatori/         → Include bootstrap.php
    └── clienti/           → Include bootstrap.php
```

## 🔧 Come Usare Bootstrap

### In file nella root (`/crm/`)
```php
require_once __DIR__ . '/core/bootstrap.php';
```

### In moduli (`/crm/modules/nome/`)
```php
require_once dirname(dirname(__DIR__)) . '/core/bootstrap.php';
```

### In sottocartelle moduli (`/crm/modules/nome/subfolder/`)
```php
require_once dirname(dirname(dirname(__DIR__))) . '/core/bootstrap.php';
```

## 📝 Funzioni Disponibili

### Autenticazione
- `getCurrentUser()` - Info utente corrente
- `isAuthenticated()` - Verifica se loggato
- `isAdmin()` - Verifica se amministratore

### Navigazione
- `crmUrl($path)` - Genera URL completo
- `crmPath($path)` - Genera percorso file system
- `crmRedirect($path, $params)` - Redirect interno

### Moduli
- `loadModule($name)` - Carica un modulo
- `loadDatabase()` - Carica classe Database
- `loadHelpers()` - Carica funzioni helper

### Utility
- `showError($message)` - Mostra errore uniforme
- `debugLog($data, $label)` - Log debug (solo in dev)

## ✅ Vantaggi

1. **Isolamento Totale**: Sistema auth mai modificato
2. **Punto Unico**: Solo bootstrap.php da modificare
3. **Consistenza**: Stesse funzioni per tutti i moduli
4. **Manutenibilità**: Facile aggiungere/modificare funzionalità

## 🚀 Aggiungere un Nuovo Modulo

1. Crea cartella in `/modules/nome_modulo/`
2. Nel file principale del modulo:
   ```php
   <?php
   // Include bootstrap
   require_once dirname(dirname(__DIR__)) . '/core/bootstrap.php';
   
   // Il modulo è già autenticato e pronto!
   $user = getCurrentUser();
   ```
3. Aggiungi route in `index.php`:
   ```php
   $availableModules = [
       // ...
       'nome_modulo' => '/modules/nome_modulo/',
   ];
   ```

## ⚠️ Note Importanti

- **MAI** modificare file in `/auth/`
- **MAI** includere direttamente `auth/config.php` o `auth/Auth.php`
- **SEMPRE** usare bootstrap.php come ponte
- Se serve una nuova funzione, aggiungerla in bootstrap.php

---

**Versione**: 1.0  
**Ultimo aggiornamento**: <?= date('Y-m-d') ?>