<?php
require_once "../classes/User.php";
require_once "../classes/DB.php";
require_once "../classes/OrderManager.php";
require '../vendor/autoload.php'; // PhpSpreadsheet
session_start();

use PhpOffice\PhpSpreadsheet\IOFactory;

$db = new DB();
$user = new User($db);
$orderManager = new OrderManager();
$errors = [];
$success = [];

// Upload and process xlsx file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_file'])) {
    $file = $_FILES['student_file']['tmp_name'];
    $fileType = strtolower(pathinfo($_FILES['student_file']['name'], PATHINFO_EXTENSION));

    if ($fileType !== 'xlsx') {
        $errors[] = "Only XLSX files are allowed.";
    } else {
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $header = array_shift($rows); // First row is header
            // Map header names to their column index (case-insensitive, trimmed)
            $headerMap = [];
            foreach ($header as $i => $col) {
                $colKey = strtolower(trim($col));
                $headerMap[$colKey] = $i;
            }

            // Flexible header mapping: Malay/English, case-insensitive, trimmed
            $headerAliases = [
                // English/Malay => DB field
                'student id' => 'student_id',
                'no. pelajar' => 'student_id',
                'name' => 'name',
                'nama pelajar' => 'name',
                'class' => 'class_name',
                'current class' => 'class_name',
                'kelas' => 'class_name',
                'phone' => 'phone',
                'no. telefon' => 'phone',
                'parent name' => 'parent_name',
                'nama ibu bapa' => 'parent_name',
                'parent phone' => 'parent_phone',
                'no. telefon ibu bapa' => 'parent_phone',
                'email' => 'email',
                'emel' => 'email',
                // Optional/extra fields
                'nama dalam bahasa lain' => 'chinese_name',
                'chinese name' => 'chinese_name',
                'jantina' => 'gender',
                'gender' => 'gender',
            ];

            // Required DB fields
            $requiredFields = ['student_id', 'name', 'class_name', 'email'];

            // Map DB fields to header index
            $fieldToIndex = [];
            foreach ($headerAliases as $headerName => $dbField) {
                if (isset($headerMap[$headerName])) {
                    $fieldToIndex[$dbField] = $headerMap[$headerName];
                }
            }

            // Check all required DB fields are present
            $missing = [];
            foreach ($requiredFields as $dbField) {
                if (!isset($fieldToIndex[$dbField])) {
                    $missing[] = $dbField;
                }
            }
            if ($missing) {
                $errors[] = "Missing required columns: " . implode(', ', $missing) . ".";
            } else {
                foreach ($rows as $index => $row) {
                    $rowNumber = $index + 2;
                    $data = [
                        'student_id'    => '',
                        'name'          => '',
                        'class_name'    => '',
                        'phone'         => '',
                        'parent_name'   => '',
                        'parent_phone'  => '',
                        'profile_image' => '',
                        'email'         => '',
                        'chinese_name'  => '',
                        'gender'        => ''
                    ];
                    $hasEmpty = false;
                    foreach ($requiredFields as $dbField) {
                        $idx = $fieldToIndex[$dbField];
                        $value = isset($row[$idx]) ? trim($row[$idx]) : '';
                        if ($value === '') {
                            $hasEmpty = true;
                        }
                        $data[$dbField] = $value;
                    }
                    // Optional/extra fields
                    foreach (['chinese_name', 'gender', 'phone', 'parent_name', 'parent_phone'] as $optField) {
                        if (isset($fieldToIndex[$optField])) {
                            $idx = $fieldToIndex[$optField];
                            $data[$optField] = isset($row[$idx]) ? trim($row[$idx]) : '';
                        }
                    }
                    if ($hasEmpty) {
                        $errors[] = "Row $rowNumber has empty required fields. Skipped.";
                        continue;
                    }
                    $existing = $user->getByStudentId($data['student_id']);
                    if ($existing && $existing['email'] === $data['email']) {
                        if ($existing['class_name'] !== $data['class_name']) {
                            // Update class_name
                            if ($user->updateClassName($data['student_id'], $data['class_name'])) {
                                $success[] = "ℹ️ Row $rowNumber: Class updated for {$data['name']} ({$data['student_id']}) to '{$data['class_name']}'.";
                            } else {
                                $errors[] = "❌ Row $rowNumber: Failed to update class for {$data['name']} ({$data['student_id']}).";
                            }
                        } else {
                            $errors[] = "Student ID '" . $data['student_id'] . "' already exists with same class. Row $rowNumber skipped.";
                        }
                        continue;
                    }
                    if ($user->studentIdExists($data['student_id'])) {
                        $errors[] = "Student ID '" . $data['student_id'] . "' already exists. Row $rowNumber skipped.";
                        continue;
                    }
                    if ($user->emailExists($data['email'])) {
                        $errors[] = "Email '" . $data['email'] . "' already exists. Row $rowNumber skipped.";
                        continue;
                    }
                    if ($user->register($data)) {
                        $success[] = "✅ Row $rowNumber: {$data['name']} ({$data['student_id']}) registered.";
                    } else {
                        $errors[] = "❌ Row $rowNumber: Failed to register {$data['name']}.";
                    }
                }
            }
        } catch (Exception $e) {
            $errors[] = "Failed to read Excel file: " . $e->getMessage();
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
    <title>Import Students | Student Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4CAF50; /* Green */
            --primary-hover: #3e8e41; /* Darker green */
            --secondary: #8BC34A; /* Light green */
            --secondary-hover: #7CB342; /* Darker light green */
            --danger: #f44336; /* Red */
            --warning: #FFC107; /* Amber */
            --info: #009688; /* Teal */
            --light: #F1F8E9; /* Very light green */
            --dark: #2E7D32; /* Dark green */
            --gray-100: #f5f5f5;
            --gray-200: #eeeeee;
            --gray-300: #e0e0e0;
            --gray-400: #bdbdbd;
            --gray-500: #9e9e9e;
            --gray-600: #757575;
            --gray-700: #616161;
            --gray-800: #424242;
            --border-radius: 0.375rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.2s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: var(--light);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-title i {
            color: var(--primary);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 2rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb-separator {
            color: var(--gray-400);
        }

        /* Card */
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background-color: var(--light);
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: var(--secondary);
            color: white;
        }

        .btn-secondary:hover {
            background-color: var(--secondary-hover);
            transform: translateY(-1px);
        }

        .btn-outline {
            background-color: transparent;
            border-color: var(--gray-300);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            background-color: var(--gray-100);
            border-color: var(--gray-400);
        }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            display: block;
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            background-color: white;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        /* File Input */
        .file-input {
            position: relative;
            display: block;
        }

        .file-input input[type="file"] {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.625rem 0.875rem;
            background-color: white;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .file-input-label:hover {
            border-color: var(--gray-400);
        }

        .file-input-text {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-right: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-700);
        }

        .file-input-button {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            background-color: var(--gray-100);
            border-radius: var(--border-radius);
            color: var(--gray-700);
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert-success {
            background-color: #E8F5E9; /* Light green background */
            color: var(--primary);
            border-left: 4px solid var(--primary);
        }

        .alert-info {
            background-color: #E0F7FA; /* Light teal background */
            color: var(--info);
            border-left: 4px solid var(--info);
        }

        .alert-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
        }

        .badge-warning {
            background-color: #FFF8E1; /* Light amber background */
            color: #FF8F00; /* Amber text */
        }

        /* Results Panel */
        .results-panel {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
        }

        .result-item {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.875rem;
        }

        .result-item:last-child {
            border-bottom: none;
        }

        .result-item.success {
            background-color: #E8F5E9; /* Light green background */
            color: var(--primary);
        }

        .result-item.error {
            background-color: #fef2f2;
            color: var(--danger);
        }

        .result-icon {
            font-size: 1rem;
            flex-shrink: 0;
        }

        /* Grid */
        .grid {
            display: grid;
            gap: 2rem;
        }

        .grid-cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .grid-cols-2 {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
        }

        /* Utility Classes */
        .mt-4 { margin-top: 1rem; }
        .mb-4 { margin-bottom: 1rem; }
        .ml-2 { margin-left: 0.5rem; }
        .hidden { display: none; }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .text-gray-500 { color: var(--gray-500); }
        .text-gray-600 { color: var(--gray-600); }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="header-title">
                <i class="fas fa-file-import"></i>
                Import Students
            </h1>
            <div class="header-actions">
                <span class="badge badge-warning">
                    <i class="fas fa-clock mr-1"></i>
                    Pending Orders: <?= $pendingOrdersCount ?>
                </span>
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
                <a href="add_user.php" class="btn btn-outline">
                    <i class="fas fa-user-plus"></i> Add Student
                </a>
            </div>
        </div>

        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <a href="view_user.php">Student Management</a>
            <span class="breadcrumb-separator">/</span>
            <span>Import Students</span>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-2">
            <!-- Import Form -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Upload Excel File</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="student_file" class="form-label">Excel File (.xlsx)</label>
                                <div class="file-input">
                                    <input type="file" id="student_file" name="student_file" class="hidden" required accept=".xlsx">
                                    <label for="student_file" class="file-input-label">
                                        <span class="file-input-text">Choose a file...</span>
                                        <span class="file-input-button">Browse</span>
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload & Process
                            </button>
                        </form>
                    </div>
                </div>

                <!-- File Requirements -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">File Requirements</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-sm text-gray-600 mb-4">To ensure successful import, please follow these guidelines:</p>
                        <ul class="text-sm text-gray-600 space-y-2">
                            <li class="font-medium">• File must be in <span class="text-primary">.xlsx</span> format (Excel 2007 or later)</li>
                            <li class="font-medium">• First row must contain column headers</li>
                            <li class="font-medium">• Required columns (case-insensitive):</li>
                            <ul class="ml-4 mt-2 space-y-1">
                                <li>Student ID / No. Pelajar</li>
                                <li>Name / Nama Pelajar</li>
                                <li>Class / Kelas</li>
                                <li>Email / Emel</li>
                            </ul>
                            <li class="font-medium mt-2">• Optional columns:</li>
                            <ul class="ml-4 mt-2 space-y-1">
                                <li>Phone / No. Telefon</li>
                                <li>Parent Name / Nama Ibu Bapa</li>
                                <li>Parent Phone / No. Telefon Ibu Bapa</li>
                                <li>Chinese Name / Nama dalam bahasa lain</li>
                                <li>Gender / Jantina</li>
                            </ul>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Results Panel -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Import Results</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($errors) && empty($success)): ?>
                            <div class="alert alert-info">
                                <div class="alert-icon">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div>
                                    <p>No file has been processed yet. Upload an Excel file to begin.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="results-panel">
                                <?php foreach ($success as $s): ?>
                                    <div class="result-item success">
                                        <div class="result-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div><?= htmlspecialchars($s) ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($errors as $e): ?>
                                    <div class="result-item error">
                                        <div class="result-icon">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </div>
                                        <div><?= htmlspecialchars($e) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <audio id="orderSound" preload="auto">
        <source src="../assets/sounds/notification.mp3" type="audio/mpeg">
    </audio>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // File input display
        const fileInput = document.getElementById('student_file');
        const fileInputText = document.querySelector('.file-input-text');
        
        if (fileInput && fileInputText) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    fileInputText.textContent = this.files[0].name;
                } else {
                    fileInputText.textContent = 'Choose a file...';
                }
            });
        }

        // Pending orders notification
        let lastPendingCount = <?= $pendingOrdersCount ?> || 0;
        const audio = document.getElementById('orderSound');

        // Unlock audio on first user interaction
        function unlockAudio() {
            audio.play().then(() => {
                audio.pause();
                audio.currentTime = 0;
            }).catch(()=>{});
            window.removeEventListener('click', unlockAudio);
            window.removeEventListener('touchstart', unlockAudio);
        }
        window.addEventListener('click', unlockAudio);
        window.addEventListener('touchstart', unlockAudio);

        function checkNewOrders() {
            fetch('check_pending_orders.php')
                .then(res => res.json())
                .then(data => {
                    if (typeof data.count !== 'undefined') {
                        if (data.count > lastPendingCount) {
                            audio.play().catch(()=>{});
                        }
                        lastPendingCount = data.count;
                        // Update the badge count
                        const badge = document.querySelector('.badge-warning');
                        if (badge) {
                            badge.innerHTML = `<i class="fas fa-clock mr-1"></i> Pending Orders: ${data.count}`;
                        }
                    }
                })
                .catch(() => {});
        }

        setInterval(checkNewOrders, 5000); // Check every 5 seconds
    });
    </script>
</body>
</html>