<!--<?php-->
<!--require_once "../classes/OrderManager.php";-->
<!--require_once "../classes/DB.php";-->
<!--session_start();-->

<!--$db = new DB();-->
<!--$conn = $db->getConnection();-->

<!--$optionId = isset($_GET['option_id']) ? (int)$_GET['option_id'] : 0;-->
<!--$search = isset($_GET['search']) ? trim($_GET['search']) : '';-->
<!--$itemsPerPage = 5;-->
<!--$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;-->
<!--$offset = ($page - 1) * $itemsPerPage;-->

<!--$status = isset($_GET['status']) && $_GET['status'] === 'PendingShipping'-->
<!--    ? " AND (status = 'Pending' OR status = 'Shipping')"-->
<!--    : "";-->

// Build base query
<!--$baseQuery = "FROM orders WHERE delivery_option_id = ?";-->
<!--$baseQuery .= $status;-->
<!--$params = [$optionId];-->

<!--if ($search !== '') {-->
<!--    $baseQuery .= " AND (-->
<!--        order_number LIKE ? OR-->
<!--        student_name LIKE ? OR-->
<!--        class_name LIKE ? OR-->
<!--        status LIKE ? OR-->
<!--        id LIKE ?-->
<!--    )";-->
<!--    $searchParam = "%$search%";-->
<!--    $params = array_merge($params, array_fill(0, 5, $searchParam));-->
<!--}-->

<!--$stmt = $conn->prepare("SELECT * $baseQuery ORDER BY created_at DESC LIMIT $offset, $itemsPerPage");-->
<!--$stmt->execute($params);-->
<!--$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);-->

<!--function highlight($text, $search) {-->
<!--    if (!$search) return htmlspecialchars($text);-->
<!--    return preg_replace(-->
<!--        '/' . preg_quote($search, '/') . '/i',-->
<!--        '<span style="background:#ffe0b2;">$0</span>',-->
<!--        htmlspecialchars($text)-->
<!--    );-->
<!--}-->

<!--$stmt = $conn->prepare("SELECT COUNT(*) $baseQuery");-->
<!--$stmt->execute($params);-->
<!--$totalOrders = $stmt->fetchColumn();-->
<!--$totalPages = ceil($totalOrders / $itemsPerPage);-->
<!--$currentPage = $page;-->
<!--$pageVar = http_build_query(array_merge($_GET, ['page' => '']));-->

<!--?>-->
<!--<table data-option-id="<?= $optionId ?>">-->
<!--    <thead>-->
<!--        <tr>-->
<!--            <th>#</th>-->
<!--            <th>Order ID</th>-->
<!--            <th>Student Name</th>-->
<!--            <th>Class Name</th>-->
<!--            <th>Total Amount</th>-->
<!--            <th>Status</th>-->
<!--            <th>Order Date</th>-->
<!--            <th>Actions</th>-->
<!--            <th>Manage</th>-->
<!--        </tr>-->
<!--    </thead>-->
<!--    <tbody>-->
<!--        <?php if (empty($orders)): ?>-->
<!--            <tr>-->
<!--                <td colspan="9" class="empty-state">No orders found for this delivery method.</td>-->
<!--            </tr>-->
<!--        <?php else: ?>-->
<!--            <?php foreach ($orders as $i => $order): ?>-->
<!--                <tr>-->
<!--                    <td><?= (($page - 1) * $itemsPerPage) + $i + 1 ?></td>-->
<!--                    <td><?= highlight($order['order_number'] ?? $order['id'], $search) ?></td>-->
<!--                    <td><?= highlight($order['student_name'] ?? 'Guest', $search) ?></td>-->
<!--                    <td><?= highlight($order['class_name'] ?? 'Unknown', $search) ?></td>-->
<!--                    <td><?= highlight('RM' . number_format($order['total_amount'], 2), $search) ?></td>-->
<!--                    <td><?= highlight($order['status'], $search) ?></td>-->
<!--                    <td><?= highlight(date('Y-m-d H:i', strtotime($order['created_at'])), $search) ?></td>-->
<!--                    <td>-->
<!--                        <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-view">View</a>-->
<!--                    </td>-->
<!--                    <td>-->
<!--                        <?php if ($order['status'] === 'Pending'): ?>-->
<!--                            <form action="update_order_status.php" method="POST" style="display:inline;">-->
<!--                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">-->
<!--                                <button type="submit" class="btn-complete" onclick="return confirm('Mark this order as completed?')">Complete</button>-->
<!--                            </form>-->
<!--                            <form action="update_order_status.php" method="POST" style="display:inline;">-->
<!--                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">-->
<!--                                <input type="hidden" name="new_status" value="Cancelled">-->
<!--                                <button type="submit" class="btn-cancel" style="background:#e57373;color:#fff;margin-left:4px;" onclick="return confirm('Cancel this order?')">Cancel</button>-->
<!--                            </form>-->
<!--                        <?php else: ?>-->
<!--                            <span style="color: var(--text-light);">—</span>-->
<!--                        <?php endif; ?>-->
<!--                    </td>-->
<!--                </tr>-->
<!--            <?php endforeach; ?>-->
<!--        <?php endif; ?>-->
<!--    </tbody>-->
<!--</table>-->
<!--<?php-->
<!--$statusParam = (isset($_GET['status']) && $_GET['status'] === 'PendingShipping') ? '&status=PendingShipping' : '';-->
<!--?>-->
<!--<?php if ($totalPages > 1): ?>-->
<!--    <div class="pagination">-->
<!--        <?php if ($currentPage > 1): ?>-->
<!--            <a href="?<?= $pageVar ?>page=1<?= $statusParam ?>&search=<?= urlencode($search) ?>" class="ajax-page-link" data-option-id="<?= $optionId ?>" data-page="1" data-search="<?= htmlspecialchars($search) ?>">First</a>-->
<!--            <a href="?<?= $pageVar ?>page=<?= $currentPage - 1 ?><?= $statusParam ?>&search=<?= urlencode($search) ?>" class="ajax-page-link" data-option-id="<?= $optionId ?>" data-page="<?= $currentPage - 1 ?>" data-search="<?= htmlspecialchars($search) ?>">Previous</a>-->
<!--        <?php endif; ?>-->

<!--        <?php for ($i = 1; $i <= $totalPages; $i++): ?>-->
<!--            <?php if ($i == $currentPage): ?>-->
<!--                <span class="btn-paginate current"><?= $i ?></span>-->
<!--            <?php else: ?>-->
<!--                <a href="?<?= $pageVar ?>page=<?= $i ?><?= $statusParam ?>&search=<?= urlencode($search) ?>" class="ajax-page-link" data-option-id="<?= $optionId ?>" data-page="<?= $i ?>" data-search="<?= htmlspecialchars($search) ?>"><?= $i ?></a>-->
<!--            <?php endif; ?>-->
<!--        <?php endfor; ?>-->

<!--        <?php if ($currentPage < $totalPages): ?>-->
<!--            <a href="?<?= $pageVar ?>page=<?= $currentPage + 1 ?><?= $statusParam ?>&search=<?= urlencode($search) ?>" class="ajax-page-link" data-option-id="<?= $optionId ?>" data-page="<?= $currentPage + 1 ?>" data-search="<?= htmlspecialchars($search) ?>">Next</a>-->
<!--            <a href="?<?= $pageVar ?>page=<?= $totalPages ?><?= $statusParam ?>&search=<?= urlencode($search) ?>" class="ajax-page-link" data-option-id="<?= $optionId ?>" data-page="<?= $totalPages ?>" data-search="<?= htmlspecialchars($search) ?>">Last</a>-->
<!--        <?php endif; ?>-->
<!--    </div>-->
<!--<?php endif; ?>-->