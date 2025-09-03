<?php
// shared/access_denied.php - Access denied page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - GLC AIMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --light-blue: #3b82f6;
            --accent-yellow: #fbbf24;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --white: #ffffff;
            --error: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
        }

        .error-container {
            text-align: center;
            background: var(--white);
            padding: 3rem 2rem;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
        }

        .error-icon {
            font-size: 4rem;
            color: var(--error);
            margin-bottom: 1.5rem;
        }

        .error-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }

        .error-message {
            font-size: 1.1rem;
            color: var(--text-light);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .error-details {
            background: #fef2f2;
            border-left: 4px solid var(--error);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: left;
        }

        .error-details strong {
            color: var(--error);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: var(--white);
        }

        .btn-secondary {
            background: var(--accent-yellow);
            color: var(--primary-blue);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 480px) {
            .error-container {
                padding: 2rem 1.5rem;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        
        <h1 class="error-title">Access Denied</h1>
        
        <p class="error-message">
            You don't have permission to access this page. This area is restricted to authorized users only.
        </p>
        
        <div class="error-details">
            <strong>Current Role:</strong> <?= htmlspecialchars($_SESSION['role_name'] ?? 'Guest') ?><br>
            <strong>Requested Page:</strong> <?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'Unknown') ?><br>
            <strong>Time:</strong> <?= date('Y-m-d H:i:s') ?>
        </div>
        
        <div class="action-buttons">
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Go Back
            </a>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/AIMS_ver1/<?= strtolower(str_replace(' ', '_', $_SESSION['role_name'])) ?>/dashboard.php" class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    My Dashboard
                </a>
            <?php else: ?>
                <a href="/AIMS_ver1/index.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </a>
            <?php endif; ?>
            
            <a href="/AIMS_ver1/logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <script>
        // Log the access denial attempt
        <?php
        if (isset($_SESSION['user_id'])) {
            ActivityLogger::log(
                $_SESSION['user_id'], 
                'ACCESS_DENIED', 
                null, 
                null, 
                null, 
                ['requested_url' => $_SERVER['REQUEST_URI'] ?? 'unknown']
            );
        }
        ?>
        
        // Auto-redirect to appropriate page after 10 seconds
        setTimeout(function() {
            <?php if (isset($_SESSION['user_id'])): ?>
                window.location.href = '/AIMS_ver1/<?= strtolower(str_replace(' ', '_', $_SESSION['role_name'])) ?>/dashboard.php';
            <?php else: ?>
                window.location.href = '/AIMS_ver1/index.php';
            <?php endif; ?>
        }, 10000);
    </script>
</body>
</html>