<?php
session_start();
require_once __DIR__ . '/../classes/DB.php';
require_once __DIR__ . '/../classes/OrderManager.php';

class Admin {
    private $conn;

    public function __construct() {
        $db = new DB();
        $this->conn = $db->getConnection();
    }

    public function register($name, $email, $phone, $password) {
        // Check if exists
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        if ($stmt->fetch()) {
            return "⚠️ Email or phone already exists.";
        }

        // Insert new admin
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO users (name, email, phone, password, is_admin, created_at) VALUES (?, ?, ?, ?, 2, NOW())");
        $stmt->execute([$name, $email, $phone, $hashedPassword]);

        return "✓ Admin registered successfully.";
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = new Admin();
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($password !== $confirm) {
        $message = "⚠️ Passwords do not match.";
    } else {
        $message = $admin->register($name, $email, $phone, $password);
    }
}

$orderManager = new OrderManager();
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Admin</title>
    <style>
        :root {
            --primary-color: #A5D6A7;
            --primary-light: #E8F5E9;
            --primary-dark: #81C784;
            --text-color: #333;
            --border-color: #e0e0e0;
            --warning-color: #FFB74D;
            --success-color: #66BB6A;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #fafafa;
            margin: 0;
            padding: 20px;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .breadcrumb {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .breadcrumb a {
            color: var(--primary-dark);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        h2 {
            color: var(--primary-dark);
            margin: 0;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #E8F5E9;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .alert-warning {
            background-color: var(--primary-light);
            color: var(--warning-color);
            border-left: 4px solid var(--warning-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .required:after {
            content: " *";
            color: var(--warning-color);
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 16px;
            transition: border 0.3s;
            box-sizing: border-box;
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(165, 214, 167, 0.2);
        }
        
        .feedback {
            font-size: 14px;
            margin-top: 5px;
            display: block;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
            text-decoration: none;
            font-size: 16px;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
        }
        
        .btn svg {
            margin-right: 8px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            margin-top: 20px;
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }
        
        .strength-bar {
            height: 4px;
            flex: 1;
            background: #eee;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .strength-bar-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }

        /* Responsive Media Queries */
        @media screen and (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 15px auto;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            h2 {
                font-size: 22px;
            }

            input[type="text"],
            input[type="email"],
            input[type="tel"],
            input[type="password"] {
                font-size: 15px;
                padding: 10px 12px;
            }

            .btn, .back-link {
                font-size: 15px;
                padding: 10px 16px;
            }

            .feedback {
                font-size: 13px;
            }
        }

        @media screen and (max-width: 480px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
            }

            h2 {
                font-size: 20px;
            }

            .breadcrumb {
                font-size: 13px;
            }

            .alert {
                padding: 10px 12px;
                font-size: 14px;
            }

            .form-group {
                margin-bottom: 15px;
            }

            label {
                font-size: 14px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .back-link {
                width: 100%;
                justify-content: center;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="#">Admin Management</a> / <a href="#">Dashboard</a> / <a href="view_admin.php">Admins</a> / Register
            </div>
            <h2>Register New Admin</h2>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?= strpos($message, '✓') === 0 ? 'alert-success' : 'alert-warning' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <form method="post" id="adminForm" novalidate>
            <div class="form-group">
                <label class="required">Full Name</label>
                <input type="text" name="name" required>
            </div>
            
            <div class="form-group">
                <label class="required">Email Address</label>
                <input type="email" name="email" id="email" required>
                <span id="email-feedback" class="feedback"></span>
            </div>
            
            <div class="form-group">
                <label class="required">Phone Number</label>
                <input type="tel" name="phone" id="phone" required pattern="[0-9]{10,15}" placeholder="Digits only, 10-15 characters">
                <span id="phone-feedback" class="feedback"></span>
            </div>
            
            <div class="form-group">
                <label class="required">Password</label>
                <input type="password" name="password" id="password" required minlength="6">
                <div class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-bar-fill" id="strength-bar"></div>
                    </div>
                </div>
                <span id="password-strength" class="feedback"></span>
            </div>
            
            <div class="form-group">
                <label class="required">Confirm Password</label>
                <input type="password" name="confirm" id="confirm" required minlength="6">
                <span id="confirm-feedback" class="feedback"></span>
            </div>
            
            <button type="submit" class="btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                Register Admin
            </button>
            
            <a href="view_admin.php" class="back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Admin List
            </a>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const submitBtn = document.querySelector('button[type="submit"]');
        const emailInput = document.getElementById('email');
        const emailFeedback = document.getElementById('email-feedback');
        const passwordInput = document.getElementById('password');
        const passwordStrengthText = document.getElementById('password-strength');
        const strengthBar = document.getElementById('strength-bar');
        const confirmInput = document.getElementById('confirm');
        const confirmFeedback = document.getElementById('confirm-feedback');
        const phoneInput = document.getElementById('phone');
        const phoneFeedback = document.getElementById('phone-feedback');

        let emailValid = false;
        let passwordStrong = false;
        let passwordsMatch = false;
        let phoneValid = false;

        function toggleSubmit() {
            submitBtn.disabled = !(emailValid && passwordStrong && passwordsMatch && phoneValid);
        }

        // Disable submit initially
        submitBtn.disabled = true;

        // Email AJAX validation
        emailInput.addEventListener('input', function () {
            const email = this.value.trim();

            if (email.length === 0) {
                emailFeedback.textContent = '';
                emailValid = false;
                toggleSubmit();
                return;
            }

            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                emailFeedback.textContent = '⚠️ Invalid email format';
                emailFeedback.style.color = 'var(--warning-color)';
                emailValid = false;
                toggleSubmit();
                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'check_email.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

            xhr.onload = function () {
                if (xhr.status === 200) {
                    emailFeedback.textContent = xhr.responseText;
                    const isAvailable = xhr.responseText.includes('✓');
                    emailFeedback.style.color = isAvailable ? 'var(--success-color)' : 'var(--warning-color)';
                    emailValid = isAvailable;
                    toggleSubmit();
                }
            };

            xhr.send('email=' + encodeURIComponent(email));
        });

        // Password strength check
        passwordInput.addEventListener('input', function () {
            const password = this.value;

            let strength = 0;
            if (password.length >= 6) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            // Update strength bar
            const strengthPercent = Math.min(100, strength * 25);
            strengthBar.style.width = strengthPercent + '%';
            
            if (password.length === 0) {
                strengthBar.style.backgroundColor = '';
                passwordStrengthText.textContent = '';
                passwordStrong = false;
            } else if (strength <= 1) {
                strengthBar.style.backgroundColor = "var(--warning-color)";
                passwordStrengthText.textContent = "⚠️ Weak password";
                passwordStrengthText.style.color = "var(--warning-color)";
                passwordStrong = false;
            } else if (strength === 2 || strength === 3) {
                strengthBar.style.backgroundColor = "var(--primary-color)";
                passwordStrengthText.textContent = "✓ Good password";
                passwordStrengthText.style.color = "var(--primary-color)";
                passwordStrong = true;
            } else {
                strengthBar.style.backgroundColor = "var(--success-color)";
                passwordStrengthText.textContent = "✓ Strong password";
                passwordStrengthText.style.color = "var(--success-color)";
                passwordStrong = true;
            }
            checkMatch();
            toggleSubmit();
        });

        // Confirm password match check
        confirmInput.addEventListener('input', function() {
            checkMatch();
            toggleSubmit();
        });

        function checkMatch() {
            const password = passwordInput.value;
            const confirm  = confirmInput.value;

            if (!confirm) {
                confirmFeedback.textContent = '';
                passwordsMatch = false;
                return;
            }

            if (password === confirm) {
                confirmFeedback.textContent = '✓ Passwords match';
                confirmFeedback.style.color = 'var(--success-color)';
                passwordsMatch = true;
            } else {
                confirmFeedback.textContent = '⚠️ Passwords do not match';
                confirmFeedback.style.color = 'var(--warning-color)';
                passwordsMatch = false;
            }
        }

        // Phone validation
        phoneInput.addEventListener('input', function () {
            const phone = this.value.trim();

            if (phone.length === 0) {
                phoneFeedback.textContent = '';
                phoneValid = false;
                toggleSubmit();
                return;
            }

            // Basic format check first
            const phoneRegex = /^[0-9]{10,15}$/;
            if (!phoneRegex.test(phone)) {
                phoneFeedback.textContent = '⚠️ Invalid phone format (10-15 digits only)';
                phoneFeedback.style.color = 'var(--warning-color)';
                phoneValid = false;
                toggleSubmit();
                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'check_phone.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

            xhr.onload = function () {
                if (xhr.status === 200) {
                    phoneFeedback.textContent = xhr.responseText;
                    const isAvailable = xhr.responseText.includes('✓');
                    phoneFeedback.style.color = isAvailable ? 'var(--success-color)' : 'var(--warning-color)';
                    phoneValid = isAvailable;
                    toggleSubmit();
                }
            };

            xhr.send('phone=' + encodeURIComponent(phone));
        });

        // Form submit validation
        document.getElementById('adminForm').addEventListener('submit', function(e) {
            if (!(emailValid && passwordStrong && passwordsMatch && phoneValid)) {
                e.preventDefault();
                alert('Please fix the form errors before submitting.');
            }
        });
    });
    </script>
</body>
</html>