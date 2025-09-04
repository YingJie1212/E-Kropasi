<?php
require_once "../classes/Admin.php";
require_once "../classes/OrderManager.php";
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin = new Admin();
$orderManager = new OrderManager();
$id = $_GET['id'] ?? null;

$message = '';

// Fetch admin details
if ($id && is_numeric($id)) {
    $data = $admin->getById($id);

    if (!$data) {
        die("Admin not found.");
    }
} else {
    die("Invalid admin ID.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    // Basic validations
    if (!$name) {
        $message = "<div class='alert alert-danger'>⚠️ Name is required.</div>";
    } elseif (!$email) {
        $message = "<div class='alert alert-danger'>⚠️ Email is required.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert alert-danger'>⚠️ Invalid email format.</div>";
    } elseif ($admin->existsByEmail($email, $id)) {
        $message = "<div class='alert alert-danger'>❌ Another admin with that email already exists.</div>";
    } elseif ($phone && !preg_match('/^[0-9]{10,15}$/', $phone)) {
        $message = "<div class='alert alert-danger'>❌ Invalid phone format (10-15 digits).</div>";
    } elseif ($password || $confirm) {
        // Password update requested
        if (strlen($password) < 6) {
            $message = "<div class='alert alert-danger'>❌ Password must be at least 6 characters.</div>";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $message = "<div class='alert alert-danger'>❌ Password must contain at least 1 lowercase letter.</div>";
        } elseif ($password !== $confirm) {
            $message = "<div class='alert alert-danger'>❌ Passwords do not match.</div>";
        }
    }

    // If no errors so far, update
    if (!$message) {
        $updateSuccess = $admin->update($id, $name, $email, $phone);

        $updatePassword = true;
        if ($password) {
            $updatePassword = $admin->updatePassword($id, $password, $_SESSION['admin_id']);
        }

        if ($updateSuccess && $updatePassword) {
            $message = "<div class='alert alert-success'>✅ Admin updated successfully.</div>";
            $data = $admin->getById($id); // refresh
        } else {
            $message = "<div class='alert alert-danger'>❌ Failed to update admin.</div>";
        }
    }
}

$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-50: #f0fdf4;
            --primary-100: #dcfce7;
            --primary-200: #bbf7d0;
            --primary-300: #86efac;
            --primary-400: #4ade80;
            --primary-500: #22c55e;
            --primary-600: #16a34a;
            --primary-700: #15803d;
            --primary-800: #166534;
            --primary-900: #14532d;
            
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            --success-50: #ecfdf5;
            --success-100: #d1fae5;
            --success-500: #10b981;
            
            --warning-50: #fffbeb;
            --warning-100: #fef3c7;
            --warning-500: #f59e0b;
            
            --danger-50: #fef2f2;
            --danger-100: #fee2e2;
            --danger-500: #ef4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.5;
        }
        
        .container {
            max-width: 640px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .card-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-control {
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--gray-800);
            background-color: white;
            background-clip: padding-box;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            border-color: var(--primary-400);
            outline: 0;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.25);
        }
        
        .input-group {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            width: 100%;
        }
        
        .input-group .form-control {
            position: relative;
            flex: 1 1 auto;
            width: 1%;
            min-width: 0;
        }
        
        .input-group-append {
            display: flex;
            margin-left: -1px;
        }
        
        .btn-toggle-password {
            display: flex;
            align-items: center;
            padding: 0 0.875rem;
            background-color: var(--gray-100);
            border: 1px solid var(--gray-300);
            border-radius: 0 0.375rem 0.375rem 0;
            cursor: pointer;
            user-select: none;
            color: var(--gray-500);
        }
        
        .btn-toggle-password:hover {
            background-color: var(--gray-200);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.5;
            color: white;
            text-align: center;
            text-decoration: none;
            white-space: nowrap;
            background-color: var(--primary-500);
            border: 1px solid transparent;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }
        
        .btn:hover {
            background-color: var(--primary-600);
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .alert {
            position: relative;
            padding: 0.875rem 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }
        
        .alert-success {
            color: #166534;
            background-color: var(--success-50);
            border-color: var(--success-100);
        }
        
        .alert-danger {
            color: #991b1b;
            background-color: var(--danger-50);
            border-color: var(--danger-100);
        }
        
        .feedback {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        
        .valid-feedback {
            color: var(--success-500);
            background-color: var(--success-50);
        }
        
        .invalid-feedback {
            color: var(--danger-500);
            background-color: var(--danger-50);
        }
        
        .warning-feedback {
            color: var(--warning-500);
            background-color: var(--warning-50);
        }
        
        .text-muted {
            color: var(--gray-500);
        }
        
        .text-success {
            color: var(--success-500);
        }
        
        .text-danger {
            color: var(--danger-500);
        }
        
        .text-warning {
            color: var(--warning-500);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--primary-600);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.15s ease-in-out;
        }
        
        .back-link:hover {
            color: var(--primary-700);
            text-decoration: underline;
        }
        
        .back-link svg {
            width: 1rem;
            height: 1rem;
            margin-right: 0.5rem;
        }

        /* Password Requirements Styles */
        .password-requirements {
            margin-top: 0.5rem;
            padding: 0.75rem;
            background-color: var(--gray-50);
            border-radius: 0.375rem;
            border: 1px solid var(--gray-200);
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
            color: var(--success-500);
        }

        .password-requirements li.invalid {
            color: var(--gray-500);
        }

        .password-requirements li::before {
            content: "•";
            display: inline-block;
            width: 1em;
            margin-left: -1em;
        }
        
        @media (max-width: 640px) {
            .card {
                padding: 1.5rem;
            }
            
            .card-header h2 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Edit Admin Profile</h2>
            </div>
            
            <?= $message ?>
            
            <form method="POST" id="adminForm" novalidate>
                <div class="form-group">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($data['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($data['email']) ?>" required>
                    <div id="email-feedback" class="feedback"></div>
                </div>
                
                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number <span class="text-muted">(optional)</span></label>
                    <input type="tel" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($data['phone']) ?>" pattern="[0-9]{10,15}" placeholder="10-15 digits only">
                    <div id="phone-feedback" class="feedback"></div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">New Password <span class="text-muted">(leave blank to keep current)</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" minlength="6">
                        <div class="input-group-append">
                            <span class="btn-toggle-password" data-target="password">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div id="password-requirements" class="password-requirements">
                        <strong>Password Requirements:</strong>
                        <ul>
                            <li id="req-length" class="invalid">At least 6 characters</li>
                            <li id="req-lowercase" class="invalid">At least 1 lowercase letter</li>
                            <li id="req-uppercase" class="invalid">At least 1 uppercase letter</li>
                            <li id="req-number" class="invalid">At least 1 number</li>
                            <li id="req-special" class="invalid">At least 1 special character</li>
                        </ul>
                    </div>
                    <div id="password-strength" class="feedback"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirm" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" name="confirm" id="confirm" class="form-control" minlength="6">
                        <div class="input-group-append">
                            <span class="btn-toggle-password" data-target="confirm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div id="confirm-feedback" class="feedback"></div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-block">Update Profile</button>
                </div>
            </form>
            
            <a href="dashboard.php" class="back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                </svg>
                Back to Dashboard
            </a>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        document.querySelectorAll('.btn-toggle-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('svg');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.innerHTML = '<path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/><path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/>';
                } else {
                    input.type = 'password';
                    icon.innerHTML = '<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>';
                }
            });
        });

        // Form validation
        const emailInput = document.getElementById('email');
        const emailFeedback = document.getElementById('email-feedback');
        const passwordInput = document.getElementById('password');
        const passwordRequirements = document.getElementById('password-requirements');
        const passwordStrengthText = document.getElementById('password-strength');
        const confirmInput = document.getElementById('confirm');
        const confirmFeedback = document.getElementById('confirm-feedback');
        const phoneInput = document.getElementById('phone');
        const phoneFeedback = document.getElementById('phone-feedback');
        const submitBtn = document.querySelector('button[type="submit"]');

        let emailValid = true;
        let passwordStrong = true;
        let passwordsMatch = true;
        let phoneValid = true;

        function toggleSubmit() {
            submitBtn.disabled = !(emailValid && passwordStrong && passwordsMatch && phoneValid);
        }

        // Email validation
        emailInput.addEventListener('input', function() {
            const email = this.value.trim();
            emailFeedback.className = 'feedback';
            
            if (!email) {
                emailFeedback.textContent = '';
                emailValid = false;
                toggleSubmit();
                return;
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                emailFeedback.textContent = '❌ Invalid email format';
                emailFeedback.className = 'feedback invalid-feedback';
                emailValid = false;
                toggleSubmit();
                return;
            }
            
            // AJAX check for existing email
            fetch('check_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `email=${encodeURIComponent(email)}&exclude_id=<?= $id ?>`
            })
            .then(response => response.text())
            .then(text => {
                emailFeedback.innerHTML = text;
                
                if (text.includes('✅')) {
                    emailFeedback.className = 'feedback valid-feedback';
                    emailValid = true;
                } else {
                    emailFeedback.className = 'feedback invalid-feedback';
                    emailValid = false;
                }
                toggleSubmit();
            })
            .catch(error => {
                console.error('Error:', error);
                emailFeedback.textContent = '⚠️ Error checking email availability';
                emailFeedback.className = 'feedback warning-feedback';
                emailValid = false;
                toggleSubmit();
            });
        });

        // Password strength and requirements
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            
            // Show/hide requirements box
            if (password.length > 0) {
                passwordRequirements.style.display = 'block';
            } else {
                passwordRequirements.style.display = 'none';
                passwordStrengthText.textContent = '';
                passwordStrong = true;
                toggleSubmit();
                return;
            }
            
            // Check each requirement
            const hasLength = password.length >= 6;
            const hasLowercase = /[a-z]/.test(password);
            const hasUppercase = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^A-Za-z0-9]/.test(password);
            
            // Update requirement indicators
            document.getElementById('req-length').className = hasLength ? 'valid' : 'invalid';
            document.getElementById('req-lowercase').className = hasLowercase ? 'valid' : 'invalid';
            document.getElementById('req-uppercase').className = hasUppercase ? 'valid' : 'invalid';
            document.getElementById('req-number').className = hasNumber ? 'valid' : 'invalid';
            document.getElementById('req-special').className = hasSpecial ? 'valid' : 'invalid';
            
            // Overall strength assessment
            passwordStrengthText.className = 'feedback';
            
            if (password.length < 6) {
                passwordStrengthText.textContent = '❌ Password must be at least 6 characters';
                passwordStrengthText.className = 'feedback invalid-feedback';
                passwordStrong = false;
            } else if (!hasLowercase) {
                passwordStrengthText.textContent = '❌ Password must contain at least 1 lowercase letter';
                passwordStrengthText.className = 'feedback invalid-feedback';
                passwordStrong = false;
            } else {
                const strength = [hasUppercase, hasNumber, hasSpecial].filter(Boolean).length;
                
                if (strength === 0) {
                    passwordStrengthText.textContent = '⚠️ Weak password (add uppercase, number, or special character)';
                    passwordStrengthText.className = 'feedback warning-feedback';
                } else if (strength < 2) {
                    passwordStrengthText.textContent = '⚠️ Fair password';
                    passwordStrengthText.className = 'feedback warning-feedback';
                } else if (strength < 3) {
                    passwordStrengthText.textContent = '✅ Good password';
                    passwordStrengthText.className = 'feedback valid-feedback';
                } else {
                    passwordStrengthText.textContent = '✅ Excellent password';
                    passwordStrengthText.className = 'feedback valid-feedback';
                }
                
                passwordStrong = true;
            }
            
            // Trigger confirm password check
            if (confirmInput.value) {
                confirmInput.dispatchEvent(new Event('input'));
            }
            
            toggleSubmit();
        });

        // Confirm password
        confirmInput.addEventListener('input', function() {
            const confirm = this.value;
            confirmFeedback.className = 'feedback';
            
            if (!confirm) {
                confirmFeedback.textContent = '';
                passwordsMatch = true;
                toggleSubmit();
                return;
            }
            
            if (confirm !== passwordInput.value) {
                confirmFeedback.textContent = '❌ Passwords do not match';
                confirmFeedback.className = 'feedback invalid-feedback';
                passwordsMatch = false;
            } else {
                confirmFeedback.textContent = '✅ Passwords match';
                confirmFeedback.className = 'feedback valid-feedback';
                passwordsMatch = true;
            }
            
            toggleSubmit();
        });

        // Phone validation
        phoneInput.addEventListener('input', function() {
            const phone = this.value.trim();
            phoneFeedback.className = 'feedback';
            
            if (!phone) {
                phoneFeedback.textContent = '';
                phoneValid = true;
                toggleSubmit();
                return;
            }
            
            if (!/^[0-9]{10,15}$/.test(phone)) {
                phoneFeedback.textContent = '❌ Phone must be 10-15 digits';
                phoneFeedback.className = 'feedback invalid-feedback';
                phoneValid = false;
            } else {
                phoneFeedback.textContent = '✅ Valid phone number';
                phoneFeedback.className = 'feedback valid-feedback';
                phoneValid = true;
            }
            
            toggleSubmit();
        });

        // Initialize form state
        toggleSubmit();
    });
    </script>
</body>
</html>