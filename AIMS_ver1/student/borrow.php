<?php
// student/borrow.php - Student borrowing system
require_once __DIR__ . '/../data/auth.php';
require_once __DIR__ . '/../data/security.php';

Auth::requireRole(['Student']);
require __DIR__ . '/../shared/header.php';

$message = '';
$messageType = 'success';
$userId = $_SESSION['user_id'];

// Handle borrow request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken(getPost('csrf_token'))) {
        $message = 'Invalid security token.';
        $messageType = 'error';
    } else {
        $itemId = getPost('item_id', 0, 'int');
        $quantity = getPost('quantity', 1, 'int');
        $returnDate = getPost('return_date');
        $purpose = getPost('purpose');
        
        // Validate input
        $errors = Security::validateInput([
            'item_id' => $itemId,
            'quantity' => $quantity,
            'return_date' => $returnDate,
            'purpose' => $purpose
        ], [
            'item_id' => 'required|numeric',
            'quantity' => 'required|numeric',
            'return_date' => 'required',
            'purpose' => 'required|min:10|max:500'
        ]);
        
        if (empty($errors)) {
            // Check if item exists and is borrowable
            $item = fetchOne("SELECT * FROM inventory_items WHERE id = ? AND is_borrowable = 1", [$itemId]);
            
            if (!$item) {
                $message = 'Item not found or not available for borrowing.';
                $messageType = 'error';
            } elseif ($item['quantity_available'] < $quantity) {
                $message = 'Requested quantity not available. Only ' . $item['quantity_available'] . ' items available.';
                $messageType = 'error';
            } elseif (strtotime($returnDate) <= time()) {
                $message = 'Return date must be in the future.';
                $messageType = 'error';
            } else {
                // Check for existing pending requests for the same item
                $existingRequest = fetchOne(
                    "SELECT id FROM borrow_transactions WHERE borrower_id = ? AND item_id = ? AND status IN ('pending', 'approved')",
                    [$userId, $itemId]
                );
                
                if ($existingRequest) {
                    $message = 'You already have a pending request for this item.';
                    $messageType = 'error';
                } else {
                    // Create borrow request
                    $result = executeUpdate(
                        "INSERT INTO borrow_transactions (item_id, borrower_id, quantity, expected_return_date, purpose, status) VALUES (?, ?, ?, ?, ?, 'pending')",
                        [$itemId, $userId, $quantity, $returnDate, $purpose]
                    );
                    
                    if ($result) {
                        ActivityLogger::log($userId, 'CREATE_BORROW_REQUEST', 'borrow_transactions', getLastInsertId());
                        $message = 'Borrow request submitted successfully. Please wait for approval.';
                    } else {
                        $message = 'Failed to submit borrow request.';
                        $messageType = 'error';
                    }
                }
            }
        } else {
            $message = 'Please check your input and try again.';
            $messageType = 'error';
        }
    }
}

// Handle request cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $requestId = (int)$_GET['cancel'];
    
    // Verify the request belongs to the current user and is cancellable
    $request = fetchOne(
        "SELECT * FROM borrow_transactions WHERE id = ? AND borrower_id = ? AND status IN ('pending', 'approved')",
        [$requestId, $userId]
    );
    
    if ($request) {
        $result = executeUpdate("UPDATE borrow_transactions SET status = 'cancelled' WHERE id = ?", [$requestId]);
        
        if ($result) {
            // If it was approved, return the quantity to available stock
            if ($request['status'] === 'approved') {
                executeUpdate(
                    "UPDATE inventory_items SET quantity_available = quantity_available + ?, quantity_borrowed = quantity_borrowed - ? WHERE id = ?",
                    [$request['quantity'], $request['quantity'], $request['item_id']]
                );
            }
            
            ActivityLogger::log($userId, 'CANCEL_BORROW_REQUEST', 'borrow_transactions', $requestId);
            $message = 'Request cancelled successfully.';
        } else {
            $message = 'Failed to cancel request.';
            $messageType = 'error';
        }
    }
}

// Get search parameters
$search = getGet('search');
$category = getGet('category');
$page = getGet('page', 1, 'int');
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query for available items
$whereConditions = ['is_borrowable = 1', 'quantity_available > 0'];
$params = [];

if ($search) {
    $whereConditions[] = "(item_name LIKE ? OR item_code LIKE ? OR description LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($category) {
    $whereConditions[] = "category = ?";
    $params[] = $category;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count
$totalQuery = "SELECT COUNT(*) as total FROM inventory_items $whereClause";
$totalResult = fetchOne($totalQuery, $params);
$totalItems = $totalResult['total'];
$totalPages = ceil($totalItems / $limit);

// Get available items
$itemsQuery = "SELECT * FROM inventory_items $whereClause ORDER BY item_name ASC LIMIT $limit OFFSET $offset";
$availableItems = fetchAll($itemsQuery, $params);

// Get categories
$categories = fetchAll("SELECT DISTINCT category FROM inventory_items WHERE is_borrowable = 1 ORDER BY category");

// Get user's borrow history
$userRequests = fetchAll("
    SELECT bt.*, ii.item_name, ii.item_code, ii.category
    FROM borrow_transactions bt
    JOIN inventory_items ii ON bt.item_id = ii.id
    WHERE bt.borrower_id = ?
    ORDER BY bt.created_at DESC
    LIMIT 10
", [$userId]);

// Get statistics for user
$userStats = fetchOne("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as active_borrows,
        SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as completed_returns
    FROM borrow_transactions 
    WHERE borrower_id = ?
", [$userId]);
?>

<div class="page-header">
    <h1><i class="fas fa-hand-holding"></i> Borrow Items</h1>
    <p>Request to borrow school equipment and materials</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- User Statistics -->
<div class="grid grid-4" style="margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-value"><?= $userStats['total_requests'] ?></div>
        <div class="stats-label">Total Requests</div>
    </div>
    <div class="stats-card" style="border-left-color: var(--warning);">
        <div class="stats-value"><?= $userStats['pending_requests'] ?></div>
        <div class="stats-label">Pending</div>
    </div>
    <div class="stats-card" style="border-left-color: var(--success);">
        <div class="stats-value"><?= $userStats['active_borrows'] ?></div>
        <div class="stats-label">Active Borrows</div>
    </div>
    <div class="stats-card" style="border-left-color: var(--light-blue);">
        <div class="stats-value"><?= $userStats['completed_returns'] ?></div>
        <div class="stats-label">Completed</div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs" style="margin-bottom: 2rem;">
    <button class="tab-btn active" onclick="showTab('browse-tab', this)">
        <i class="fas fa-search"></i> Browse Items
    </button>
    <button class="tab-btn" onclick="showTab('history-tab', this)">
        <i class="fas fa-history"></i> My Requests
    </button>
</div>

<!-- Browse Items Tab -->
<div id="browse-tab" class="tab-content">
    <!-- Search and Filters -->
    <div class="search-filters" style="background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <form method="GET" class="grid grid-3" style="align-items: end;">
            <div>
                <label class="form-label">Search Items</label>
                <input type="text" name="search" class="form-input" placeholder="Search by name or code..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div>
                <label class="form-label">Category</label>
                <select name="category" class="form-input form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category === $cat['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </form>
    </div>

    <!-- Available Items Grid -->
    <div class="items-grid">
        <?php if (empty($availableItems)): ?>
            <div style="text-align: center; padding: 3rem; color: var(--text-light);">
                <i class="fas fa-box-open" style="font-size: 4rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
                <h3>No items available</h3>
                <p>No items match your search criteria or all items are currently unavailable.</p>
            </div>
        <?php else: ?>
            <?php foreach ($availableItems as $item): ?>
                <div class="item-card">
                    <div class="item-header">
                        <h3 class="item-name"><?= htmlspecialchars($item['item_name']) ?></h3>
                        <span class="item-code"><?= htmlspecialchars($item['item_code']) ?></span>
                    </div>
                    
                    <div class="item-details">
                        <div class="item-category">
                            <i class="fas fa-tag"></i>
                            <?= htmlspecialchars($item['category']) ?>
                        </div>
                        
                        <div class="item-availability">
                            <i class="fas fa-boxes"></i>
                            <?= $item['quantity_available'] ?> available
                        </div>
                        
                        <div class="item-condition">
                            <i class="fas fa-info-circle"></i>
                            <?= htmlspecialchars($item['condition_status']) ?>
                        </div>
                        
                        <?php if ($item['location']): ?>
                            <div class="item-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($item['location']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($item['description']): ?>
                        <div class="item-description">
                            <?= htmlspecialchars($item['description']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="item-actions">
                        <button class="btn btn-primary" onclick="requestBorrow(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name']) ?>', <?= $item['quantity_available'] ?>)">
                            <i class="fas fa-hand-holding"></i> Request Borrow
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top: 2rem; text-align: center;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>" 
                   class="btn <?= $i == $page ? 'btn-primary' : 'btn-secondary' ?>" 
                   style="margin: 0 0.2rem;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- My Requests Tab -->
<div id="history-tab" class="tab-content" style="display: none;">
    <?php if (empty($userRequests)): ?>
        <div style="text-align: center; padding: 3rem; color: var(--text-light);">
            <i class="fas fa-clipboard-list" style="font-size: 4rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
            <h3>No requests yet</h3>
            <p>You haven't made any borrow requests yet.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Request Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userRequests as $request): ?>
                        <tr>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($request['item_name']) ?></strong>
                                    <br><small style="color: var(--text-light);"><?= htmlspecialchars($request['item_code']) ?></small>
                                </div>
                            </td>
                            <td><?= $request['quantity'] ?></td>
                            <td><?= date('M j, Y', strtotime($request['created_at'])) ?></td>
                            <td><?= date('M j, Y', strtotime($request['expected_return_date'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= $request['status'] ?>">
                                    <?= ucfirst($request['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (in_array($request['status'], ['pending', 'approved'])): ?>
                                    <a href="?cancel=<?= $request['id'] ?>" class="btn-sm btn-danger confirm-cancel">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                <?php endif; ?>
                                <button class="btn-sm btn-info" onclick="viewRequestDetails(<?= $request['id'] ?>)">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Borrow Request Modal -->
<div id="borrowModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-hand-holding"></i> Request Borrow</h3>
            <button class="modal-close" onclick="closeModal('borrowModal')">&times;</button>
        </div>
        <form method="POST" id="borrowForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" id="borrowItemId" name="item_id">
            
            <div class="modal-body">
                <div class="item-info" style="background: var(--light-gray); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <h4 id="borrowItemName" style="margin-bottom: 0.5rem;"></h4>
                    <p id="borrowItemAvailable" style="color: var(--text-light); margin: 0;"></p>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="quantity">Quantity *</label>
                    <input type="number" id="quantity" name="quantity" class="form-input" min="1" value="1" required>
                    <small style="color: var(--text-light);">How many items do you need?</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="return_date">Expected Return Date *</label>
                    <input type="date" id="return_date" name="return_date" class="form-input" required>
                    <small style="color: var(--text-light);">When do you plan to return the item(s)?</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="purpose">Purpose *</label>
                    <textarea id="purpose" name="purpose" class="form-input" rows="3" placeholder="Please describe why you need this item..." required></textarea>
                    <small style="color: var(--text-light);">Minimum 10 characters required.</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('borrowModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .items-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
    }
    
    .item-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        border: 1px solid var(--border-gray);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .item-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }
    
    .item-header {
        margin-bottom: 1rem;
        border-bottom: 2px solid var(--accent-yellow);
        padding-bottom: 0.5rem;
    }
    
    .item-name {
        font-size: 1.2rem;
        color: var(--primary-blue);
        margin: 0 0 0.2rem 0;
    }
    
    .item-code {
        font-family: monospace;
        background: var(--light-gray);
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-size: 0.85rem;
        color: var(--text-light);
    }
    
    .item-details {
        margin-bottom: 1rem;
    }
    
    .item-details > div {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        color: var(--text-dark);
    }
    
    .item-details i {
        width: 16px;
        color: var(--accent-yellow);
    }
    
    .item-description {
        background: var(--light-gray);
        padding: 0.8rem;
        border-radius: 6px;
        font-size: 0.9rem;
        color: var(--text-light);
        margin-bottom: 1rem;
        line-height: 1.4;
    }
    
    .item-actions {
        margin-top: 1rem;
    }
    
    .tabs {
        display: flex;
        gap: 0.5rem;
        border-bottom: 2px solid var(--border-gray);
        margin-bottom: 2rem;
    }
    
    .tab-btn {
        padding: 0.8rem 1.5rem;
        border: none;
        background: transparent;
        color: var(--text-light);
        cursor: pointer;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .tab-btn.active {
        background: var(--accent-yellow);
        color: var(--primary-blue);
        font-weight: 600;
    }
    
    .tab-btn:hover:not(.active) {
        background: var(--light-gray);
        color: var(--text-dark);
    }
    
    .status-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 500;
        text-transform: uppercase;
    }
    
    .status-pending { background: #fff3cd; color: #856404; }
    .status-approved { background: #cff4fc; color: #055160; }
    .status-borrowed { background: #d1ecf1; color: #0c5460; }
    .status-returned { background: #d1e7dd; color: #0f5132; }
    .status-overdue { background: #f8d7da; color: #721c24; }
    .status-cancelled { background: #f8f9fa; color: #495057; }
    
    .btn-sm {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
        border-radius: 6px;
        margin-right: 0.3rem;
    }
    
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-gray);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        padding: 1.5rem;
        border-top: 1px solid var(--border-gray);
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-light);
    }
    
    @media (max-width: 768px) {
        .items-grid {
            grid-template-columns: 1fr;
        }
        
        .tabs {
            overflow-x: auto;
        }
        
        .tab-btn {
            white-space: nowrap;
        }
    }
</style>

<script>
    function showTab(tabId, button) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.style.display = 'none';
        });
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected tab and mark button as active
        document.getElementById(tabId).style.display = 'block';
        button.classList.add('active');
    }
    
    function requestBorrow(itemId, itemName, available) {
        document.getElementById('borrowItemId').value = itemId;
        document.getElementById('borrowItemName').textContent = itemName;
        document.getElementById('borrowItemAvailable').textContent = available + ' items available';
        document.getElementById('quantity').max = available;
        
        // Set minimum return date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('return_date').min = tomorrow.toISOString().split('T')[0];
        
        document.getElementById('borrowModal').style.display = 'flex';
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    function viewRequestDetails(requestId) {
        // This would open a modal with detailed information about the request
        alert('Request details for ID: ' + requestId + '\nThis would show full request information, status updates, and any notes.');
    }
    
    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
    
    // Confirm cancel action
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('confirm-cancel') || e.target.closest('.confirm-cancel')) {
            e.preventDefault();
            if (confirm('Are you sure you want to cancel this request?')) {
                window.location.href = e.target.href || e.target.closest('.confirm-cancel').href;
            }
        }
    });
    
    // Form validation
    document.getElementById('borrowForm').addEventListener('submit', function(e) {
        const quantity = parseInt(document.getElementById('quantity').value);
        const maxQuantity = parseInt(document.getElementById('quantity').max);
        const purpose = document.getElementById('purpose').value.trim();
        
        if (quantity > maxQuantity) {
            e.preventDefault();
            alert('Requested quantity exceeds available stock.');
            return;
        }
        
        if (purpose.length < 10) {
            e.preventDefault();
            alert('Purpose must be at least 10 characters long.');
            return;
        }
        
        const returnDate = new Date(document.getElementById('return_date').value);
        const today = new Date();
        
        if (returnDate <= today) {
            e.preventDefault();
            alert('Return date must be in the future.');
            return;
        }
    });
</script>

<?php require __DIR__ . '/../shared/footer.php'; ?>
                