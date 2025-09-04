<?php
// filepath: c:\xampp\htdocs\school_project\admin\edit_user.php
require_once "../classes/DB.php";
require_once "../classes/Admin.php";
session_start();

$db = new DB();
$admin = new Admin($db);

$errors = [];
$success = false;

// Get user ID from query
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user = $admin->getUserById($id);

if (!$user) {
    die("User not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $student_id = trim($_POST['student_id']);
    $email = trim($_POST['email']);
    $class_name = trim($_POST['class_name']);
    $phone = trim($_POST['phone']);
    $parent_name = trim($_POST['parent_name']);
    $parent_phone = trim($_POST['parent_phone']);

    // Check for duplicate email (exclude current user)
    $checkStmt = $db->getConnection()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkStmt->execute([$email, $id]);
    if ($checkStmt->fetch()) {
        $errors[] = "Email address already exists for another user.";
    }

    // Check for duplicate student_id (exclude current user)
    if (!empty($student_id)) {
        $checkStmt2 = $db->getConnection()->prepare("SELECT id FROM users WHERE student_id = ? AND id != ?");
        $checkStmt2->execute([$student_id, $id]);
        if ($checkStmt2->fetch()) {
            $errors[] = "Student ID already exists for another user.";
        }
    }

    if (empty($errors)) {
        $stmt = $db->getConnection()->prepare("UPDATE users SET name=?, student_id=?, email=?, class_name=?, phone=?, parent_name=?, parent_phone=? WHERE id=?");
        if ($stmt->execute([$name, $student_id, $email, $class_name, $phone, $parent_name, $parent_phone, $id])) {
            $success = true;
            // Reload updated user
            $user = $admin->getUserById($id);
        } else {
            $errors[] = "Failed to update user.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student | School Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #66BB6A;
            --primary-dark: #43A047;
            --primary-light: #C8E6C9;
            --secondary-color: #f8f9fa;
            --success-color: #4CAF50;
            --error-color: #f44336;
            --text-color: #37474F;
            --light-gray: #f5f5f5;
            --border-color: #E0E0E0;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --card-bg: #ffffff;
            --body-bg: #F1F8E9;
            --input-bg: #FAFAFA;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--body-bg);
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 30px auto;
            background: var(--card-bg);
            padding: 40px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        h2 {
            color: var(--primary-dark);
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--primary-light);
            font-weight: 600;
            font-size: 1.8rem;
            position: relative;
        }

        h2::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -1px;
            width: 60px;
            height: 3px;
            background: var(--primary-color);
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 6px;
            font-weight: 500;
            position: relative;
            padding-left: 50px;
        }

        .alert-success {
            background-color: #E8F5E9;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-error {
            background-color: #FFEBEE;
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }

        .alert::before {
            font-family: 'Material Icons';
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
        }

        .alert-success::before {
            content: "check_circle";
            color: var(--success-color);
        }

        .alert-error::before {
            content: "error";
            color: var(--error-color);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-col {
            flex: 1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #546E7A;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 15px;
            transition: var(--transition);
            background-color: var(--input-bg);
            font-family: 'Poppins', sans-serif;
        }

        input[type="text"]:focus,
        input[type="email"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 187, 106, 0.2);
            background-color: #fff;
        }

        .btn {
            display: inline-block;
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 14px 28px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            font-family: 'Poppins', sans-serif;
            text-transform: none;
        }

        .btn:hover {
            background: var(--primary-dark);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            margin-top: 25px;
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            font-size: 0.95rem;
        }

        .back-link:hover {
            text-decoration: underline;
            color: var(--primary-color);
        }

        .back-link svg {
            margin-right: 8px;
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .container {
                padding: 25px;
            }
            
            h2 {
                font-size: 1.5rem;
            }
        }

        /* Professional enhancements */
        .container {
            position: relative;
            overflow: hidden;
        }

        input[type="text"]::placeholder,
        input[type="email"]::placeholder {
            color: #90A4AE;
            font-size: 0.9em;
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .form-header h2 {
            margin-bottom: 0;
            border-bottom: none;
        }

        .form-header h2::after {
            display: none;
        }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
<div class="container">
    <div class="form-header">
        <h2>Edit Student Information</h2>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success">Student information updated successfully.</div>
    <?php endif; ?>
    
    <?php if ($errors): ?>
        <div class="alert alert-error"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    
    <form method="post">
        <div class="form-row">
            <div class="form-col">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-col">
                <div class="form-group">
                    <label for="student_id">Student ID</label>
                    <input type="text" id="student_id" name="student_id" value="<?= htmlspecialchars($user['student_id'] ?? '') ?>" required>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
        </div>
        
        <div class="form-row">
            <div class="form-col">
                <div class="form-group">
                    <label for="class_name">Class</label>
                    <input type="text" id="class_name" name="class_name" value="<?= htmlspecialchars($user['class_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-col">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-col">
                <div class="form-group">
                    <label for="parent_name">Parent Name</label>
                    <input type="text" id="parent_name" name="parent_name" value="<?= htmlspecialchars($user['parent_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-col">
                <div class="form-group">
                    <label for="parent_phone">Parent Phone</label>
                    <input type="text" id="parent_phone" name="parent_phone" value="<?= htmlspecialchars($user['parent_phone'] ?? '') ?>">
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn btn-block">Save Changes</button>
    </form>
    
    <a href="view_user.php" class="back-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
        </svg>
        Back to User List
    </a>
</div>
</body>
</html>