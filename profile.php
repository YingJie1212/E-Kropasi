<?php
require_once "../classes/DB.php";
require_once "../classes/User.php";
session_start();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ====== CSS Variables ====== */
        :root {
            --primary-bg: #f8f9fa;
            --card-bg: #ffffff;
            --primary-green: #d1e7dd;
            --accent-green: #2e7d32;
            --light-accent: #a5d6a7;
            --dark-green: #1b5e20;
            --light-gray: #f1f3f4;
            --medium-gray: #e0e0e0;
            --text-muted: #5f6368;
            --border-radius: 12px;
            --border-radius-sm: 6px;
            --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --spacing: 1.5rem;
            --spacing-sm: 1rem;
            --spacing-xs: 0.75rem;
        }

        /* ====== Base Styles ====== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--primary-bg);
            color: #202124;
            line-height: 1.5;
            padding: 0;
            min-height: 100vh;
        }

        /* ====== Typography ====== */
        h1, h2, h3, h4 {
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--dark-green);
        }

        h1 {
            font-size: 1.75rem;
            line-height: 1.3;
        }

        h2 {
            font-size: 1.375rem;
        }

        h3 {
            font-size: 1.125rem;
        }

        p {
            margin-bottom: 1rem;
            color: var(--text-muted);
        }

        /* ====== Layout Components ====== */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing);
        }

        /* ====== Header ====== */
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing);
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-green);
        }

        /* ====== Navigation ====== */
        .btn-back {
            display: inline-flex;
            align-items: center;
            padding: var(--spacing-xs) var(--spacing-sm);
            background-color: var(--card-bg);
            color: var(--accent-green);
            border: 1px solid var(--medium-gray);
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn-back:hover {
            background-color: var(--light-gray);
            border-color: var(--accent-green);
            color: var(--accent-green);
        }

        .btn-back i {
            margin-right: 8px;
            font-size: 0.9em;
        }

        /* ====== Profile Layout ====== */
        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: var(--spacing);
            align-items: start;
        }

        @media (max-width: 992px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ====== Profile Card ====== */
        .profile-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            height: 100%;
        }

        /* ====== Profile Sidebar ====== */
        .profile-sidebar {
            padding: var(--spacing);
            position: relative;
            border-top: 4px solid var(--accent-green);
        }

        .profile-avatar-container {
            width: 150px;
            height: 150px;
            margin: 0 auto var(--spacing-sm);
            position: relative;
        }

        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--light-gray);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            background-color: var(--primary-green);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-green);
            font-size: 3rem;
            font-weight: bold;
        }

        .profile-name {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
            color: var(--dark-green);
            text-align: center;
            font-weight: 600;
        }

        .profile-title {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: var(--spacing);
            font-weight: 500;
            text-align: center;
        }

        .profile-meta {
            margin-top: var(--spacing);
            border-top: 1px solid var(--medium-gray);
            padding-top: var(--spacing);
        }

        .meta-item {
            display: flex;
            align-items: center;
            margin-bottom: var(--spacing-sm);
        }

        .meta-icon {
            width: 36px;
            height: 36px;
            background-color: var(--primary-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: var(--spacing-sm);
            color: var(--accent-green);
        }

        .meta-content {
            flex: 1;
        }

        .meta-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .meta-value {
            font-weight: 600;
            color: var(--dark-green);
        }

        /* ====== Profile Content ====== */
        .profile-content {
            padding: var(--spacing);
        }

        .content-tabs {
            display: flex;
            border-bottom: 1px solid var(--medium-gray);
            margin-bottom: var(--spacing);
        }

        .tab-item {
            padding: var(--spacing-xs) var(--spacing-sm);
            margin-right: var(--spacing-xs);
            cursor: pointer;
            font-weight: 500;
            color: var(--text-muted);
            border-bottom: 2px solid transparent;
            transition: var(--transition);
        }

        .tab-item.active {
            color: var(--accent-green);
            border-bottom-color: var(--accent-green);
        }

        .tab-item:hover:not(.active) {
            color: var(--dark-green);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .info-card {
            background-color: var(--light-gray);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
            border-left: 3px solid var(--accent-green);
        }

        .info-card-header {
            display: flex;
            align-items: center;
            margin-bottom: var(--spacing-xs);
        }

        .info-card-icon {
            margin-right: var(--spacing-xs);
            color: var(--accent-green);
        }

        .info-card-title {
            font-weight: 600;
            color: var(--dark-green);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: var(--spacing-sm);
        }

        .info-item {
            margin-bottom: var(--spacing-xs);
        }

        .info-label {
            font-size: 0.8125rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-weight: 500;
            word-break: break-word;
        }

        /* ====== Action Buttons ====== */
        .action-buttons {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing);
        }

        .btn {
            padding: 0.625rem var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 0.9375rem;
            min-width: 120px;
        }

        .btn i {
            margin-right: 8px;
            font-size: 0.9em;
        }

        .btn-primary {
            background-color: var(--accent-green);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--dark-green);
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(46, 125, 50, 0.3);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
        }

        .btn-outline:hover {
            background-color: rgba(46, 125, 50, 0.08);
            color: var(--dark-green);
            border-color: var(--dark-green);
        }

        /* ====== Responsive Adjustments ====== */
        @media (max-width: 992px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-avatar-container {
                width: 120px;
                height: 120px;
            }
        }

        @media (max-width: 768px) {
            :root {
                --spacing: 1.25rem;
                --spacing-sm: 0.875rem;
            }
            
            .container {
                padding: var(--spacing-sm);
            }
            
            .profile-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-sm);
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-avatar-container {
                width: 100px;
                height: 100px;
            }
            
            .profile-name {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="profile-header">
            <h1 class="header-title">Student Profile</h1>
            <a href="products.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </header>
        
        <div class="profile-grid">
            <!-- Profile Sidebar -->
            <aside class="profile-card">
                <div class="profile-sidebar">
                    <div class="profile-avatar-container">
                        <?php if (!empty($student['profile_image']) && file_exists("../uploads/" . $student['profile_image'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($student['profile_image']) ?>" 
                                 alt="Profile Image" 
                                 class="profile-avatar" 
                                 onerror="this.style.display='none';">
                        <?php else: ?>
                            <div class="profile-avatar"><?= strtoupper(substr($student['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="profile-name"><?= htmlspecialchars($student['name']) ?></h1>
                    <p class="profile-title">Student at <?= htmlspecialchars($student['class_name'] ?? 'School') ?></p>
                    
                    <div class="profile-meta">
                        <div class="meta-item">
                            <div class="meta-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="meta-content">
                                <div class="meta-label">Student ID</div>
                                <div class="meta-value"><?= htmlspecialchars($student['student_id'] ?? '-') ?></div>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="meta-content">
                                <div class="meta-label">Class</div>
                                <div class="meta-value"><?= htmlspecialchars($student['class_name'] ?? '-') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
            
            <!-- Main Profile Content -->
            <main class="profile-card">
                <div class="profile-content">
                    <div class="content-tabs">
                        <div class="tab-item active" data-tab="personal">Personal Information</div>
                        <div class="tab-item" data-tab="parent">Parent Information</div>
                    </div>
                    
                    <div id="personal" class="tab-content active">
                        <div class="info-card">
                            <div class="info-card-header">
                                <i class="fas fa-user info-card-icon"></i>
                                <h3 class="info-card-title">Basic Information</h3>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?= htmlspecialchars($student['name']) ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Student ID</div>
                                    <div class="info-value"><?= htmlspecialchars($student['student_id'] ?? '-') ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Class</div>
                                    <div class="info-value"><?= htmlspecialchars($student['class_name'] ?? '-') ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-card-header">
                                <i class="fas fa-info-circle info-card-icon"></i>
                                <h3 class="info-card-title">Additional Details</h3>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?= htmlspecialchars($student['email'] ?? '-') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="parent" class="tab-content">
                        <div class="info-card">
                            <div class="info-card-header">
                                <i class="fas fa-users info-card-icon"></i>
                                <h3 class="info-card-title">Parent/Guardian Information</h3>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Parent's Name</div>
                                    <div class="info-value"><?= htmlspecialchars($student['parent_name'] ?? '-') ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Parent's Email</div>
                                    <div class="info-value"><?= htmlspecialchars($student['parent_email'] ?? '-') ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Relationship</div>
                                    <div class="info-value"><?= htmlspecialchars($student['parent_relationship'] ?? '-') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="edit_profile.php" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                        <a href="logout.php" class="btn btn-outline">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab-item');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and contents
                    document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>