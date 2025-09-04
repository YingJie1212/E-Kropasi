<?php
require_once "../classes/DB.php";
require_once "../classes/User.php";
session_start();

$db = new DB();
$user = new User($db);

$errors = [];

// AJAX login handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $student_id = trim($_POST['student_id']);
    $password = $_POST['password'];
    $response = [];

    if (empty($student_id) || empty($password)) {
        $response['status'] = 'error';
        $response['message'] = "Both Student ID and Password are required.";
    } else {
        $loggedInUser = $user->login($student_id, $password);
        if ($loggedInUser) {
            if (!empty($loggedInUser['status']) && $loggedInUser['status'] === 'banned') {
                $response['status'] = 'banned';
            } else {
                $_SESSION['user_id'] = $loggedInUser['id'];
                $_SESSION['student_id'] = $loggedInUser['student_id'];
                $_SESSION['name'] = $loggedInUser['name'];
                $response['status'] = 'success';
                $response['redirect'] = 'products.php';
            }
        } else {
            $response['status'] = 'error';
            $response['message'] = "Invalid Student ID or Password.";
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Login</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <style>
        :root {
            --color-primary: #F5F5DC;        /* Light beige/light yellow */
            --color-secondary: #E8F5E9;      /* Light green */
            --color-accent: #81C784;         /* Medium green */
            --color-accent-dark: #4CAF50;    /* Darker green */
            --color-text: #2E7D32;           /* Dark green */
            --color-text-light: #689F38;      /* Lighter green */
            --color-error: #D32F2F;          /* Red for errors */
            --color-white: #FFFFFF;
            --color-gray-light: #F5F5F5;
            --color-gray-medium: #E0E0E0;
            --color-gray-dark: #757575;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --transition: all 0.2s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--color-secondary);
            color: var(--color-text);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background-color: var(--color-white);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .login-header {
            background-color: var(--color-primary);
            padding: 2.5rem 2rem;
            text-align: center;
            border-bottom: 4px solid var(--color-accent);
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            background-color: var(--color-accent);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--color-white);
            font-size: 2rem;
            font-weight: 700;
            box-shadow: var(--shadow-sm);
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--color-text);
        }

        .login-header p {
            font-size: 0.875rem;
            color: var(--color-text-light);
        }

        .login-form {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--color-text);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 1px solid var(--color-gray-medium);
            border-radius: 6px;
            transition: var(--transition);
            background-color: var(--color-gray-light);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(129, 199, 132, 0.2);
        }

        .btn {
            display: inline-block;
            width: 100%;
            padding: 0.875rem;
            font-size: 1rem;
            font-weight: 600;
            color: var(--color-white);
            background-color: var(--color-accent);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .btn:hover {
            background-color: var(--color-accent-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn:active {
            transform: translateY(0);
        }

        .error {
            background-color: rgba(211, 47, 47, 0.1);
            color: var(--color-error);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border-left: 4px solid var(--color-error);
        }

        .error ul {
            list-style: none;
        }

        .error li {
            margin-bottom: 0.25rem;
        }

        .login-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--color-gray-dark);
        }

        .login-footer a {
            color: var(--color-text-light);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .login-footer a:hover {
            color: var(--color-accent-dark);
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: var(--color-gray-dark);
            font-size: 0.75rem;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background-color: var(--color-gray-medium);
        }

        .divider::before {
            margin-right: 1rem;
        }

        .divider::after {
            margin-left: 1rem;
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: var(--color-white);
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            width: 90%;
            max-width: 400px;
            text-align: center;
        }

        .modal-content h2 {
            margin-bottom: 1rem;
            color: var(--color-text);
        }

        .modal-content p {
            margin-bottom: 1.5rem;
            color: var(--color-gray-dark);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .modal-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }

        .modal-btn.primary {
            background-color: var(--color-accent);
            color: var(--color-white);
        }

        .modal-btn.primary:hover {
            background-color: var(--color-accent-dark);
        }

        .modal-btn.secondary {
            background-color: var(--color-gray-light);
            color: var(--color-text);
        }

        .modal-btn.secondary:hover {
            background-color: var(--color-gray-medium);
        }

        /* Responsive adjustments */
        @media (max-width: 480px) {
            .login-container {
                border-radius: 0;
            }
            
            body {
                padding: 0;
                background-color: var(--color-white);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">SP</div>
            <h1>Student Portal</h1>
            <p>Sign in to access your dashboard</p>
        </div>
        
        <div class="login-form">
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div id="loginErrorBox" class="error" style="display:none;"></div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="student_id">Student ID</label>
                    <input type="text" class="form-control" id="student_id" name="student_id" placeholder="Enter your student ID" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div class="divider">or</div>
            
            <div class="login-footer">
                <p><a href="forgot_password.php">Forgot your password?</a></p>
            </div>
        </div>
    </div>

    <!-- Account Modal -->
    <div id="accountModal" class="modal-overlay">
        <div class="modal-content">
            <h2>Do you have an account?</h2>
            <p>Please select an option to continue</p>
            <div class="modal-actions">
                <button id="closeModalBtn" class="modal-btn primary">Yes, Login</button>
                <button id="guestBtn" class="modal-btn secondary">Continue as Guest</button>
            </div>
        </div>
    </div>

    <!-- Banned Modal -->
    <div id="bannedModal" class="modal-overlay">
        <div class="modal-content">
            <h2 style="color: var(--color-error);">Account Restricted</h2>
            <p>Your account has been suspended. Please contact support for assistance.</p>
            <button id="bannedCloseBtn" class="modal-btn primary">Close</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal functionality
        const accountModal = document.getElementById('accountModal');
        const bannedModal = document.getElementById('bannedModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const guestBtn = document.getElementById('guestBtn');
        const bannedCloseBtn = document.getElementById('bannedCloseBtn');

        // Show account modal on page load
        accountModal.classList.add('active');

        // Close account modal
        closeModalBtn.addEventListener('click', function() {
            accountModal.classList.remove('active');
        });

        // Guest button
        guestBtn.addEventListener('click', function() {
            window.location.href = 'new_student_dashboard.php';
        });

        // Close banned modal
        bannedCloseBtn.addEventListener('click', function() {
            bannedModal.classList.remove('active');
        });

        // AJAX login
        const loginForm = document.querySelector('form[method="POST"]');
        const errorBox = document.getElementById('loginErrorBox');

        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Hide error box if visible
                if (errorBox) {
                    errorBox.style.display = 'none';
                }

                const formData = new FormData(loginForm);
                formData.append('ajax', '1');

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.href = data.redirect;
                    } else if (data.status === 'banned') {
                        bannedModal.classList.add('active');
                    } else if (data.status === 'error') {
                        if (errorBox) {
                            errorBox.textContent = data.message;
                            errorBox.style.display = 'block';
                        }
                    }
                })
                .catch(error => {
                    if (errorBox) {
                        errorBox.textContent = 'An error occurred. Please try again.';
                        errorBox.style.display = 'block';
                    }
                });
            });
        }
    });
    </script>
</body>
</html>