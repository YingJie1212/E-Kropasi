<?php
require_once "../classes/LoginLog.php";
require_once "../classes/OrderManager.php";
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$log = new LoginLog();
$entries = $log->getAll();

$orderManager = new OrderManager();
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login History</title>
</head>
<body>
<h2>Login History</h2>
<table border="1" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>User</th>
        <th>Email</th>
        <th>Time</th>
        <th>IP</th>
        <th>User Agent</th>
    </tr>
    <?php foreach ($entries as $e): ?>
        <tr>
            <td><?= $e['id'] ?></td>
            <td><?= htmlspecialchars($e['name']) ?></td>
            <td><?= htmlspecialchars($e['email']) ?></td>
            <td><?= $e['login_time'] ?></td>
            <td><?= $e['ip_address'] ?></td>
            <td><?= htmlspecialchars($e['user_agent']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<script>
window.__pendingOrdersCount = <?= (int)$pendingOrdersCount ?>;
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Start with the current count from the server (optional fallback to 0)
    let lastPendingCount = window.__pendingOrdersCount || 0;
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
                }
            })
            .catch(() => {});
    }

    setInterval(checkNewOrders, 1000); // Check every 1 second
});
</script>
</body>
</html>
