<?php
// index.php - Enhanced login page
require_once __DIR__ . '/data/config.php';
require_once __DIR__ . '/data/db.php';
require_once __DIR__ . '/data/auth.php';
require_once __DIR__ . '/data/security.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    $redirectUrl = Auth::getRedirectUrl($user['role_id']);
    header("Location: $redirectUrl");
    exit;
}

$message = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting check
    if (!Security::checkRateLimit('login', 5, 900)) {
        $message = 'Too many login attempts. Please try again in 15 minutes.';
    } else {
        // Verify CSRF token
        $csrfToken = getPost('csrf_token');
        if (!Security::verifyCSRFToken($csrfToken)) {
            $message = 'Invalid security token. Please refresh the page and try again.';
        } else {
            $username = getPost('username');
            $password = $_POST['password'] ?? ''; // Don't sanitize password
            
            // Validate input
            $errors = Security::validateInput([
                'username' => $username,
                'password' => $password
            ], [
                'username' => 'required|min:3|max:50',
                'password' => 'required|min:6'
            ]);
            
            if (!empty($errors)) {
                $message = 'Please check your input and try again.';
            } else {
                // Attempt login
                $result = Auth::login($username, $password);
                if ($result['success']) {
                    header("Location: " . $result['redirect']);
                    exit;
                } else {
                    $message = $result['message'];
                    Security::logSecurityEvent('failed_login', ['username' => $username]);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --light-blue: #3b82f6;
            --accent-yellow: #fbbf24;
            --light-yellow: #fef3c7;
            --dark-blue: #1e40af;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --white: #ffffff;
            --light-gray: #f8fafc;
            --border-gray: #e5e7eb;
            --success: #10b981;
            --error: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 50%, var(--light-blue) 100%);
            min-height: 100vh;
            display: flex;
            justify-content: space-between; /* left = login, right = info */
            align-items: center; /* vertically aligned */
            padding: 2rem;
            position: relative;
            overflow: auto;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .login-container {
            position: 0 0 auto;
            z-index: 1;
            width: 100%;
            max-width: 450px;
            flex: 1;
            height: 100%; 
        }

        .login-card {
            background: var(--white);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            
            color: var(--white);
            width: 80px;
            height: 80px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            box-shadow: 0 10px 25px rgba(30, 58, 138, 0.3);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
        }

        .logo-subtitle {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid var(--border-gray);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-yellow);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
            transform: translateY(-1px);
        }

        .form-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.1rem;
            pointer-events: none;
            transition: color 0.3s ease;
        }

        .form-input:focus + .form-icon {
            color: var(--accent-yellow);
        }

        .btn-login {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            animation: slideDown 0.4s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid var(--error);
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .demo-accounts {
            margin-top: 2rem;
            padding: 1.5rem;
            background: var(--light-gray);
            border-radius: 12px;
            border-left: 4px solid var(--accent-yellow);
        }

        .demo-accounts h4 {
            color: var(--primary-blue);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .demo-account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-gray);
        }

        .demo-account:last-child {
            border-bottom: none;
        }

        .demo-role {
            font-weight: 600;
            color: var(--text-dark);
        }

        .demo-credentials {
            font-family: monospace;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--accent-yellow);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--primary-blue);
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 2rem;
                margin: 1rem;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
            
            .demo-accounts {
                font-size: 0.9rem;
            }
        }
        
        .college-info {
            flex: 1; /* push to the right */
            display: flex;
            flex-direction: column;
            align-items: flex-end; /* right align text */
            justify-content: center;
            color: var(--white);
            padding-right: 3rem;
            animation: fadeIn 1.5s ease-out;
            position: right;
        }

        .college-info h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            line-height: 1.2;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.3);
            position: right;
        }

        .college-info p {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--light-yellow);
            text-shadow: 1px 1px 6px rgba(0,0,0,0.3);
            position: right;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* On mobile: stack vertically */
        @media (max-width: 768px) {
            body {
                flex-direction: column;
                justify-content: flex-start;
                align-items: center;
            }
            .college-info {
                align-items: center;
                padding-right: 0;
                margin-top: 2rem;
                text-align: center;
            }
        }

    </style>
</head>
<body>
    <div class="college-info">
        <h1>Golden Link College Foundation Inc.</h1>
        <p>"Be The Best That You Can Possibly Be!"</p>
    </div>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <div class="logo-icon">
                    <img src="/AIMS_ver1/shared/GLC_LOGO.png" alt="GLC Logo" style="height:80px; width:auto; border-radius:8px;">
                </div>
                <div class="logo-text">GLC AIMS</div>
                <div class="logo-subtitle">Academic Information Management System</div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>">
                    <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['msg'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_GET['msg']) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" id="loginForm">
                <?= csrfTokenInput() ?>
                
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="form-input-wrapper">
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-input" 
                               placeholder="Enter your username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required
                               autocomplete="username">
                        <i class="fas fa-user form-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="form-input-wrapper">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="Enter your password"
                               required
                               autocomplete="current-password">
                        <i class="fas fa-lock form-icon"></i>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>

            <div class="demo-accounts">
                <h4>
                    <i class="fas fa-info-circle"></i>
                    Demo Accounts
                </h4>
                <div class="demo-account">
                    <span class="demo-role">Super Admin</span>
                    <span class="demo-credentials">admin / password123</span>
                </div>
                <div class="demo-account">
                    <span class="demo-role">Registrar</span>
                    <span class="demo-credentials">registrar / password123</span>
                </div>
                <div class="demo-account">
                    <span class="demo-role">SAO</span>
                    <span class="demo-credentials">sao / password123</span>
                </div>
                <div class="demo-account">
                    <span class="demo-role">Student</span>
                    <span class="demo-credentials">student1 / password123</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span class="loading"></span> Signing In...';
        });

        // Focus on username field when page loads
        document.getElementById('username').focus();
    </script>
</body>
</html>