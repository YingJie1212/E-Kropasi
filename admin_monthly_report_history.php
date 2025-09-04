<?php
session_start();
require_once "../classes/DB.php";

// Only allow admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$pdo = (new DB())->getConnection();

$perPage = 10; // Show 12 months per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = "WHERE status = 'Completed'";
$params = [];

if ($search !== '') {
    $where .= " AND (
        YEAR(created_at) LIKE :searchYear
        OR MONTHNAME(created_at) LIKE :searchMonth
    )";
    $params[':searchYear'] = "%$search%";
    $params[':searchMonth'] = "%$search%";
}

$sql = "
    SELECT DISTINCT YEAR(created_at) AS year, MONTH(created_at) AS month
    FROM orders
    $where
    ORDER BY year DESC, month DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$months = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total months for pagination
$countSql = "
    SELECT COUNT(*) AS total
    FROM (
        SELECT DISTINCT YEAR(created_at), MONTH(created_at)
        FROM orders
        $where
    ) AS months
";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val, PDO::PARAM_STR);
}
$countStmt->execute();
$totalMonths = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalMonths / $perPage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report History | Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-light: #E8F5E9; /* Very light green */
            --primary-color: #4CAF50; /* Green */
            --primary-dark: #388E3C; /* Darker green */
            --secondary-color: #C8E6C9; /* Light green */
            --accent-color: #81C784; /* Medium green accent */
            --text-color: #333;
            --text-light: #666;
            --white: #ffffff;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
            background-color: var(--primary-light);
            padding: 20px;
        }
        
        .admin-container {
            max-width: 1000px;
            margin: 30px auto;
            background: var(--white);
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--secondary-color);
        }
        
        .page-title {
            color: var(--primary-dark);
            font-size: 28px;
            font-weight: 600;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: var(--secondary-color);
            color: var(--primary-dark);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
        }
        
        .back-link:hover {
            background-color: var(--primary-color);
            color: var(--white);
            transform: translateY(-2px);
        }
        
        .search-container {
            background-color: var(--secondary-color);
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid var(--primary-light);
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: var(--transition);
            background-color: var(--white);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }
        
        .search-btn {
            padding: 12px 25px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .clear-btn {
            padding: 12px 25px;
            background-color: var(--accent-color);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .clear-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .month-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .month-card {
            background-color: var(--white);
            border: 1px solid var(--secondary-color);
            border-radius: var(--border-radius);
            padding: 20px;
            transition: var(--transition);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .month-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }
        
        .month-link {
            display: block;
            text-decoration: none;
            color: var(--text-color);
        }
        
        .month-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 5px;
        }
        
        .month-year {
            font-size: 14px;
            color: var(--text-light);
        }
        
        .month-icon {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 50px;
            height: 50px;
            background-color: var(--secondary-color);
            border-radius: 50%;
            margin-bottom: 15px;
            color: var(--primary-dark);
            font-size: 20px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 40px;
        }
        
        .pagination a, 
        .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
        }
        
        .pagination a {
            background-color: var(--secondary-color);
            color: var(--primary-dark);
            transition: var(--transition);
        }
        
        .pagination a:hover {
            background-color: var(--primary-color);
            color: var(--white);
            transform: translateY(-2px);
        }
        
        .pagination span {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            background-color: var(--secondary-color);
            border-radius: var(--border-radius);
            color: var(--text-light);
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            .admin-container {
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .month-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-chart-line"></i> Monthly Report History
        </h1>
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    
    <div class="search-container">
        <form method="get" class="search-form">
            <input type="text" name="search" class="search-input" 
                   value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" 
                   placeholder="Search by year or month...">
            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> Search
            </button>
            <button type="button" class="clear-btn" onclick="window.location.href='admin_monthly_report_history.php'">
                <i class="fas fa-times"></i> Clear
            </button>
        </form>
    </div>
    
    <?php if (!empty($months)): ?>
        <div class="month-list">
            <?php foreach ($months as $row): 
                $monthName = date('F', mktime(0,0,0,$row['month'],1));
                $monthIcon = match($row['month']) {
                    1 => 'fa-snowflake',
                    2 => 'fa-heart',
                    3 => 'fa-seedling',
                    4 => 'fa-umbrella',
                    5 => 'fa-sun',
                    6 => 'fa-water',
                    7 => 'fa-ice-cream',
                    8 => 'fa-sun',
                    9 => 'fa-apple-alt',
                    10 => 'fa-leaf',
                    11 => 'fa-cloud-rain',
                    12 => 'fa-snowman',
                    default => 'fa-calendar'
                };
            ?>
                <div class="month-card">
                    <a href="admin_monthly_report.php?month=<?= $row['month'] ?>&year=<?= $row['year'] ?>" class="month-link">
                        <div class="month-icon">
                            <i class="fas <?= $monthIcon ?>"></i>
                        </div>
                        <div class="month-name"><?= $monthName ?></div>
                        <div class="month-year"><?= $row['year'] ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-results">
            <i class="fas fa-calendar-times fa-2x" style="margin-bottom: 15px;"></i>
            <p>No monthly reports found</p>
        </div>
    <?php endif; ?>
    
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p == $page): ?>
                    <span><?= $p ?></span>
                <?php else: ?>
                    <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>