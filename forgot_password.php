<?php
session_start();
require_once "../classes/DB.php";
require_once "../classes/User.php";
require '../vendor/autoload.php'; // Add this at the top if using Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$db = new DB();
$user = new User($db);

$errors = [];
$success = false;
$step = 1;
$password = null;

// Step 1: User submits phone and email to get code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_code'])) {
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    if (empty($phone) || empty($email)) {
        $errors[] = "All fields are required.";
    } else {
        $userData = $user->getByPhone($phone); // <-- Use phone
        if (!$userData) {
            $errors[] = "Phone number not found.";
        } elseif (strtolower($userData['email']) !== strtolower($email)) {
            $errors[] = "Email does not match our records.";
        } else {
            // Generate code
            $code = rand(100000, 999999);
            $_SESSION['reset_code'] = $code;
            $_SESSION['reset_phone'] = $phone;
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_code_time'] = time();
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'francelivia1@gmail.com ';
                $mail->Password   = 'oueh qpfl iatu xnvo';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->setFrom('your_gmail@gmail.com', 'School Portal');
                $mail->addAddress($email);
                $mail->isHTML(false);
                $mail->Subject = 'Your Password Reset Code';
                $mail->Body    = "Your code is: $code";
                $mail->send();
                $step = 2;
            } catch (Exception $e) {
                $errors[] = "Could not send email. Mailer Error: {$mail->ErrorInfo}";
                $step = 1;
            }
        }
    }
}

// Step 2: User submits code and new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $code = trim($_POST['code']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($code) || empty($new_password) || empty($confirm_password)) {
        $errors[] = "All fields are required.";
        $step = 2;
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
        $step = 2;
    } elseif (
        strlen($new_password) < 8 ||
        !preg_match('/[A-Z]/', $new_password) ||
        !preg_match('/[a-z]/', $new_password) ||
        !preg_match('/[0-9]/', $new_password) ||
        !preg_match('/[^a-zA-Z0-9]/', $new_password) // Special character check
    ) {
        $errors[] = "Password must be at least 8 characters long and include uppercase, lowercase, a number, and a special character.";
        $step = 2;
    } elseif (!isset($_SESSION['reset_code']) || trim($code) !== trim((string)$_SESSION['reset_code'])) {
        $errors[] = "Invalid code.";
        $step = 2;
    } else {
        $phone = $_SESSION['reset_phone'];
        if ($user->updatePasswordByPhone($phone, $new_password)) { // <-- Use phone
            $success = true;
            unset($_SESSION['reset_code'], $_SESSION['reset_phone'], $_SESSION['reset_email']);
        } else {
            $errors[] = "Failed to reset password.";
            $step = 2;
        }
    }
}

// Resend code logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_code'])) {
    if (isset($_SESSION['reset_phone'], $_SESSION['reset_email'])) {
        $phone = $_SESSION['reset_phone'];
        $email = $_SESSION['reset_email'];
        $userData = $user->getByPhone($phone); // <-- Use phone
        if ($userData && strtolower($userData['email']) === strtolower($email)) {
            $code = rand(100000, 999999);
            $_SESSION['reset_code'] = $code;
            $_SESSION['reset_code_time'] = time();
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'francelivia1@gmail.com ';
                $mail->Password   = 'oueh qpfl iatu xnvo';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->setFrom('your_gmail@gmail.com', 'School Portal');
                $mail->addAddress($email);
                $mail->isHTML(false);
                $mail->Subject = 'Your Password Reset Code';
                $mail->Body    = "Your code is: $code";
                $mail->send();
                $step = 2;
            } catch (Exception $e) {
                $errors[] = "Could not resend email. Mailer Error: {$mail->ErrorInfo}";
                $step = 2;
            }
        } else {
            $errors[] = "User data not found for resend.";
            $step = 1;
        }
    } else {
        $errors[] = "Session expired. Please start over.";
        $step = 1;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Student Portal</title>
    <style>
        :root {
            --primary-light-green: #e8f5e9;
            --secondary-light-yellow: #fff9c4;
            --accent-green: #81c784;
            --text-dark: #2e7d32;
            --error-red: #d32f2f;
            --success-green: #388e3c;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--primary-light-green);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .reset-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
        }
        
        .reset-header {
            background-color: var(--secondary-light-yellow);
            padding: 30px 20px;
            text-align: center;
            border-bottom: 4px solid var(--accent-green);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
            background-color: var(--accent-green);
            border-radius: 50%;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 28px;
            font-weight: bold;
        }
        
        .reset-header h1 {
            color: var(--text-dark);
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .reset-header p {
            color: #666;
            font-size: 14px;
        }
        
        .reset-form {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--accent-green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(129, 199, 132, 0.2);
        }
        
        .btn {
            background-color: var(--accent-green);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 100%;
        }
        
        .btn:hover {
            background-color: #66bb6a;
        }
        
        .error {
            color: var(--error-red);
            background-color: #ffebee;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .error ul {
            list-style-type: none;
        }
        
        .error li {
            margin-bottom: 5px;
        }
        
        .success {
            color: var(--success-green);
            background-color: #e8f5e9;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 16px;
            text-align: center;
        }
        
        .success a {
            color: var(--success-green);
            font-weight: 600;
            text-decoration: none;
        }
        
        .success a:hover {
            text-decoration: underline;
        }
        
        .reset-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .reset-footer a {
            color: var(--text-dark);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .reset-footer a:hover {
            color: var(--accent-green);
            text-decoration: underline;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: #999;
            font-size: 14px;
        }
        
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #ddd;
        }
        
        .divider::before {
            margin-right: 10px;
        }
        
        .divider::after {
            margin-left: 10px;
        }
        
        /* Password Requirements Styles */
        .password-requirements {
            margin-top: 0.5rem;
            padding: 0.75rem;
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            font-size: 0.75rem;
            display: none;
        }

        .password-requirements ul {
            margin-left: 1.25rem;
        }

        .password-requirements li {
            margin-bottom: 0.25rem;
        }

        .password-requirements li.valid {
            color: var(--success-green);
        }

        .password-requirements li.invalid {
            color: #666;
        }

        .password-requirements li::before {
            content: "•";
            display: inline-block;
            width: 1em;
            margin-left: -1em;
        }
        
        .password-strength {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        
        .valid-feedback {
            color: var(--success-green);
            background-color: #e8f5e9;
        }
        
        .invalid-feedback {
            color: var(--error-red);
            background-color: #ffebee;
        }
        
        .warning-feedback {
            color: #ff9800;
            background-color: #fff3e0;
        }
        
        #resend-section {
            text-align: center;
            margin-top: 12px;
        }
        
        #countdown {
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <div class="logo">SP</div>
            <h1>Reset Password</h1>
            <p>Enter your student ID and new password</p>
        </div>
        
        <div class="reset-form">
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success">
                    Password reset successfully. <a href="login.php">Login now</a>
                </div>
            <?php elseif ($step == 1): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <button type="submit" class="btn" name="send_code">Send Code</button>
                </form>
            <?php elseif ($step == 2): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="code">Verification Code</label>
                        <input type="text" class="form-control" id="code" name="code" required>
                        <div class="password-requirements">Please check your moe email for the verification code</div>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div id="password-requirements" class="password-requirements">
                            <strong>Password Requirements:</strong>
                            <ul>
                                <li id="req-length">At least 8 characters</li>
                                <li id="req-uppercase">At least 1 uppercase letter</li>
                                <li id="req-lowercase">At least 1 lowercase letter</li>
                                <li id="req-number">At least 1 number</li>
                                <li id="req-special">At least 1 special character</li>
                            </ul>
                        </div>
                        <div id="password-strength" class="password-strength"></div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div id="confirm-feedback" class="password-strength"></div>
                    </div>
                    <button type="submit" class="btn" name="reset_password">Reset Password</button>
                </form>
                <?php
                // Show resend option after 2 minutes
                $canResend = false;
                $remaining = 120;
                if (isset($_SESSION['reset_code_time'])) {
                    $elapsed = time() - $_SESSION['reset_code_time'];
                    if ($elapsed >= 120) {
                        $canResend = true;
                        $remaining = 0;
                    } else {
                        $remaining = 120 - $elapsed;
                    }
                }
                ?>
                <div id="resend-section">
                    <div id="countdown" class="password-requirements" <?= $canResend ? 'style="display:none;"' : '' ?>>
                        You can resend the code in <span id="resend-timer"><?= $remaining ?></span> seconds.
                    </div>
                    <form id="resend-form" method="POST" style="display:<?= $canResend ? 'inline' : 'none' ?>;">
                        <button type="submit" class="btn" name="resend_code">Resend Code</button>
                    </form>
                </div>
                <script>
                    // Password requirements and strength checker
                    const passwordInput = document.getElementById('new_password');
                    const passwordRequirements = document.getElementById('password-requirements');
                    const passwordStrengthText = document.getElementById('password-strength');
                    const confirmInput = document.getElementById('confirm_password');
                    const confirmFeedback = document.getElementById('confirm-feedback');
                    
                    // Get all requirement elements
                    const reqLength = document.getElementById('req-length');
                    const reqUppercase = document.getElementById('req-uppercase');
                    const reqLowercase = document.getElementById('req-lowercase');
                    const reqNumber = document.getElementById('req-number');
                    const reqSpecial = document.getElementById('req-special');
                    
                    // Show password requirements when password field is focused
                    passwordInput.addEventListener('focus', function() {
                        passwordRequirements.style.display = 'block';
                    });
                    
                    // Hide password requirements when password field is empty and blurred
                    passwordInput.addEventListener('blur', function() {
                        if (this.value.length === 0) {
                            passwordRequirements.style.display = 'none';
                        }
                    });
                    
                    // Check password strength in real-time
                    passwordInput.addEventListener('input', function() {
                        const password = this.value;
                        
                        // Always show requirements when typing
                        if (password.length > 0) {
                            passwordRequirements.style.display = 'block';
                        } else {
                            passwordRequirements.style.display = 'none';
                            passwordStrengthText.textContent = '';
                            return;
                        }
                        
                        // Check each requirement
                        const hasLength = password.length >= 8;
                        const hasUppercase = /[A-Z]/.test(password);
                        const hasLowercase = /[a-z]/.test(password);
                        const hasNumber = /[0-9]/.test(password);
                        const hasSpecial = /[^a-zA-Z0-9]/.test(password);
                        
                        // Update requirement indicators
                        updateRequirement(reqLength, hasLength);
                        updateRequirement(reqUppercase, hasUppercase);
                        updateRequirement(reqLowercase, hasLowercase);
                        updateRequirement(reqNumber, hasNumber);
                        updateRequirement(reqSpecial, hasSpecial);
                        
                        // Overall strength assessment
                        passwordStrengthText.className = 'password-strength';
                        
                        if (password.length < 8) {
                            passwordStrengthText.textContent = '❌ Password must be at least 8 characters';
                            passwordStrengthText.className += ' invalid-feedback';
                        } else {
                            const strength = [hasUppercase, hasLowercase, hasNumber, hasSpecial].filter(Boolean).length;
                            
                            if (strength === 0) {
                                passwordStrengthText.textContent = '⚠️ Very weak password';
                                passwordStrengthText.className += ' warning-feedback';
                            } else if (strength < 2) {
                                passwordStrengthText.textContent = '⚠️ Weak password';
                                passwordStrengthText.className += ' warning-feedback';
                            } else if (strength < 3) {
                                passwordStrengthText.textContent = '✅ Good password';
                                passwordStrengthText.className += ' valid-feedback';
                            } else if (strength < 4) {
                                passwordStrengthText.textContent = '✅ Strong password';
                                passwordStrengthText.className += ' valid-feedback';
                            } else {
                                passwordStrengthText.textContent = '✅ Very strong password';
                                passwordStrengthText.className += ' valid-feedback';
                            }
                        }
                        
                        // Trigger confirm password check
                        if (confirmInput.value) {
                            confirmInput.dispatchEvent(new Event('input'));
                        }
                    });
                    
                    // Function to update requirement display
                    function updateRequirement(element, isValid) {
                        if (isValid) {
                            element.classList.add('valid');
                            element.classList.remove('invalid');
                        } else {
                            element.classList.add('invalid');
                            element.classList.remove('valid');
                        }
                    }
                    
                    // Confirm password check
                    confirmInput.addEventListener('input', function() {
                        const confirm = this.value;
                        confirmFeedback.className = 'password-strength';
                        
                        if (!confirm) {
                            confirmFeedback.textContent = '';
                            return;
                        }
                        
                        if (confirm !== passwordInput.value) {
                            confirmFeedback.textContent = '❌ Passwords do not match';
                            confirmFeedback.className += ' invalid-feedback';
                        } else {
                            confirmFeedback.textContent = '✅ Passwords match';
                            confirmFeedback.className += ' valid-feedback';
                        }
                    });
                    
                    // Resend code countdown timer
                    let timer = <?= $remaining ?>;
                    const el = document.getElementById('resend-timer');
                    const countdown = document.getElementById('countdown');
                    const resendForm = document.getElementById('resend-form');
                    if (timer > 0) {
                        const interval = setInterval(() => {
                            timer--;
                            if (timer <= 0) {
                                clearInterval(interval);
                                countdown.style.display = 'none';
                                resendForm.style.display = 'inline';
                            } else {
                                el.textContent = timer;
                            }
                        }, 1000);
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>