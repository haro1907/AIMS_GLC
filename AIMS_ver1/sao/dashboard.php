<?php
// sao/dashboard.php - SAO Dashboard
require_once __DIR__ . '/../data/auth.php';
Auth::requireRole(['SAO', 'Super Admin']);
require __DIR__ . '/../shared/header.php';

$userId = $_SESSION['user_id'];

// Get statistics for SAO dashboard
$inventoryStats = fetchOne("
    SELECT 
        COUNT(*) as total_items,
        SUM(quantity_total) as total_quantity,
        SUM(quantity_available) as available_quantity,
        SUM(quantity_borrowed) as borrowed_quantity,
        SUM(CASE WHEN quantity_available = 0 THEN 1 ELSE 0 END) as out_of_stock
    FROM inventory_items
");

$borrowStats = fetchOne("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as active_borrows,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_items
    FROM borrow_transactions
");

$announcementStats = fetchOne("
    SELECT 
        COUNT(*) as total_announcements,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_announcements,
        SUM(CASE WHEN posted_by = ? THEN 1 ELSE 0 END) as my_announcements
    FROM announcements
", [$userId]);

// Get recent activities
$recentBorrowRequests = fetchAll("
    SELECT bt.*, ii.item_name, ii.item_code, u.first_name, u.last_name, u.username
    FROM borrow_transactions bt
    JOIN inventory_items ii ON bt.item_id = ii.id
    JOIN users u ON bt.borrower_id = u.id
    WHERE bt.status = 'pending'
    ORDER BY bt.created_at DESC
    LIMIT 5
");

$recentReturns = fetchAll("
    SELECT bt.*, ii.item_name, ii.item_code, u.first_name, u.last_name
    FROM borrow_transactions bt
    JOIN inventory_items ii ON bt.item_id = ii.id
    JOIN users u ON bt.borrower_id = u.id
    WHERE bt.status = 'returned'
    ORDER BY bt.actual_return_date DESC
    LIMIT 5
");

$overdueItems = fetchAll("
    SELECT bt.*, ii.item_name, ii.item_code, u.first_name, u.last_name, u.email,
           DATEDIFF(CURDATE(), bt.expected_return_date) as days_overdue
    FROM borrow_transactions bt
    JOIN inventory_items ii ON bt.item_id = ii.id
    JOIN users u ON bt.borrower_id = u.id
    WHERE bt.status = 'borrowed'
    AND bt.expected_return_date < CURDATE()
    ORDER BY days_overdue DESC
    LIMIT 5
");

$lowStockItems = fetchAll("
    SELECT item_name, item_code, quantity_available, quantity_total, category
    FROM inventory_items
    WHERE quantity_available <= 2 AND quantity_available > 0
    ORDER BY quantity_available ASC
    LIMIT 5
");

// Update overdue status
executeUpdate("
    UPDATE borrow_transactions 
    SET status = 'overdue' 
    WHERE status = 'borrowed' 
    AND expected_return_date < CURDATE()
");
?>

<div class="page-header">
    <h1><i class="fas fa-tachometer-alt"></i> SAO Dashboard</h1>
    <p>Student Affairs Office Management Overview</p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-4" style="margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--primary-blue); color: white;">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $inventoryStats['total_items'] ?? 0 ?></div>
            <div class="stats-label">Total Inventory Items</div>
            <small style="color: var(--text-light);"><?= $inventoryStats['out_of_stock'] ?? 0 ?> out of stock</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--warning); color: white;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $borrowStats['pending_requests'] ?? 0 ?></div>
            <div class="stats-label">Pending Requests</div>
            <small style="color: var(--text-light);">Need approval</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--success); color: white;">
            <i class="fas fa-hand-holding"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $borrowStats['active_borrows'] ?? 0 ?></div>
            <div class="stats-label">Active Borrows</div>
            <small style="color: var(--text-light);">Currently borrowed</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: <?= ($borrowStats['overdue_items'] ?? 0) > 0 ? 'var(--error)' : 'var(--success)' ?>; color: white;">
            <i class="fas fa-<?= ($borrowStats['overdue_items'] ?? 0) > 0 ? 'exclamation-triangle' : 'check-circle' ?>"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $borrowStats['overdue_items'] ?? 0 ?></div>
            <div class="stats-label">Overdue Items</div>
            <small style="color: var(--text-light);">Need follow-up</small>
        </div>
    </div>
</div>

<!-- Alert for Overdue Items -->
<?php if (($borrowStats['overdue_items'] ?? 0) > 0): ?>
    <div class="alert alert-warning" style="margin-bottom: 2rem;">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Attention Required!</strong>
            <p>There are <?= $borrowStats['overdue_items'] ?> overdue items that need immediate attention. 
            <a href="/AIMS_ver1/sao/borrow_requests.php?status=overdue" style="color: inherit; text-decoration: underline;">View overdue items</a></p>
        </div>
    </div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="quick-actions" style="margin-bottom: 2rem;">
    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div class="action-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
        <a href="/AIMS_ver1/sao/borrow_requests.php?status=pending" class="action-card">
            <div class="action-icon" style="background: var(--warning); color: white;">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="action-content">
                <h4>Review Requests</h4>
                <p>Process <?= $borrowStats['pending_requests'] ?? 0 ?> pending borrow requests</p>
            </div>
        </a>
        
        <a href="/AIMS_ver1/sao/inventory.php" class="action-card">
            <div class="action-icon" style="background: var(--primary-blue); color: white;">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="action-content">
                <h4>Manage Inventory</h4>
                <p>Add, edit, or update inventory items</p>
            </div>
        </a>
        
        <a href="/AIMS_ver1/sao/announcements.php" class="action-card">
            <div class="action-icon" style="background: var(--accent-yellow); color: var(--primary-blue);">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div class="action-content">
                <h4>Announcements</h4>
                <p>Create and manage school announcements</p>
            </div>
        </a>
        
        <a href="/AIMS_ver1/sao/reports.php" class="action-card">
            <div class="action-icon" style="background: var(--light-blue); color: white;">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="action-content">
                <h4>Generate Reports</h4>
                <p>View analytics and generate reports</p>
            </div>
        </a>
    </div>
</div>

<!-- Dashboard Content Grid -->
<div class="grid grid-2" style="gap: 2rem; margin-bottom: 2rem;">
    <!-- Pending Borrow Requests -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-clock"></i> Pending Requests</h3>
            <a href="/AIMS_ver1/sao/borrow_requests.php?status=pending" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($recentBorrowRequests)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-check"></i>
                <p>No pending requests</p>
            </div>
        <?php else: ?>
            <div class="request-list">
                <?php foreach ($recentBorrowRequests as $request): ?>
                    <div class="request-item">
                        <div class="request-info">
                            <div class="student-info">
                                <strong><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></strong>
                                <small>(<?= htmlspecialchars($request['username']) ?>)</small>
                            </div>
                            <div class="item-info">
                                <span style="color: var(--primary-blue); font-weight: 500;">
                                    <?= htmlspecialchars($request['item_name']) ?>
                                </span>
                                <small style="color: var(--text-light);">
                                    Qty: <?= $request['quantity'] ?> • 
                                    Due: <?= date('M j', strtotime($request['expected_return_date'])) ?>
                                </small>
                            </div>
                        </div>
                        <div class="request-actions">
                            <a href="/AIMS_ver1/sao/borrow_requests.php#request-<?= $request['id'] ?>" class="btn-sm btn-primary">
                                <i class="fas fa-eye"></i> Review
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Overdue Items -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Overdue Items</h3>
            <a href="/AIMS_ver1/sao/borrow_requests.php?status=overdue" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($overdueItems)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No overdue items</p>
                <small style="color: var(--success);">Great job!</small>
            </div>
        <?php else: ?>
            <div class="overdue-list">
                <?php foreach ($overdueItems as $overdue): ?>
                    <div class="overdue-item">
                        <div class="overdue-info">
                            <div class="student-info">
                                <strong style="color: var(--error);">
                                    <?= htmlspecialchars($overdue['first_name'] . ' ' . $overdue['last_name']) ?>
                                </strong>
                                <small><?= htmlspecialchars($overdue['item_name']) ?></small>
                            </div>
                            <div class="overdue-duration">
                                <span class="overdue-badge">
                                    <?= $overdue['days_overdue'] ?> days overdue
                                </span>
                            </div>
                        </div>
                        <div class="overdue-actions">
                            <a href="mailto:<?= htmlspecialchars($overdue['email']) ?>?subject=Overdue Item Return Notice" class="btn-sm btn-warning">
                                <i class="fas fa-envelope"></i> Email
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Additional Dashboard Sections -->
<div class="grid grid-2" style="gap: 2rem;">
    <!-- Low Stock Items -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-boxes"></i> Low Stock Alert</h3>
            <a href="/AIMS_ver1/sao/inventory.php?filter=low_stock" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($lowStockItems)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>All items well stocked</p>
            </div>
        <?php else: ?>
            <div class="stock-list">
                <?php foreach ($lowStockItems as $item): ?>
                    <div class="stock-item">
                        <div class="stock-info">
                            <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                            <small><?= htmlspecialchars($item['item_code']) ?> • <?= htmlspecialchars($item['category']) ?></small>
                        </div>
                        <div class="stock-level">
                            <span class="stock-badge stock-low">
                                <?= $item['quantity_available'] ?>/<?= $item['quantity_total'] ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Returns -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-undo"></i> Recent Returns</h3>
            <a href="/AIMS_ver1/sao/borrow_requests.php?status=returned" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($recentReturns)): ?>
            <div class="empty-state">
                <i class="fas fa-undo"></i>
                <p>No recent returns</p>
            </div>
        <?php else: ?>
            <div class="return-list">
                <?php foreach ($recentReturns as $return): ?>
                    <div class="return-item">
                        <div class="return-info">
                            <strong><?= htmlspecialchars($return['first_name'] . ' ' . $return['last_name']) ?></strong>
                            <small><?= htmlspecialchars($return['item_name']) ?></small>
                            <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem;">
                                Returned <?= date('M j, Y', strtotime($return['actual_return_date'])) ?>
                            </div>
                        </div>
                        <div class="return-status">
                            <span class="status-badge status-returned">
                                <i class="fas fa-check"></i>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        border-left: 4px solid var(--accent-yellow);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: transform 0.3s ease;
    }
    
    .stats-card:hover {
        transform: translateY(-2px);
    }
    
    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .stats-content {
        flex: 1;
    }
    
    .stats-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-blue);
        line-height: 1;
    }
    
    .stats-label {
        color: var(--text-dark);
        font-weight: 500;
        margin-top: 0.2rem;
    }
    
    .quick-actions h3 {
        color: var(--primary-blue);
        margin-bottom: 1rem;
    }
    
    .action-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        text-decoration: none;
        color: inherit;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        text-decoration: none;
        color: inherit;
    }
    
    .action-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .action-content h4 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--primary-blue);
    }
    
    .action-content p {
        margin: 0.3rem 0 0;
        font-size: 0.9rem;
        color: var(--text-light);
    }
    
    .dashboard-section {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .section-header h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--primary-blue);
    }
    
    .btn-link {
        font-size: 0.9rem;
        color: var(--primary-blue);
        text-decoration: none;
        font-weight: 500;
    }
    
    .btn-link:hover {
        text-decoration: underline;
    }
    
    .empty-state {
        text-align: center;
        padding: 2rem;
        color: var(--text-light);
    }
    
    .empty-state i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    
    .request-item,
    .overdue-item,
    .stock-item,
    .return-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 0;
        border-bottom: 1px solid #eee;
    }
    
    .request-item:last-child,
    .overdue-item:last-child,
    .stock-item:last-child,
    .return-item:last-child {
        border-bottom: none;
    }
    
    .student-info strong {
        font-size: 1rem;
    }
    
    .student-info small {
        display: block;
        font-size: 0.8rem;
        color: var(--text-light);
    }
    
    .overdue-badge {
        background: var(--error);
        color: white;
        padding: 0.2rem 0.6rem;
        border-radius: 8px;
        font-size: 0.8rem;
    }
    
    .stock-badge {
        padding: 0.3rem 0.6rem;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .stock-low {
        background: var(--warning);
        color: white;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        font-size: 0.9rem;
    }
    
    .status-returned {
        background: var(--success);
        color: white;
    }
    
    .btn-sm {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.8rem;
        padding: 0.4rem 0.6rem;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    
    .btn-primary {
        background: var(--primary-blue);
        color: white;
    }
    
    .btn-warning {
        background: var(--warning);
        color: white;
    }
    
    .btn-sm:hover {
        opacity: 0.9;
        text-decoration: none;
    }
</style>

<?php require __DIR__ . '/../shared/footer.php'; ?>
