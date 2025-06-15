<?php
/**
 * LOGIN FORM - Sistema Autenticazione
 * CRM Re.De Consulting
 * 
 * Form login minimalista senza tracking ore
 */

define('AUTH_INIT', true);
require_once 'config.php';
require_once 'Auth.php';

$auth = Auth::getInstance();

// Se già autenticato, redirect a dashboard
if ($auth->isAuthenticated()) {
    header('Location: ' . DASHBOARD_URL);
    exit;
}

// Genera token CSRF
$csrfToken = $auth->generateCSRFToken();

// Gestione POST login
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!isset($_POST['csrf_token']) || !$auth->verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Token di sicurezza non valido';
    } else {
        // Tenta login
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            // Login riuscito - redirect
            header('Location: ' . DASHBOARD_URL);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Messaggio da logout
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $success = 'Disconnessione avvenuta con successo';
}

// Messaggio sessione scaduta
if (isset($_GET['expired']) && $_GET['expired'] == '1') {
    $error = 'Sessione scaduta, effettua nuovamente il login';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CRM Re.De Consulting</title>
    <style>
        /* Reset e variabili */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #007849;
            --primary-dark: #005a37;
            --secondary: #86B817;
            --danger: #E60012;
            --success: #28a745;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
            --shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }
        
        .login-header {
            background: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .login-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .login-header p {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-icon {
            font-size: 1.25rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 120, 73, 0.1);
        }
        
        .form-input::placeholder {
            color: var(--gray-400);
        }
        
        .btn {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .login-footer {
            padding: 1.5rem 2rem;
            background: var(--gray-50);
            text-align: center;
            font-size: 0.875rem;
            color: var(--gray-500);
        }
        
        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff40;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        .btn-primary:disabled .spinner {
            display: inline-block;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">R</div>
            <h1>CRM Re.De Consulting</h1>
            <p>Accedi al sistema di gestione</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠️</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="nome@esempio.com"
                        required
                        autofocus
                        autocomplete="email"
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                </div>
                
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <span>Accedi</span>
                    <span class="spinner"></span>
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            &copy; <?= date('Y') ?> Re.De Consulting - Tutti i diritti riservati
        </div>
    </div>
    
    <script>
        // Form submit con loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.querySelector('span:first-child').textContent = 'Accesso in corso...';
        });
        
        // Auto-focus su campo con errore
        <?php if ($error): ?>
        document.getElementById('email').focus();
        document.getElementById('email').select();
        <?php endif; ?>
    </script>
</body>
</html>