<?php
session_start();
require_once "../classes/OrderManager.php";
require_once "../classes/DB.php";

$db = new DB();
$conn = $db->getConnection();
$orderManager = new OrderManager();

// Get completed and cancelled orders
$stmt = $conn->prepare("SELECT * FROM orders WHERE status IN ('Completed', 'Cancelled') ORDER BY created_at DESC");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function highlight($text, $search) {
    if (!$search) return htmlspecialchars($text);
    return preg_replace(
        '/' . preg_quote($search, '/') . '/i',
        '<span style="background:#ffe0b2;">$0</span>',
        htmlspecialchars($text)
    );
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $orders = array_filter($orders, function($order) use ($search) {
        return stripos($order['order_number'], $search) !== false
            || stripos($order['student_name'], $search) !== false
            || stripos($order['class_name'], $search) !== false
            || stripos($order['status'], $search) !== false
            || stripos($order['id'], $search) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order History | Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary-color: #fff3e0;
            --primary-accent: #ffb74d;
            --primary-dark: #fb8c00;
            --secondary-color: rgb(215, 215, 215);
            --text-color: #e65100;
            --text-light: #ff9800;
            --border-color: #ffe0b2;
            --white: #ffffff;
        }

        body {
            background-color: #fafafa;
            color: #333;
            font-family: 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }

        .container {
            max-width: 1100px;
            margin: 30px auto;
            background: var(--white);
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background-color: var(--primary-color);
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .breadcrumb {
            font-size: 16px;
            color: var(--text-light);
        }

        .breadcrumb a {
            color: var(--text-color);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        h2 {
            color: var(--text-color);
            font-size: 22px;
            font-weight: 500;
            margin: 24px 24px 12px;
        }

        .search-form {
            display: flex;
            align-items: center;
            padding: 0 24px 18px;
        }

        .search-form input[type="text"] {
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            width: 300px;
            font-size: 16px;
            margin-right: 8px;
        }

        .search-form button {
            padding: 10px 15px;
            background-color: var(--primary-accent);
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.2s;
        }

        .search-form button:hover {
            background-color: var(--primary-dark);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 16px;
            margin-bottom: 30px;
        }

        th {
            background-color: var(--primary-color);
            color: var(--text-color);
            text-align: left;
            padding: 14px 15px;
            font-weight: 500;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 14px 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        tr:hover {
            background-color: var(--secondary-color);
        }

        .btn {
            display: inline-block;
            padding: 8px 14px;
            font-size: 16px;
            border-radius: 3px;
            text-decoration: none;
            transition: all 0.2s;
            background-color: var(--primary-accent);
            color: white;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background-color: var(--primary-dark);
        }

        .back-link {
            display: inline-block;
            margin: 20px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 16px;
        }

        .back-link:hover {
            color: var(--text-color);
            text-decoration: underline;
        }

        .empty-state {
            padding: 30px 20px;
            text-align: center;
            color: var(--text-light);
            font-size: 16px;
        }

        @media screen and (max-width: 991.98px) {
            .container {
                margin: 20px;
            }
            .search-form input[type="text"] {
                width: 250px;
            }
        }

        @media screen and (max-width: 767.98px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                padding: 15px;
            }
            h2 {
                margin: 15px 15px 8px;
                font-size: 20px;
            }
            .search-form {
                flex-direction: column;
                align-items: stretch;
                padding: 0 15px 15px;
            }
            .search-form input[type="text"] {
                width: 100%;
                margin-right: 0;
                margin-bottom: 10px;
            }
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
            }
            .btn {
                padding: 6px 10px;
                font-size: 14px;
            }
        }

        @media screen and (max-width: 575.98px) {
            .container {
                margin: 10px;
            }
            th, td {
                padding: 10px 12px;
                font-size: 14px;
            }
            .empty-state {
                padding: 20px 15px;
                font-size: 15px;
            }
            .back-link {
                margin: 15px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> / Order History
            </div>
        </div>
        <h2>Order History</h2>
        <form class="search-form" method="GET">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search orders by student name, ID...">
            <button type="submit">Search</button>
        </form>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Order ID</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>View Items</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">No completed or cancelled orders found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $i => $order): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= highlight($order['order_number'] ?? $order['id'], $search) ?></td>
                            <td><?= highlight($order['student_name'] ?? 'Guest', $search) ?></td>
                            <td><?= highlight($order['class_name'] ?? 'Unknown', $search) ?></td>
                            <td><?= highlight('RM' . number_format($order['total_amount'], 2), $search) ?></td>
                            <td><?= highlight($order['status'], $search) ?></td>
                            <td><?= highlight(date('Y-m-d H:i', strtotime($order['created_at'])), $search) ?></td>
                            <td>
                                <a href="order_details.php?id=<?= $order['id'] ?>" class="btn">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</body>
</html>