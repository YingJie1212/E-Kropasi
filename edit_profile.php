<?php
require_once "../classes/DB.php";
require_once "../classes/User.php";
session_start();

// Redirect if not logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$db = new DB();
$user = new User($db);
$student = $user->getByStudentId($_SESSION['student_id']);

if (!$student) {
    echo "User not found.";
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updatedData = [
        'profile_image' => $student['profile_image'],
    ];

    // Handle password update
    if (!empty($_POST['new_password'])) {
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if ($newPassword !== $confirmPassword) {
            $errors[] = "New password and confirm password do not match.";
        } elseif (
            strlen($newPassword) < 8 ||
            !preg_match('/[A-Z]/', $newPassword) ||
            !preg_match('/[a-z]/', $newPassword) ||
            !preg_match('/[0-9]/', $newPassword) ||
            !preg_match('/[^A-Za-z0-9]/', $newPassword) // Special character check
        ) {
            $errors[] = "Password must be at least 8 characters long and include uppercase, lowercase, a number, and a special character.";
        } else {
            $updatedData['password'] = $newPassword; // Pass plain password, let User.php hash it
        }
    }

    // Handle image upload (base64 from hidden input)
    if (!empty($_POST['cropped_image'])) {
        $data = $_POST['cropped_image'];
        if (preg_match('/^data:image\/png;base64,/', $data)) {
            $data = substr($data, strpos($data, ',') + 1);
            $data = base64_decode($data);
            if ($data !== false) {
                $uploadDir = "../uploads/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $uniqueName = time() . "_" . bin2hex(random_bytes(5)) . ".png";
                $targetPath = $uploadDir . $uniqueName;
                if (file_put_contents($targetPath, $data)) {
                    $updatedData['profile_image'] = $uniqueName;
                } else {
                    $errors[] = "Image upload failed.";
                }
            } else {
                $errors[] = "Invalid image data.";
            }
        } else {
            $errors[] = "Invalid image format.";
        }
    } elseif (!empty($_FILES['profile_image']['name'])) {
        // fallback for normal uploads
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $fileName = $_FILES['profile_image']['name'];
        $fileTmp = $_FILES['profile_image']['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedExtensions)) {
            $errors[] = "Only JPG, PNG, or GIF images are allowed.";
        } else {
            $imageInfo = getimagesize($fileTmp);
            if ($imageInfo === false) {
                $errors[] = "Uploaded file is not a valid image.";
            } else {
                $uploadDir = "../uploads/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $uniqueName = time() . "_" . bin2hex(random_bytes(5)) . ".png";
                $targetPath = $uploadDir . $uniqueName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $updatedData['profile_image'] = $uniqueName;
                } else {
                    $errors[] = "Image upload failed.";
                }
            }
        }
    }

    if (empty($errors)) {
        if ($user->updateProfile($_SESSION['student_id'], $updatedData)) {
            $success = true;
            $student = $user->getByStudentId($_SESSION['student_id']);
        } else {
            $errors[] = "Update failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | Student Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-50: #FFFDE7;
            --primary-100: #FFF9C4;
            --primary-200: #FFF59D;
            --primary-300: #FFF176;
            --secondary-50: #F1F8E9;
            --secondary-100: #DCEDC8;
            --secondary-200: #C5E1A5;
            --secondary-300: #AED581;
            --secondary-400: #9CCC65;
            --secondary-500: #8BC34A;
            --secondary-600: #7CB342;
            --secondary-700: #689F38;
            --secondary-800: #558B2F;
            --secondary-900: #33691E;
            --gray-50: #FAFAFA;
            --gray-100: #F5F5F5;
            --gray-200: #EEEEEE;
            --gray-300: #E0E0E0;
            --gray-400: #BDBDBD;
            --gray-500: #9E9E9E;
            --gray-600: #757575;
            --gray-700: #616161;
            --gray-800: #424242;
            --gray-900: #212121;
            --success-50: #E8F5E9;
            --success-100: #C8E6C9;
            --success-500: #4CAF50;
            --error-50: #FFEBEE;
            --error-100: #FFCDD2;
            --error-500: #F44336;
            --border-radius-sm: 4px;
            --border-radius-md: 8px;
            --border-radius-lg: 12px;
            --border-radius-xl: 16px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --spacing-1: 4px;
            --spacing-2: 8px;
            --spacing-3: 12px;
            --spacing-4: 16px;
            --spacing-5: 20px;
            --spacing-6: 24px;
            --spacing-7: 28px;
            --spacing-8: 32px;
            --spacing-9: 36px;
            --spacing-10: 40px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
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
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-8);
        }

        .page-header {
            margin-bottom: var(--spacing-6);
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--secondary-800);
            margin-bottom: var(--spacing-2);
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--gray-600);
            margin-bottom: var(--spacing-6);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-2);
            color: var(--secondary-600);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: var(--transition);
            margin-bottom: var(--spacing-6);
        }

        .back-link:hover {
            color: var(--secondary-800);
        }

        .back-link svg {
            width: 16px;
            height: 16px;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: var(--spacing-8);
        }

        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }

        /* Sidebar Card */
        .sidebar-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            height: fit-content;
        }

        .sidebar-header {
            padding: var(--spacing-5);
            background-color: var(--secondary-100);
            border-bottom: 1px solid var(--secondary-200);
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--secondary-900);
        }

        .sidebar-body {
            padding: var(--spacing-5);
        }

        .profile-image-container {
            position: relative;
            width: 160px;
            height: 160px;
            margin: 0 auto var(--spacing-4);
            border-radius: 50%;
            background-color: white;
            border: 4px solid var(--secondary-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary-700);
            font-size: 14px;
            font-weight: 500;
        }

        .image-upload-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-2);
            width: 100%;
            padding: var(--spacing-3) var(--spacing-4);
            background-color: var(--secondary-500);
            color: white;
            border: none;
            border-radius: var(--border-radius-md);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .image-upload-btn:hover {
            background-color: var(--secondary-600);
            box-shadow: var(--shadow-sm);
        }

        .image-upload-btn svg {
            width: 16px;
            height: 16px;
        }

        #profileImage {
            display: none;
        }

        .profile-info {
            margin-top: var(--spacing-4);
            font-size: 14px;
        }

        .profile-info-item {
            display: flex;
            justify-content: space-between;
            padding: var(--spacing-3) 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .profile-info-label {
            color: var(--gray-600);
        }

        .profile-info-value {
            color: var(--gray-800);
            font-weight: 500;
        }

        /* Main Content Card */
        .content-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .content-header {
            padding: var(--spacing-5);
            background-color: var(--secondary-100);
            border-bottom: 1px solid var(--secondary-200);
        }

        .content-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--secondary-900);
        }

        .content-body {
            padding: var(--spacing-5);
        }

        /* Alert Styles */
        .alert {
            padding: var(--spacing-4);
            margin-bottom: var(--spacing-6);
            border-radius: var(--border-radius-md);
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-3);
        }

        .alert-success {
            background-color: var(--success-50);
            color: var(--secondary-900);
            border-left: 4px solid var(--success-500);
        }

        .alert-error {
            background-color: var(--error-50);
            color: var(--error-500);
            border-left: 4px solid var(--error-500);
        }

        .alert-icon {
            font-size: 20px;
            line-height: 1;
        }

        /* Form Styles */
        .form-section {
            margin-bottom: var(--spacing-8);
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--secondary-800);
            margin-bottom: var(--spacing-4);
            padding-bottom: var(--spacing-2);
            border-bottom: 1px solid var(--gray-200);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--spacing-6);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: var(--spacing-4);
        }

        .form-group.hidden {
            display: none;
        }

        .form-label {
            display: block;
            margin-bottom: var(--spacing-2);
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-control {
            width: 100%;
            padding: var(--spacing-3) var(--spacing-4);
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-md);
            font-size: 14px;
            transition: var(--transition);
            background-color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary-400);
            box-shadow: 0 0 0 3px rgba(139, 195, 74, 0.2);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: var(--spacing-8);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-2);
            padding: var(--spacing-3) var(--spacing-6);
            border: none;
            border-radius: var(--border-radius-md);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background-color: var(--secondary-500);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-600);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:active {
            background-color: var(--secondary-700);
        }

        .btn-icon {
            width: 16px;
            height: 16px;
        }

        /* Password Requirements */
        .password-requirements {
            margin-top: var(--spacing-2);
            font-size: 12px;
            color: var(--gray-600);
            display: none; /* Initially hidden */
            transition: var(--transition);
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            margin-bottom: var(--spacing-1);
        }

        .requirement-icon {
            font-size: 14px;
        }

        /* Password requirement validation */
        .valid {
            color: var(--success-500);
        }

        .invalid {
            color: var(--gray-500);
        }

        /* Cropper Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: var(--spacing-4);
        }

        .modal-container {
            background-color: white;
            border-radius: var(--border-radius-lg);
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: var(--spacing-4) var(--spacing-5);
            background-color: var(--secondary-100);
            border-bottom: 1px solid var(--secondary-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--secondary-900);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--gray-600);
            font-size: 20px;
            cursor: pointer;
            line-height: 1;
        }

        .modal-body {
            padding: var(--spacing-5);
            flex-grow: 1;
            overflow: auto;
        }

        .cropper-container {
            width: 100%;
            max-height: 60vh;
        }

        .cropper-image-container {
            width: 100%;
            height: 400px;
        }

        .cropper-image-container img {
            max-width: 100%;
            max-height: 100%;
            display: block;
        }

        .modal-footer {
            padding: var(--spacing-4) var(--spacing-5);
            background-color: var(--gray-50);
            display: flex;
            justify-content: flex-end;
            gap: var(--spacing-3);
        }

        .btn-secondary {
            background-color: var(--gray-100);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background-color: var(--gray-200);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container {
                padding: var(--spacing-4);
            }
            
            .profile-image-container {
                width: 120px;
                height: 120px;
            }
            
            .cropper-image-container {
                height: 300px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: var(--spacing-3);
            }
            
            .form-actions {
                justify-content: center;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="profile.php" class="back-link">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
            Back to Profile
        </a>

        <div class="page-header">
            <h1 class="page-title">Edit Profile</h1>
            <p class="page-subtitle">Update your profile picture and password</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✓</span>
                <div>Your profile has been updated successfully!</div>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">!</span>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endforeach; ?>

        <div class="profile-container">
            <!-- Sidebar Card -->
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <h2 class="sidebar-title">Profile Picture</h2>
                </div>
                <div class="sidebar-body">
                    <div class="profile-image-container">
                        <?php if ($student['profile_image']): ?>
                            <img src="../uploads/<?= htmlspecialchars($student['profile_image']) ?>" id="imagePreview" class="profile-image">
                        <?php else: ?>
                            <div id="imagePreview" class="profile-image-placeholder">No Image</div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="image-upload-btn" onclick="document.getElementById('profileImage').click()">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                            <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/>
                        </svg>
                        Change Photo
                    </button>
                    <input type="file" name="profile_image" id="profileImage" accept="image/*">

                    <div class="profile-info">
                        <div class="profile-info-item">
                            <span class="profile-info-label">Student ID:</span>
                            <span class="profile-info-value"><?= htmlspecialchars($student['student_id']) ?></span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">Name:</span>
                            <span class="profile-info-value"><?= htmlspecialchars($student['name']) ?></span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">Class:</span>
                            <span class="profile-info-value"><?= htmlspecialchars($student['class_name']) ?></span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">Last Updated:</span>
                            <span class="profile-info-value"><?= date('M j, Y', strtotime($student['updated_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Card -->
            <div class="content-card">
                <form method="post" enctype="multipart/form-data">
                    <div class="content-header">
                        <h2 class="content-title">Security Settings</h2>
                    </div>
                    <div class="content-body">
                        <div class="form-section">
                            <h3 class="section-title">Change Password</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="new_password" class="form-label">New Password (optional)</label>
                                    <input type="password" class="form-control" name="new_password" id="new_password" placeholder="Leave blank to keep current password">
                                    <div class="password-requirements" id="passwordRequirements">
                                        <div class="requirement">
                                            <span class="requirement-icon" id="lengthIcon">•</span>
                                            <span id="lengthText">At least 8 characters</span>
                                        </div>
                                        <div class="requirement">
                                            <span class="requirement-icon" id="uppercaseIcon">•</span>
                                            <span id="uppercaseText">At least 1 uppercase letter</span>
                                        </div>
                                        <div class="requirement">
                                            <span class="requirement-icon" id="lowercaseIcon">•</span>
                                            <span id="lowercaseText">At least 1 lowercase letter</span>
                                        </div>
                                        <div class="requirement">
                                            <span class="requirement-icon" id="numberIcon">•</span>
                                            <span id="numberText">At least 1 number</span>
                                        </div>
                                        <div class="requirement">
                                            <span class="requirement-icon" id="specialIcon">•</span>
                                            <span id="specialText">At least 1 special character</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Re-enter your new password">
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="cropped_image" id="croppedImageData">
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="btn-icon">
                                    <path d="M13.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h11zM2 2a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H2z"/>
                                    <path d="M5.5 6a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1H6a.5.5 0 0 1-.5-.5zM5.5 9a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1H6a.5.5 0 0 1-.5-.5z"/>
                                </svg>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cropper Modal -->
    <div class="modal-overlay" id="cropperModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">Crop Profile Image</h2>
                <button class="modal-close" onclick="closeCropper()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="cropper-container">
                    <div class="cropper-image-container">
                        <img id="cropperImage">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCropper()">Cancel</button>
                <button class="btn btn-primary" onclick="cropImage()">Save Image</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
    <script>
        let cropper;
        const profileInput = document.getElementById('profileImage');
        const cropperModal = document.getElementById('cropperModal');
        const cropperImage = document.getElementById('cropperImage');
        let imagePreview = document.getElementById('imagePreview');
        const croppedImageData = document.getElementById('croppedImageData');
        
        // Password requirements elements
        const passwordInput = document.getElementById('new_password');
        const passwordRequirements = document.getElementById('passwordRequirements');
        const lengthIcon = document.getElementById('lengthIcon');
        const uppercaseIcon = document.getElementById('uppercaseIcon');
        const lowercaseIcon = document.getElementById('lowercaseIcon');
        const numberIcon = document.getElementById('numberIcon');
        const specialIcon = document.getElementById('specialIcon');
        const lengthText = document.getElementById('lengthText');
        const uppercaseText = document.getElementById('uppercaseText');
        const lowercaseText = document.getElementById('lowercaseText');
        const numberText = document.getElementById('numberText');
        const specialText = document.getElementById('specialText');

        // Show password requirements when password input is focused or has value
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            
            if (password.length > 0) {
                passwordRequirements.style.display = 'block';
                
                // Validate each requirement
                const hasLength = password.length >= 8;
                const hasUppercase = /[A-Z]/.test(password);
                const hasLowercase = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSpecial = /[^A-Za-z0-9]/.test(password);
                
                // Update icons and text
                updateRequirement(lengthIcon, lengthText, hasLength, 'At least 8 characters');
                updateRequirement(uppercaseIcon, uppercaseText, hasUppercase, 'At least 1 uppercase letter');
                updateRequirement(lowercaseIcon, lowercaseText, hasLowercase, 'At least 1 lowercase letter');
                updateRequirement(numberIcon, numberText, hasNumber, 'At least 1 number');
                updateRequirement(specialIcon, specialText, hasSpecial, 'At least 1 special character');
            } else {
                passwordRequirements.style.display = 'none';
            }
        });
        
        // Helper function to update requirement display
        function updateRequirement(icon, textElement, isValid, text) {
            if (isValid) {
                icon.textContent = '✓';
                icon.classList.add('valid');
                icon.classList.remove('invalid');
                textElement.textContent = text;
                textElement.classList.add('valid');
                textElement.classList.remove('invalid');
            } else {
                icon.textContent = '•';
                icon.classList.add('invalid');
                icon.classList.remove('valid');
                textElement.textContent = text;
                textElement.classList.add('invalid');
                textElement.classList.remove('valid');
            }
        }

        profileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    cropperImage.src = e.target.result;
                    cropperModal.style.display = 'flex';
                    
                    // Initialize cropper with better defaults
                    cropper = new Cropper(cropperImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        autoCropArea: 0.8,
                        responsive: true,
                        guides: false,
                        center: true,
                        highlight: false,
                        background: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        function cropImage() {
            const canvas = cropper.getCroppedCanvas({ 
                width: 400, 
                height: 400,
                minWidth: 200,
                minHeight: 200,
                maxWidth: 800,
                maxHeight: 800,
                fillColor: '#fff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });
            
            // Create a blob from the canvas
            canvas.toBlob(blob => {
                // Create preview URL
                const previewURL = URL.createObjectURL(blob);
                // Update the preview image
                if (imagePreview && imagePreview.tagName.toLowerCase() === 'img') {
                    imagePreview.src = previewURL;
                } else if (imagePreview) {
                    const img = document.createElement('img');
                    img.src = previewURL;
                    img.className = 'profile-image';
                    img.id = 'imagePreview';
                    imagePreview.replaceWith(img);
                    imagePreview = img;
                }

                // Convert blob to base64 for form submission
                const reader = new FileReader();
                reader.onload = function() {
                    croppedImageData.value = reader.result;
                };
                reader.readAsDataURL(blob);

                // Clean up
                closeCropper();
            }, 'image/png', 0.9); // 0.9 quality
        }

        function closeCropper() {
            cropperModal.style.display = 'none';
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            // Reset file input
            profileInput.value = '';
        }
    </script>
</body>
</html>