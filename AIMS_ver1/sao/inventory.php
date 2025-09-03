<?php
// sao/inventory.php - Inventory Management System
require_once __DIR__ . '/../data/auth.php';
require_once __DIR__ . '/../data/security.php';

Auth::requireRole(['SAO', 'Super Admin']);
require __DIR__ . '/../shared/header.php';

$message = '';
$messageType = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = getPost('action');
    
    if (!Security::verifyCSRFToken(getPost('csrf_token'))) {
        $message = 'Invalid security token.';
        $messageType = 'error';
    } else {
        switch ($action) {
            case 'add_item':
                $data = [
                    'item_code' => getPost('item_code', '', 'alphanumeric'),
                    'item_name' => getPost('item_name'),
                    'description' => getPost('description'),
                    'category' => getPost('category'),
                    'quantity_total' => getPost('quantity_total', 0, 'int'),
                    'location' => getPost('location'),
                    'condition_status' => getPost('condition_status'),
                    'purchase_date' => getPost('purchase_date'),
                    'purchase_price' => getPost('purchase_price', 0, 'float'),
                    'is_borrowable' => getPost('is_borrowable', 0, 'int')
                ];
                
                $errors = Security::validateInput($data, [
                    'item_code' => 'required|min:3|max:50',
                    'item_name' => 'required|min:3|max:255',
                    'category' => 'required',
                    'quantity_total' => 'required|numeric'
                ]);
                
                if (empty($errors)) {
                    // Check if item code already exists
                    $existing = fetchOne("SELECT id FROM inventory_items WHERE item_code = ?", [$data['item_code']]);
                    
                    if ($existing) {
                        $message = 'Item code already exists.';
                        $messageType = 'error';
                    } else {
                        $data['quantity_available'] = $data['quantity_total'];
                        
                        $result = executeUpdate(
                            "INSERT INTO inventory_items (item_code, item_name, description, category, quantity_total, quantity_available, location, condition_status, purchase_date, purchase_price, is_borrowable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            array_values($data)
                        );
                        
                        if ($result) {
                            ActivityLogger::log($_SESSION['user_id'], 'ADD_INVENTORY_ITEM', 'inventory_items', getLastInsertId());
                            $message = 'Item added successfully.';
                        } else {
                            $message = 'Failed to add item.';
                            $messageType = 'error';
                        }
                    }
                } else {
                    $message = 'Please check your input and try again.';
                    $messageType = 'error';
                }
                break;
                
            case 'update_item':
                $itemId = getPost('item_id', 0, 'int');
                $data = [
                    'item_name' => getPost('item_name'),
                    'description' => getPost('description'),
                    'category' => getPost('category'),
                    'quantity_total' => getPost('quantity_total', 0, 'int'),
                    'location' => getPost('location'),
                    'condition_status' => getPost('condition_status'),
                    'purchase_date' => getPost('purchase_date'),
                    'purchase_price' => getPost('purchase_price', 0, 'float'),
                    'is_borrowable' => getPost('is_borrowable', 0, 'int')
                ];
                
                // Get current item data
                $currentItem = fetchOne("SELECT * FROM inventory_items WHERE id = ?", [$itemId]);
                
                if ($currentItem) {
                    // Adjust available quantity if total quantity changed
                    $quantityDifference = $data['quantity_total'] - $currentItem['quantity_total'];
                    $newAvailable = $currentItem['quantity_available'] + $quantityDifference;
                    $data['quantity_available'] = max(0, $newAvailable);
                    
                    $result = executeUpdate(
                        "UPDATE inventory_items SET item_name = ?, description = ?, category = ?, quantity_total = ?, quantity_available = ?, location = ?, condition_status = ?, purchase_date = ?, purchase_price = ?, is_borrowable = ? WHERE id = ?",
                        array_merge(array_values($data), [$itemId])
                    );
                    
                    if ($result) {
                        ActivityLogger::log($_SESSION['user_id'], 'UPDATE_INVENTORY_ITEM', 'inventory_items', $itemId);
                        $message = 'Item updated successfully.';
                    } else {
                        $message = 'Failed to update item.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Item not found.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Handle item deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $itemId = (int)$_GET['delete'];
    
    // Check if item has active borrows
    $activeBorrows = fetchOne("SELECT COUNT(*) as count FROM borrow_transactions WHERE item_id = ? AND status IN ('pending', 'approved', 'borrowed')", [$itemId]);
    
    if ($activeBorrows['count'] > 0) {
        $message = 'Cannot delete item with active borrow transactions.';
        $messageType = 'error';
    } else {
        $result = executeUpdate("DELETE FROM inventory_items WHERE id = ?", [$itemId]);
        
        if ($result) {
            ActivityLogger::log($_SESSION['user_id'], 'DELETE_INVENTORY_ITEM', 'inventory_items', $itemId);
            $message = 'Item deleted successfully.';
        } else {
            $message = 'Failed to delete item.';
            $messageType = 'error';
        }
    }
}

// Get search and filter parameters
$search = getGet('search');
$category = getGet('category');
$status = getGet('status');
$page = getGet('page', 1, 'int');
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = [];
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

if ($status === 'available') {
    $whereConditions[] = "quantity_available > 0";
} elseif ($status === 'unavailable') {
    $whereConditions[] = "quantity_available = 0";
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$totalQuery = "SELECT COUNT(*) as total FROM inventory_items $whereClause";
$totalResult = fetchOne($totalQuery, $params);
$totalItems = $totalResult['total'];
$totalPages = ceil($totalItems / $limit);

// Get items
$itemsQuery = "SELECT * FROM inventory_items $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$items = fetchAll($itemsQuery, $params);

// Get categories for filter
$categories = fetchAll("SELECT DISTINCT category FROM inventory_items ORDER BY category");

// Get statistics
$stats = fetchOne("SELECT 
    COUNT(*) as total_items,
    SUM(quantity_total) as total_quantity,
    SUM(quantity_available) as available_quantity,
    SUM(quantity_borrowed) as borrowed_quantity
    FROM inventory_items");
?>

<div class="page-header">
    <h1><i class="fas fa-boxes"></i> Inventory Management</h1>
    <p>Manage school equipment and materials</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-4" style="margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-value"><?= number_format($stats['total_items']) ?></div>
        <div class="stats-label">Total Items</div>
    </div>
    <div class="stats-card">
        <div class="stats-value"><?= number_format($stats['total_quantity']) ?></div>
        <div class="stats-label">Total Quantity</div>
    </div>
    <div class="stats-card">
        <div class="stats-value"><?= number_format($stats['available_quantity']) ?></div>
        <div class="stats-label">Available</div>
    </div>
    <div class="stats-card">
        <div class="stats-value"><?= number_format($stats['borrowed_quantity']) ?></div>
        <div class="stats-label">Borrowed</div>
    </div>
</div>

<!-- Search and Filters -->
<div class="search-filters" style="background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
    <form method="GET" class="grid grid-4" style="align-items: end;">
        <div>
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-input" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>">
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
            <label class="form-label">Status</label>
            <select name="status" class="form-input form-select">
                <option value="">All Status</option>
                <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                <option value="unavailable" <?= $status === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
        </div>
    </form>
</div>

<!-- Action Buttons -->
<div style="margin-bottom: 1.5rem; display: flex; gap: 1rem;">
    <button class="btn btn-secondary" onclick="showAddItemModal()">
        <i class="fas fa-plus"></i> Add New Item
    </button>
    <a href="/AIMS_ver1/sao/inventory_report.php" class="btn btn-primary">
        <i class="fas fa-file-export"></i> Generate Report
    </a>
</div>

<!-- Items Table -->
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Available</th>
                <th>Condition</th>
                <th>Location</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-light); padding: 2rem;">
                        <i class="fas fa-box-open" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
                        No items found
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['item_code']) ?></strong>
                        </td>
                        <td>
                            <div>
                                <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                <?php if ($item['description']): ?>
                                    <br><small style="color: var(--text-light);"><?= htmlspecialchars(substr($item['description'], 0, 50)) ?>...</small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-secondary"><?= htmlspecialchars($item['category']) ?></span>
                        </td>
                        <td>
                            <div style="text-align: center;">
                                <strong><?= $item['quantity_total'] ?></strong>
                            </div>
                        </td>
                        <td>
                            <div style="text-align: center;">
                                <span class="badge <?= $item['quantity_available'] > 0 ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $item['quantity_available'] ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= getConditionBadgeClass($item['condition_status']) ?>">
                                <?= htmlspecialchars($item['condition_status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($item['location']) ?></td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn-icon btn-primary" onclick="editItem(<?= $item['id'] ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon btn-info" onclick="viewItemDetails(<?= $item['id'] ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="?delete=<?= $item['id'] ?>" class="btn-icon btn-danger confirm-delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top: 1.5rem; text-align: center;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>" 
               class="btn <?= $i == $page ? 'btn-primary' : 'btn-secondary' ?>" 
               style="margin: 0 0.2rem;">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<!-- Add Item Modal -->
<div id="addItemModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Add New Item</h3>
            <button class="modal-close" onclick="closeModal('addItemModal')">&times;</button>
        </div>
        <form method="POST" id="addItemForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="add_item">
            
            <div class="modal-body">
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label" for="item_code">Item Code *</label>
                        <input type="text" id="item_code" name="item_code" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="category">Category *</label>
                        <select id="category" name="category" class="form-input form-select" required>
                            <option value="">Select Category</option>
                            <option value="Electronics">Electronics</option>
                            <option value="Furniture">Furniture</option>
                            <option value="Books">Books</option>
                            <option value="Sports Equipment">Sports Equipment</option>
                            <option value="Laboratory Equipment">Laboratory Equipment</option>
                            <option value="Office Supplies">Office Supplies</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="item_name">Item Name *</label>
                    <input type="text" id="item_name" name="item_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-input" rows="3"></textarea>
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label" for="quantity_total">Quantity *</label>
                        <input type="number" id="quantity_total" name="quantity_total" class="form-input" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="condition_status">Condition</label>
                        <select id="condition_status" name="condition_status" class="form-input form-select">
                            <option value="Excellent">Excellent</option>
                            <option value="Good" selected>Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="Damaged">Damaged</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="location">Location</label>
                    <input type="text" id="location" name="location" class="form-input">
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label" for="purchase_date">Purchase Date</label>
                        <input type="date" id="purchase_date" name="purchase_date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="purchase_price">Purchase Price</label>
                        <input type="number" id="purchase_price" name="purchase_price" class="form-input" step="0.01" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_borrowable" value="1" checked>
                        Allow borrowing
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addItemModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Item
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .btn-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-primary { background: var(--primary-blue); color: white; }
    .btn-secondary { background: var(--accent-yellow); color: var(--primary-blue); }
    .btn-info { background: var(--light-blue); color: white; }
    .btn-danger { background: var(--error); color: white; }
    
    .badge {
        padding: 0.3rem 0.6rem;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-danger { background: #fef2f2; color: #991b1b; }
    .badge-warning { background: #fffbeb; color: #92400e; }
    .badge-secondary { background: var(--light-gray); color: var(--text-dark); }
    
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
    
    .grid-4 { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
</style>

<script>
    function showAddItemModal() {
        document.getElementById('addItemModal').style.display = 'flex';
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    function editItem(itemId) {
        // Implementation for edit modal would go here
        alert('Edit functionality would open a modal similar to add item');
    }
    
    function viewItemDetails(itemId) {
        // Implementation for view details modal would go here
        alert('View details functionality would show item details and borrow history');
    }
    
    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
</script>

<?php
function getConditionBadgeClass($condition) {
    switch ($condition) {
        case 'Excellent':
        case 'Good':
            return 'badge-success';
        case 'Fair':
            return 'badge-warning';
        case 'Poor':
        case 'Damaged':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}

require __DIR__ . '/../shared/footer.php';
?>