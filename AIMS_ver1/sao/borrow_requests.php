<?php
// sao/borrow_requests.php - Manage borrow requests
require_once __DIR__ . '/../data/auth.php';
require_once __DIR__ . '/../data/security.php';

Auth::requireRole(['SAO', 'Super Admin']);
require __DIR__ . '/../shared/header.php';

// Generate CSRF Token
function getCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

$message = '';
$messageType = 'success';
$userId = $_SESSION['user_id'];

// Handle request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = getPost('action');
    $requestId = getPost('request_id', 0, 'int');
    
    if (!Security::verifyCSRFToken(getPost('csrf_token'))) {
        $message = 'Invalid security token.';
        $messageType = 'error';
    } else {
        $request = fetchOne("SELECT * FROM borrow_transactions WHERE id = ?", [$requestId]);
        
        if (!$request) {
            $message = 'Request not found.';
            $messageType = 'error';
        } else {
            switch ($action) {
                case 'approve':
                    if ($request['status'] !== 'pending') {
                        $message = 'Request is not in pending status.';
                        $messageType = 'error';
                    } else {
                        $item = fetchOne("SELECT * FROM inventory_items WHERE id = ?", [$request['item_id']]);
                        
                        if ($item['quantity_available'] < $request['quantity']) {
                            $message = 'Insufficient items available. Only ' . $item['quantity_available'] . ' items left.';
                            $messageType = 'error';
                        } else {
                            $updateRequest = executeUpdate(
                                "UPDATE borrow_transactions SET status = 'approved', approved_by = ? WHERE id = ?",
                                [$userId, $requestId]
                            );
                            
                            $updateInventory = executeUpdate(
                                "UPDATE inventory_items 
                                 SET quantity_available = quantity_available - ?, 
                                     quantity_borrowed = quantity_borrowed + ? 
                                 WHERE id = ?",
                                [$request['quantity'], $request['quantity'], $request['item_id']]
                            );
                            
                            if ($updateRequest && $updateInventory) {
                                ActivityLogger::log($userId, 'APPROVE_BORROW_REQUEST', 'borrow_transactions', $requestId);
                                $message = 'Request approved successfully.';
                            } else {
                                $message = 'Failed to approve request.';
                                $messageType = 'error';
                            }
                        }
                    }
                    break;
                
                case 'reject':
                    if ($request['status'] !== 'pending') {
                        $message = 'Request is not in pending status.';
                        $messageType = 'error';
                    } else {
                        $notes = getPost('notes');
                        $result = executeUpdate(
                            "UPDATE borrow_transactions SET status = 'cancelled', notes = ? WHERE id = ?",
                            [$notes, $requestId]
                        );
                        
                        if ($result) {
                            ActivityLogger::log($userId, 'REJECT_BORROW_REQUEST', 'borrow_transactions', $requestId);
                            $message = 'Request rejected.';
                        } else {
                            $message = 'Failed to reject request.';
                            $messageType = 'error';
                        }
                    }
                    break;
                
                case 'mark_borrowed':
                    if ($request['status'] !== 'approved') {
                        $message = 'Request is not in approved status.';
                        $messageType = 'error';
                    } else {
                        $result = executeUpdate(
                            "UPDATE borrow_transactions SET status = 'borrowed', borrow_date = NOW() WHERE id = ?",
                            [$requestId]
                        );
                        
                        if ($result) {
                            ActivityLogger::log($userId, 'MARK_BORROWED', 'borrow_transactions', $requestId);
                            $message = 'Item marked as borrowed.';
                        } else {
                            $message = 'Failed to update status.';
                            $messageType = 'error';
                        }
                    }
                    break;
                
                case 'mark_returned':
                    if ($request['status'] !== 'borrowed') {
                        $message = 'Item is not currently borrowed.';
                        $messageType = 'error';
                    } else {
                        $returnCondition = getPost('return_condition');
                        $returnNotes = getPost('return_notes');
                        
                        $updateRequest = executeUpdate(
                            "UPDATE borrow_transactions 
                             SET status = 'returned', actual_return_date = NOW(), 
                                 return_condition = ?, notes = ? 
                             WHERE id = ?",
                            [$returnCondition, $returnNotes, $requestId]
                        );
                        
                        $updateInventory = executeUpdate(
                            "UPDATE inventory_items 
                             SET quantity_available = quantity_available + ?, 
                                 quantity_borrowed = quantity_borrowed - ? 
                             WHERE id = ?",
                            [$request['quantity'], $request['quantity'], $request['item_id']]
                        );
                        
                        if ($returnCondition === 'Damaged' || $returnCondition === 'Poor') {
                            executeUpdate(
                                "UPDATE inventory_items SET condition_status = ? WHERE id = ?",
                                [$returnCondition, $request['item_id']]
                            );
                        }
                        
                        if ($updateRequest && $updateInventory) {
                            ActivityLogger::log($userId, 'MARK_RETURNED', 'borrow_transactions', $requestId);
                            $message = 'Item marked as returned.';
                        } else {
                            $message = 'Failed to process return.';
                            $messageType = 'error';
                        }
                    }
                    break;
            }
        }
    }
}

// Filters
$status = getGet('status', 'pending');
$search = getGet('search');
$dateFrom = getGet('date_from');
$dateTo = getGet('date_to');
$page = getGet('page', 1, 'int');
$limit = 20;
$offset = ($page - 1) * $limit;

$whereConditions = [];
$params = [];

if ($status && $status !== 'all') {
    $whereConditions[] = "bt.status = ?";
    $params[] = $status;
}

if ($search) {
    $whereConditions[] = "(ii.item_name LIKE ? OR ii.item_code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, array_fill(0, 5, $searchTerm));
}

if ($dateFrom) {
    $whereConditions[] = "DATE(bt.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "DATE(bt.created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$totalQuery = "SELECT COUNT(*) as total 
               FROM borrow_transactions bt 
               JOIN inventory_items ii ON bt.item_id = ii.id 
               JOIN users u ON bt.borrower_id = u.id 
               $whereClause";
$totalResult = fetchOne($totalQuery, $params);
$totalRequests = $totalResult['total'];
$totalPages = ceil($totalRequests / $limit);

$requestsQuery = "
    SELECT bt.*, ii.item_name, ii.item_code, ii.category, 
           u.first_name, u.last_name, u.username, u.email,
           approver.first_name as approver_first_name, 
           approver.last_name as approver_last_name
    FROM borrow_transactions bt
    JOIN inventory_items ii ON bt.item_id = ii.id
    JOIN users u ON bt.borrower_id = u.id
    LEFT JOIN users approver ON bt.approved_by = approver.id
    $whereClause
    ORDER BY bt.created_at DESC
    LIMIT $limit OFFSET $offset
";
$requests = fetchAll($requestsQuery, $params);

$stats = fetchOne("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as active_borrows,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_items
    FROM borrow_transactions
");

executeUpdate("
    UPDATE borrow_transactions 
    SET status = 'overdue' 
    WHERE status = 'borrowed' 
    AND expected_return_date < CURDATE()
");
?>

<div class="page-header">
    <h1><i class="fas fa-clipboard-list"></i> Borrow Request Management</h1>
    <p>Review and manage student borrow requests</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Table -->
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Student</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Purpose</th>
                <th>Request Date</th>
                <th>Status</th>
                <th style="text-align:center;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($requests): ?>
            <?php foreach ($requests as $req): ?>
                <tr>
                    <td><?= $req['id'] ?></td>
                    <td><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></td>
                    <td><?= htmlspecialchars($req['item_name']) ?> (<?= htmlspecialchars($req['item_code']) ?>)</td>
                    <td><?= $req['quantity'] ?></td>
                    <td><?= htmlspecialchars($req['purpose'] ?? '-') ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($req['created_at'])) ?></td>
                    <td><?= ucfirst($req['status']) ?></td>
                    <td style="text-align:center;">
                        <?php if ($req['status'] === 'pending'): ?>
                            <div style="display:flex; gap:8px; justify-content:center;">
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                    <button type="submit" class="btn btn-success btn-sm" style="min-width:100px;">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" style="min-width:100px;">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </div>
                        <?php elseif ($req['status'] === 'approved'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                <input type="hidden" name="action" value="mark_borrowed">
                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-box"></i> Mark Borrowed
                                </button>
                            </form>
                        <?php elseif ($req['status'] === 'borrowed'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                <input type="hidden" name="action" value="mark_returned">
                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="fas fa-undo"></i> Mark Returned
                                </button>
                            </form>
                        <?php else: ?>
                            <em>No actions</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8" style="text-align:center;">No requests found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
