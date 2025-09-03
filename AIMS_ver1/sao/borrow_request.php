<?php
// sao/borrow_requests.php - Manage borrow requests
require_once __DIR__ . '/../data/auth.php';
require_once __DIR__ . '/../data/security.php';

Auth::requireRole(['SAO', 'Super Admin']);
require __DIR__ . '/../shared/header.php';

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
                        // Check if items are still available
                        $item = fetchOne("SELECT * FROM inventory_items WHERE id = ?", [$request['item_id']]);
                        
                        if ($item['quantity_available'] < $request['quantity']) {
                            $message = 'Insufficient items available. Only ' . $item['quantity_available'] . ' items left.';
                            $messageType = 'error';
                        } else {
                            // Approve the request and update inventory
                            $updateRequest = executeUpdate(
                                "UPDATE borrow_transactions SET status = 'approved', approved_by = ? WHERE id = ?",
                                [$userId, $requestId]
                            );
                            
                            $updateInventory = executeUpdate(
                                "UPDATE inventory_items SET quantity_available = quantity_available - ?, quantity_borrowed = quantity_borrowed + ? WHERE id = ?",
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
                        
                        // Mark as returned
                        $updateRequest = executeUpdate(
                            "UPDATE borrow_transactions SET status = 'returned', actual_return_date = NOW(), return_condition = ?, notes = ? WHERE id = ?",
                            [$returnCondition, $returnNotes, $requestId]
                        );
                        
                        // Return items to inventory
                        $updateInventory = executeUpdate(
                            "UPDATE inventory_items SET quantity_available = quantity_available + ?, quantity_borrowed = quantity_borrowed - ? WHERE id = ?",
                            [$request['quantity'], $request['quantity'], $request['item_id']]
                        );
                        
                        // Update item condition if damaged
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

// Get filter parameters
$status = getGet('status', 'pending');
$search = getGet('search');
$dateFrom = getGet('date_from');
$dateTo = getGet('date_to');
$page = getGet('page', 1, 'int');
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
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

// Get total count
$totalQuery = "SELECT COUNT(*) as total FROM borrow_transactions bt JOIN inventory_items ii ON bt.item_id = ii.id JOIN users u ON bt.borrower_id = u.id $whereClause";
$totalResult = fetchOne($totalQuery, $params);
$totalRequests = $totalResult['total'];
$totalPages = ceil($totalRequests / $limit);

// Get requests
$requestsQuery = "
    SELECT bt.*, ii.item_name, ii.item_code, ii.category, 
           u.first_name, u.last_name, u.username, u.email,
           approver.first_name as approver_first_name, approver.last_name as approver_last_name
    FROM borrow_transactions bt
    JOIN inventory_items ii ON bt.item_id = ii.id
    JOIN users u ON bt.borrower_id = u.id
    LEFT JOIN users approver ON bt.approved_by = approver.id
    $whereClause
    ORDER BY bt.created_at DESC
    LIMIT $limit OFFSET $offset
";

$requests = fetchAll($requestsQuery, $params);

// Get statistics
$stats = fetchOne("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as active_borrows,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_items
    FROM borrow_transactions
");

// Check for overdue items and update status
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

<!-- Statistics Cards -->
<div class="grid grid-5" style="margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-value"><?= $stats['total_requests'] ?></div>
        <div class="stats-label">Total Requests</div>
    </div>
    <div class="stats-card" style="border-left-color: var(--warning);">
        <div class="stats-value"><?= $stats['pending_requests'] ?></div>
        <div class="stats-label">Pending</div>
    </div>
    <div class="stats-card" style="border-left-color: var(--light-blue);">
        <div class="stats-value"><?= $stats['approved_requests'] ?></div>
        <div class="stats-label">Approved</div>
    </div>
    <div class="stats-card" style="border-left-color: var(--success);">
        <div class="stats-value"><?= $stats['active_borrows'] ?></div>
        <div class="stats-label">Active Borrows</div>
    </div>
    <div class="stats-card" style="border-left-color: var(--error);">
        <div class="stats-value"><?= $stats['overdue_items'] ?></div>
        <div class="stats-label">Overdue</div>
    </div>
</div>

<!-- Filters -->
<div class="filters-card" style="background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
    <form method="GET" class="grid grid-5" style="align-items: end;">
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-input form-select">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="borrowed" <?= $status === 'borrowed' ? 'selected' : '' ?>>Borrowed</option>
                <option value="returned" <?= $status === 'returned' ? 'selected' : '' ?>>Returned</option>
                <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div>
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-input" placeholder="Search by item or student..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div>
            <label class="form-label">Date From</label>
            <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div>
            <label class="form-label">Date To</label>
            <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- Quick Actions -->
<div style="margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
    <a href="?status=pending" class="btn <?= $status === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">
        <i class="fas fa-clock"></i> Pending Requests (<?= $stats['pending_requests'] ?>)
    </a>
    <a href="?status=overdue" class="btn <?= $status === 'overdue' ? 'btn-danger' : 'btn-secondary' ?>">
        <i class="fas fa-exclamation-triangle"></i> Overdue Items (<?= $stats['overdue_items'] ?>)
    </a>
    <a href="/AIMS_ver1/sao/overdue_report.php" class="btn btn-warning">
        <i class="fas fa-file-alt"></i> Overdue Report
    </a>
</div>

<!-- Requests Table -->
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Student</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Request Date</th>
                