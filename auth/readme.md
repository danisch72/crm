# Sistema di Autenticazione CRM Re.De Consulting

## 🔐 Panoramica

Sistema di autenticazione modulare e isolato per il CRM Re.De Consulting.
Gestisce esclusivamente login, logout e verifica stato autenticazione.

## ⚠️ SICUREZZA IMPORTANTE

Il sistema usa il file `.env` nella root del CRM per le credenziali.
**MAI mettere credenziali in chiaro nei file PHP!**

Assicurarsi che:
1. Il file `.env` esista in `/crm/.env`
2. Il file `.env` NON sia accessibile via web (testare!)
3. Il file `.env` abbia permessi 600 (solo proprietario può leggere)
4. Il file `.htaccess` nella root `/crm/` blocchi l'accesso a .env

## 📁 Struttura File

```
/crm/
├── .env                   ← File configurazione (PROTEGGERE!)
├── .htaccess             ← Protezione .env e sicurezza
├── auth/                 ← Directory sistema auth
│   ├── index.php         # Entry point (redirect a login)
│   ├── config.php        # Configurazione sistema (legge da .env)
│   ├── Auth.php          # Classe autenticazione
│   ├── login.php         # Form e processo login
│   ├── logout.php        # Gestione logout
│   ├── check.php         # API verifica auth (AJAX)
│   ├── install.sql       # Script creazione tabelle
│   ├── .htaccess         # Protezione directory auth
│   ├── .env.example      # Esempio configurazione
│   └── README.md         # Questo file
└── index.php             ← Entry point CRM principale
```

## 🚀 Installazione

1. **Verificare che `.env` esista** in `/crm/.env` con le credenziali database
2. **Proteggere `.env`** con permessi 600: `chmod 600 /crm/.env`
3. **Caricare i file** nella directory `/crm/auth/` del sito
4. **Eseguire `install.sql`** per creare le tabelle necessarie
5. **Verificare permessi**:
   - File `.env`: 600 (IMPORTANTE!)
   - Directory `/crm/auth/`: 755
   - File PHP: 644
   - Directory `/crm/auth/logs/`: 755 (creare se necessaria)
6. **Verificare .htaccess** nella root `/crm/` per protezione .env

## 🔧 Utilizzo

### Login
```php
// Redirect manuale
header('Location: /crm/auth/login.php');

// O usa costante
header('Location: ' . LOGIN_URL);
```

### Logout
```php
// Redirect a logout
header('Location: /crm/auth/logout.php');
```

### Verifica Autenticazione
```php
// In PHP
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/auth/Auth.php';
$auth = Auth::getInstance();

if ($auth->isAuthenticated()) {
    $user = $auth->getCurrentUser();
    // Utente autenticato
}

// Via AJAX
fetch('/crm/auth/check.php')
    .then(response => response.json())
    .then(data => {
        if (data.authenticated) {
            console.log('User:', data.user);
        }
    });
```

## 🔑 Funzioni Disponibili

### Classe Auth

- `login($email, $password)` - Autentica utente
- `logout()` - Disconnetti utente
- `isAuthenticated()` - Verifica se autenticato
- `getCurrentUser()` - Ottieni info utente corrente
- `isAdmin()` - Verifica se utente è admin
- `generateCSRFToken()` - Genera token CSRF
- `verifyCSRFToken($token)` - Verifica token CSRF

### Helper Functions (Compatibilità)

- `isAuthenticated()` - Verifica autenticazione globale
- `getCurrentUserId()` - ID utente corrente
- `isAdmin()` - Verifica se admin

## 🛡️ Sicurezza

- **Password Hash**: Argon2ID
- **Protezione Brute Force**: Max 5 tentativi, lockout 15 minuti
- **CSRF Protection**: Token su tutti i form
- **Sessioni Sicure**: HTTPS only, HTTPOnly cookies
- **Audit Trail**: Log di tutti gli accessi

## 📊 Tabelle Database

### login_attempts
Traccia tentativi di login falliti per prevenire brute force.

### auth_log
Log completo di tutte le attività di autenticazione.

## ⚙️ Configurazione

Il sistema legge la configurazione dal file `.env` nella root del CRM.

**Parametri richiesti in .env:**
```ini
# Database
DB_HOST="your_host"
DB_NAME="your_database"
DB_USERNAME="your_user"
DB_PASSWORD="your_password"
DB_CHARSET="utf8mb4"

# Security
APP_SECRET_KEY="your_32_char_secret_key"
```

**Parametri configurabili in config.php:**
- `AUTH_SESSION_LIFETIME`: Durata sessione (default: 3600 secondi)
- `AUTH_LOGIN_ATTEMPTS`: Max tentativi login (default: 5)
- `AUTH_LOCKOUT_TIME`: Tempo lockout (default: 900 secondi)

## 🔄 Migrazione dal Vecchio Sistema

Il nuovo sistema mantiene compatibilità con le variabili di sessione legacy:
- `$_SESSION['operatore_id']`
- `$_SESSION['is_amministratore']`

Queste verranno rimosse gradualmente in future versioni.

## 🐛 Troubleshooting

### "Accesso diretto non consentito"
Assicurarsi di definire `AUTH_INIT` o `CRM_INIT` prima di includere i file.

### Loop di redirect
Verificare che le costanti URL in `config.php` siano corrette.

### Sessione non persistente
Controllare configurazione PHP session e cookie domain.

## 📝 Note

- Sistema completamente isolato dalla logica business
- Nessun tracking ore lavoro (gestito altrove)
- Nessuna modalità lavoro (ufficio/smart)
- Solo autenticazione base

---

**Versione**: 1.0  
**Data**: <?= date('Y-m-d') ?>  
**Autore**: Sistema CRM Re.De Consulting