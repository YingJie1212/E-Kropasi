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
$password = null; // or set a default, or skip password logic

// Step 1: User submits student_id and email to get code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_code'])) {
    $student_id = trim($_POST['student_id']);
    $email = trim($_POST['email']);

    if (empty($student_id) || empty($email)) {
        $errors[] = "All fields are required.";
    } else {
        $userData = $user->getByStudentId($student_id);
        if (!$userData) {
            $errors[] = "Student ID not found.";
        } elseif (strtolower($userData['email']) !== strtolower($email)) {
            $errors[] = "Email does not match our records.";
        } else {
            // Generate code
            $code = rand(100000, 999999);
            $_SESSION['reset_code'] = $code;
            $_SESSION['reset_student_id'] = $student_id;
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_code_time'] = time(); // Add this line
            // TODO: Use a real mailer in production!
            $mail = new PHPMailer(true);
            try {
                //Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; // Your SMTP server
                $mail->SMTPAuth   = true;
                $mail->Username   = 'francelivia1@gmail.com '; // Your email
                $mail->Password   = 'oueh qpfl iatu xnvo';    // Your app password (not your Gmail password)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                //Recipients
                $mail->setFrom('your_gmail@gmail.com', 'School Portal');
                $mail->addAddress($email);

                //Content
                $mail->isHTML(false);
                $mail->Subject = 'Your Password Reset Code';
                $mail->Body    = "Your code is: $code";

                $mail->send();
                // Success, continue as normal
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
        $student_id = $_SESSION['reset_student_id'];
        // Pass the plain password:
        if ($user->updatePassword($student_id, $new_password)) {
            $success = true;
            unset($_SESSION['reset_code'], $_SESSION['reset_student_id'], $_SESSION['reset_email']);
        } else {
            $errors[] = "Failed to reset password.";
            $step = 2;
        }
    }
}

// Resend code logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_code'])) {
    if (isset($_SESSION['reset_student_id'], $_SESSION['reset_email'])) {
        $student_id = $_SESSION['reset_student_id'];
        $email = $_SESSION['reset_email'];
        $userData = $user->getByStudentId($student_id);
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
        
        .password-requirements {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .requirements-list {
            display: none;
            background-color: #f9f9f9;
            border-radius: 6px;
            padding: 10px;
            margin-top: 5px;
            border-left: 3px solid var(--accent-green);
        }
        
        .requirements-list.active {
            display: block;
        }
        
        .requirement {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .requirement-icon {
            margin-right: 8px;
            width: 16px;
            height: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .requirement.valid {
            color: var(--success-green);
        }
        
        .requirement.invalid {
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
                        <label for="student_id">Student ID</label>
                        <input type="text" class="form-control" id="student_id" name="student_id" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Moe Email</label>
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
                        <input type="password" class="form-control" id="new_password" name="new_password" required onfocus="showRequirements()" onblur="hideRequirements()" onkeyup="checkPassword()">
                        <div id="requirements" class="requirements-list">
                            <div class="requirement invalid" id="length-req">
                                <span class="requirement-icon">•</span>
                                At least 8 characters
                            </div>
                            <div class="requirement invalid" id="uppercase-req">
                                <span class="requirement-icon">•</span>
                                At least 1 uppercase letter
                            </div>
                            <div class="requirement invalid" id="lowercase-req">
                                <span class="requirement-icon">•</span>
                                At least 1 lowercase letter
                            </div>
                            <div class="requirement invalid" id="number-req">
                                <span class="requirement-icon">•</span>
                                At least 1 number
                            </div>
                            <div class="requirement invalid" id="special-req">
                                <span class="requirement-icon">•</span>
                                At least 1 special character
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
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
                <div id="resend-section" style="text-align:center;margin-top:12px;">
                    <div id="countdown" class="password-requirements" <?= $canResend ? 'style="display:none;"' : '' ?>>
                        You can resend the code in <span id="resend-timer"><?= $remaining ?></span> seconds.
                    </div>
                    <form id="resend-form" method="POST" style="display:<?= $canResend ? 'inline' : 'none' ?>;">
                        <button type="submit" class="btn" name="resend_code">Resend Code</button>
                    </form>
                </div>
                <script>
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

                    function showRequirements() {
                        document.getElementById('requirements').classList.add('active');
                    }

                    function hideRequirements() {
                        const password = document.getElementById('new_password').value;
                        if (password === '') {
                            document.getElementById('requirements').classList.remove('active');
                        }
                    }

                    function checkPassword() {
                        const password = document.getElementById('new_password').value;
                        const requirements = document.getElementById('requirements');
                        
                        // At least 8 characters
                        if (password.length >= 8) {
                            document.getElementById('length-req').classList.remove('invalid');
                            document.getElementById('length-req').classList.add('valid');
                        } else {
                            document.getElementById('length-req').classList.remove('valid');
                            document.getElementById('length-req').classList.add('invalid');
                        }
                        
                        // At least 1 uppercase letter
                        if (/[A-Z]/.test(password)) {
                            document.getElementById('uppercase-req').classList.remove('invalid');
                            document.getElementById('uppercase-req').classList.add('valid');
                        } else {
                            document.getElementById('uppercase-req').classList.remove('valid');
                            document.getElementById('uppercase-req').classList.add('invalid');
                        }
                        
                        // At least 1 lowercase letter
                        if (/[a-z]/.test(password)) {
                            document.getElementById('lowercase-req').classList.remove('invalid');
                            document.getElementById('lowercase-req').classList.add('valid');
                        } else {
                            document.getElementById('lowercase-req').classList.remove('valid');
                            document.getElementById('lowercase-req').classList.add('invalid');
                        }
                        
                        // At least 1 number
                        if (/[0-9]/.test(password)) {
                            document.getElementById('number-req').classList.remove('invalid');
                            document.getElementById('number-req').classList.add('valid');
                        } else {
                            document.getElementById('number-req').classList.remove('valid');
                            document.getElementById('number-req').classList.add('invalid');
                        }
                        
                        // At least 1 special character
                        if (/[^a-zA-Z0-9]/.test(password)) {
                            document.getElementById('special-req').classList.remove('invalid');
                            document.getElementById('special-req').classList.add('valid');
                        } else {
                            document.getElementById('special-req').classList.remove('valid');
                            document.getElementById('special-req').classList.add('invalid');
                        }
                        
                        // Keep requirements visible if typing
                        if (password !== '') {
                            requirements.classList.add('active');
                        }
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>