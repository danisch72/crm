<?php
/**
 * LOGIN.PHP - Pagina Login Sistema Autenticazione
 * CRM Re.De Consulting
 * 
 * Form login con design Datev Koinos
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

$error = '';
$email = '';

// Gestione form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verifica CSRF
    if (!$auth->verifyCSRFToken($csrf_token)) {
        $error = 'Token di sicurezza non valido. Ricarica la pagina.';
    } else {
        // Tenta login
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            // Redirect a dashboard
            header('Location: ' . DASHBOARD_URL);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Genera nuovo token CSRF
$csrf_token = $auth->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CRM Re.De Consulting</title>
    
    <style>
        /* Reset e base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7f9;
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Container login */
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        
        /* Card login */
        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        
        /* Header */
        .login-header {
            background: #007849; /* Verde Datev Koinos */
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Form */
        .login-form {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            font-size: 16px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            background: #f9fafb;
            transition: all 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #007849;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 120, 73, 0.1);
        }
        
        .form-input.error {
            border-color: #dc2626;
        }
        
        /* Checkbox remember */
        .form-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .form-checkbox input {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            cursor: pointer;
        }
        
        .form-checkbox label {
            font-size: 14px;
            color: #6b7280;
            cursor: pointer;
        }
        
        /* Button */
        .btn-login {
            width: 100%;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            background: #007849;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-login:hover {
            background: #005a37;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 120, 73, 0.2);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        /* Footer */
        .login-footer {
            text-align: center;
            padding: 20px 30px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        
        .login-footer p {
            font-size: 13px;
            color: #6b7280;
        }
        
        /* Logo */
        .logo {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 12px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: #007849;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                box-shadow: none;
                border-radius: 0;
            }
            
            .login-container {
                padding: 0;
            }
        }
        
        /* Loading state */
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s ease-in-out infinite;
            margin-left: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="logo">RC</div>
                <h1>CRM Re.De Consulting</h1>
                <p>Accedi al sistema di gestione dello studio</p>
            </div>
            
            <!-- Form -->
            <form class="login-form" method="POST" id="loginForm">
                <!-- Alert errori -->
                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input <?= $error ? 'error' : '' ?>"
                        value="<?= htmlspecialchars($email) ?>"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="nome@studio.it"
                    >
                </div>
                
                <!-- Password -->
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input <?= $error ? 'error' : '' ?>"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    >
                </div>
                
                <!-- Remember me -->
                <div class="form-checkbox">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember">Mantieni l'accesso</label>
                </div>
                
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <!-- Submit -->
                <button type="submit" class="btn-login" id="submitBtn">
                    Accedi
                </button>
            </form>
            
            <!-- Footer -->
            <div class="login-footer">
                <p>&copy; <?= date('Y') ?> Re.De Consulting - Tutti i diritti riservati</p>
            </div>
        </div>
    </div>
    
    <script>
        // Form handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = 'Accesso in corso<span class="spinner"></span>';
        });
        
        // Auto-focus first empty field
        document.addEventListener('DOMContentLoaded', function() {
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            
            if (!email.value) {
                email.focus();
            } else {
                password.focus();
            }
        });
    </script>
</body>
</html>