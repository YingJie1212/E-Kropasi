<?php
require_once "../classes/DB.php";
require_once "../classes/User.php";
session_start();

$db = new DB();
$user = new User($db);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'         => trim($_POST['name'] ?? ''),
        'chinese_name' => trim($_POST['chinese_name'] ?? ''),
        'gender'       => trim($_POST['gender'] ?? ''),
        'student_id'   => trim($_POST['student_id'] ?? ''),
        'class_name'   => trim($_POST['class_name'] ?? ''),
        'email'        => trim($_POST['email'] ?? ''),
        'profile_image'=> '',
    ];

    // Validate inputs
    if (empty($data['name'])) {
        $errors[] = "Name is required.";
    }
    if (empty($data['chinese_name'])) {
        $errors[] = "Chinese Name is required.";
    }
    if (empty($data['gender'])) {
        $errors[] = "Gender is required.";
    }
    if (empty($data['student_id'])) {
        $errors[] = "Student ID is required.";
    }
    if (empty($data['class_name'])) {
        $errors[] = "Class name is required.";
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check for existing records only if validation passed
    if (empty($errors)) {
        if ($user->studentIdExists($data['student_id'])) {
            $errors[] = "Student ID already exists.";
        }
        if ($user->emailExists($data['email'])) {
            $errors[] = "Email already exists.";
        }
    }

    if (empty($errors)) {
        if ($user->register($data)) {
            $success = true;
            // Clear form data on success
            $data = array_map(function() { return ''; }, $data);
        } else {
            $errors[] = "Failed to add user. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student | Student Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4CAF50; /* Green */
            --primary-dark: #388E3C; /* Darker green */
            --secondary-color: #8BC34A; /* Light green */
            --success-color: #4CAF50; /* Green */
            --error-color: #F44336; /* Red */
            --light-gray: #F1F8E9; /* Very light green */
            --medium-gray: #E8F5E9; /* Light green */
            --dark-gray: #689F38; /* Medium green */
            --text-color: #2E7D32; /* Dark green */
            --border-radius: 0.375rem;
            --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--light-gray);
        }

        .container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        h2 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-weight: 600;
            text-align: center;
            position: relative;
            padding-bottom: 0.5rem;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--success-color);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--primary-dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            line-height: 1.5;
            color: var(--text-color);
            background-color: white;
            background-clip: padding-box;
            border: 1px solid var(--medium-gray);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: 1px solid transparent;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: var(--border-radius);
            transition: var(--transition);
            cursor: pointer;
            background-color: var(--primary-color);
            color: white;
            width: 100%;
            margin-top: 0.5rem;
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
        }

        .alert-success {
            background-color: rgba(76, 175, 80, 0.2);
            border-left: 4px solid var(--success-color);
            color: var(--text-color);
        }

        .alert-error {
            background-color: rgba(244, 67, 54, 0.1);
            border-left: 4px solid var(--error-color);
            color: var(--text-color);
        }

        .alert ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        .required-field::after {
            content: '*';
            color: var(--error-color);
            margin-left: 0.25rem;
        }

        .back-btn {
            background-color: var(--secondary-color);
            margin-top: 1rem;
            width: auto;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .back-btn:hover {
            background-color: #7CB342; /* Slightly darker light green */
        }

        @media (max-width: 640px) {
            .container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }

        /* Animation for success message */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Add New Student</h2>
    
    <?php if ($success): ?>
        <div class="alert alert-success fade-in">
            <i class="fas fa-check-circle"></i> Student added successfully!
        </div>
    <?php endif; ?>
    
    <?php if ($errors): ?>
        <div class="alert alert-error fade-in">
            <i class="fas fa-exclamation-circle"></i>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" autocomplete="off">
        <div class="form-group">
            <label for="name" class="required-field">Full Name</label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?= htmlspecialchars($data['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="chinese_name" class="required-field">Chinese Name</label>
            <input type="text" id="chinese_name" name="chinese_name" class="form-control" 
                   value="<?= htmlspecialchars($data['chinese_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="gender" class="required-field">Gender</label>
            <select id="gender" name="gender" class="form-control" required>
                <option value="">Select Gender</option>
                <option value="Male" <?= (isset($data['gender']) && $data['gender'] == 'Male') ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= (isset($data['gender']) && $data['gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= (isset($data['gender']) && $data['gender'] == 'Other') ? 'selected' : '' ?>>Other</option>
            </select>
        </div>
        <div class="form-group">
            <label for="student_id" class="required-field">Student ID</label>
            <input type="text" id="student_id" name="student_id" class="form-control" 
                   value="<?= htmlspecialchars($data['student_id'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="class_name" class="required-field">Class</label>
            <input type="text" id="class_name" name="class_name" class="form-control" 
                   value="<?= htmlspecialchars($data['class_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="email" class="required-field">Email</label>
            <input type="email" id="email" name="email" class="form-control" 
                   value="<?= htmlspecialchars($data['email'] ?? '') ?>" required>
        </div>
 
        <button type="submit" class="btn">
            <i class="fas fa-user-plus"></i> Add Student
        </button>
    </form>
    
    <a href="user_register.php" class="btn back-btn">
        <i class="fas fa-arrow-left"></i> Back to User Register
    </a>
</div>

<script>
    // Simple client-side validation enhancement
    document.querySelector('form').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value;
        if (!email.includes('@')) {
            alert('Please enter a valid email address');
            e.preventDefault();
        }
    });
</script>
</body>
</html>