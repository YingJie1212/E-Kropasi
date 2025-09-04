<?php
session_start();
require_once "../classes/Admin.php";
$admin = new Admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminLevel = $admin->login($_POST['email'], $_POST['password']);
    if ($adminLevel) {
        $admin_id = $_SESSION['admin_id'];
        $_SESSION['is_admin'] = $adminLevel;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid credentials!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | SMJK Phor Tay</title>
    <style>
        :root {
            --primary-green: #4CAF50;
            --dark-green: #388E3C;
            --light-green: #E8F5E9;
            --accent-yellow: #FFD54F;
            --light-yellow: #FFF8E1;
            --white: #FFFFFF;
            --light-gray: #FAFAFA;
            --medium-gray: #E0E0E0;
            --dark-gray: #424242;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', sans-serif;
        }
        
        body {
            background-color: var(--light-yellow);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            color: white;
            padding: 1.5rem 3rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo {
            height: 50px;
        }
        
        .brand-text {
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .brand-subtext {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 0.2rem;
        }
        
        .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-grow: 1;
            padding: 2rem;
        }
        
        .login-container {
            background-color: var(--white);
            width: 500px;
            max-width: 95%;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
            padding: 3rem;
        }
        
        .login-form {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .form-header {
            margin-bottom: 2.5rem;
            text-align: center;
        }
        
        .form-title {
            font-size: 2rem;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }
        
        .form-subtitle {
            color: var(--dark-gray);
            opacity: 0.7;
            font-size: 0.95rem;
        }
        
        .input-group {
            margin-bottom: 1.5rem;
        }
        
        .input-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .input-field {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 1px solid var(--medium-gray);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--light-green);
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            background-color: var(--white);
        }
        
        .login-button {
            width: 100%;
            padding: 1rem;
            background-color: var(--primary-green);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
        }
        
        .login-button:hover {
            background-color: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .error-message {
            color: #D32F2F;
            background-color: #FFEBEE;
            padding: 0.9rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border-left: 4px solid #D32F2F;
        }
        
        .footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--dark-gray);
            font-size: 0.9rem;
            background-color: var(--light-yellow);
        }
        
        .forgot-link {
            display: block;
            text-align: right;
            margin-top: 0.5rem;
            color: var(--primary-green);
            font-size: 0.9rem;
            text-decoration: none;
        }
        
        .forgot-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1rem 1.5rem;
            }
            
            .brand-text {
                font-size: 1.4rem;
            }
            
            .login-container {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-container">
            <img src="images/llogo.png" alt="SMJK Phor Tay" class="logo">
            <div>
                <div class="brand-text">SMJK PHOR TAY</div>
            
            </div>
        </div>
    </header>
    
    <main class="main-content">
        <div class="login-container">
            <div class="login-form">
                <div class="form-header">
                    <h1 class="form-title">Admin Login</h1>
                    <p class="form-subtitle">Please enter your credentials to continue</p>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="input-group">
                        <label for="email" class="input-label">Email Address</label>
                        <input type="email" id="email" name="email" class="input-field" placeholder="admin@phortay.edu.my" required>
                    </div>
                    
                    <div class="input-group">
                        <label for="password" class="input-label">Password</label>
                        <input type="password" id="password" name="password" class="input-field" placeholder="Enter your password" required>
                        <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="login-button">Sign In</button>
                </form>
            </div>
        </div>
    </main>
    
    <footer class="footer">
      
    </footer>
</body>
</html>