<?php
// sao/announcements.php - Enhanced announcements management
require_once __DIR__ . '/../data/auth.php';
require_once __DIR__ . '/../data/security.php';

Auth::requireRole(['SAO', 'Super Admin']);
require __DIR__ . '/../shared/header.php';

$message = '';
$messageType = 'success';
$userId = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = getPost('action', 'add');
    
    // Verify CSRF token
    if (!Security::verifyCSRFToken(getPost('csrf_token'))) {
        $message = 'Invalid security token.';
        $messageType = 'error';
    } else {
        switch ($action) {
            case 'add':
                $title = getPost('title');
                $content = getPost('content');
                $priority = getPost('priority', 'medium');
                $isActive = getPost('is_active', 0, 'int');
                
                // Validate input
                $errors = Security::validateInput([
                    'title' => $title,
                    'content' => $content
                ], [
                    'title' => 'required|min:3|max:255',
                    'content' => 'required|min:10'
                ]);
                
                if (!empty($errors)) {
                    $message = 'Please check your input and try again.';
                    $messageType = 'error';
                } else {
                    $result = executeUpdate(
                        "INSERT INTO announcements (title, content, priority, is_active, posted_by) VALUES (?, ?, ?, ?, ?)",
                        [$title, $content, $priority, $isActive, $userId]
                    );
                    
                    if ($result) {
                        ActivityLogger::log($userId, 'CREATE_ANNOUNCEMENT', 'announcements', getLastInsertId());
                        $message = 'Announcement created successfully.';
                    } else {
                        $message = 'Failed to create announcement.';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'edit':
                $announcementId = getPost('announcement_id', 0, 'int');
                $title = getPost('title');
                $content = getPost('content');
                $priority = getPost('priority', 'medium');
                $isActive = getPost('is_active', 0, 'int');
                
                // Validate input
                $errors = Security::validateInput([
                    'title' => $title,
                    'content' => $content
                ], [
                    'title' => 'required|min:3|max:255',
                    'content' => 'required|min:10'
                ]);
                
                if (!empty($errors)) {
                    $message = 'Please check your input and try again.';
                    $messageType = 'error';
                } else {
                    $result = executeUpdate(
                        "UPDATE announcements SET title = ?, content = ?, priority = ?, is_active = ? WHERE id = ?",
                        [$title, $content, $priority, $isActive, $announcementId]
                    );
                    
                    if ($result !== false) {
                        ActivityLogger::log($userId, 'UPDATE_ANNOUNCEMENT', 'announcements', $announcementId);
                        $message = 'Announcement updated successfully.';
                    } else {
                        $message = 'Failed to update announcement.';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete':
                $announcementId = getPost('announcement_id', 0, 'int');
                
                $result = executeUpdate("DELETE FROM announcements WHERE id = ?", [$announcementId]);
                
                if ($result) {
                    ActivityLogger::log($userId, 'DELETE_ANNOUNCEMENT', 'announcements', $announcementId);
                    $message = 'Announcement deleted successfully.';
                } else {
                    $message = 'Failed to delete announcement.';
                    $messageType = 'error';
                }
                break;
                
            case 'toggle_status':
                $announcementId = getPost('announcement_id', 0, 'int');
                
                $result = executeUpdate(
                    "UPDATE announcements SET is_active = NOT is_active WHERE id = ?",
                    [$announcementId]
                );
                
                if ($result !== false) {
                    ActivityLogger::log($userId, 'TOGGLE_ANNOUNCEMENT_STATUS', 'announcements', $announcementId);
                    $message = 'Announcement status updated.';
                } else {
                    $message = 'Failed to update status.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get filter parameters
$status = getGet('status', 'all');
$search = getGet('search');
$priority = getGet('priority', 'all');
$page = getGet('page', 1, 'int');
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = [];
$params = [];

if ($status === 'active') {
    $whereConditions[] = "a.is_active = 1";
} elseif ($status === 'inactive') {
    $whereConditions[] = "a.is_active = 0";
}

if ($priority !== 'all') {
    $whereConditions[] = "a.priority = ?";
    $params[] = $priority;
}

if ($search) {
    $whereConditions[] = "(a.title LIKE ? OR a.content LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$totalQuery = "SELECT COUNT(*) as total FROM announcements a $whereClause";
$totalResult = fetchOne($totalQuery, $params);
$totalAnnouncements = $totalResult['total'];
$totalPages = ceil($totalAnnouncements / $limit);

// Get announcements
$announcementsQuery = "
    SELECT a.*, u.first_name, u.last_name, u.username
    FROM announcements a
    LEFT JOIN users u ON a.posted_by = u.id
    $whereClause
    ORDER BY a.posted_at DESC
    LIMIT $limit OFFSET $offset
";

$announcements = fetchAll($announcementsQuery, $params);

// Get editing announcement if edit mode
$editAnnouncement = null;
if (getGet('edit')) {
    $editId = getGet('edit', 0, 'int');
    $editAnnouncement = fetchOne("SELECT * FROM announcements WHERE id = ?", [$editId]);
}
?>

<div class="page-header">
    <h1><i class="fas fa-bullhorn"></i> Announcements Management</h1>
    <p>Create and manage school announcements</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Add/Edit Announcement Form -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3><i class="fas fa-plus"></i> <?= $editAnnouncement ? 'Edit' : 'Create New' ?> Announcement</h3>
    </div>
    <div class="card-body">
        <form method="POST" class="announcement-form">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="<?= $editAnnouncement ? 'edit' : 'add' ?>">
            <?php if ($editAnnouncement): ?>
                <input type="hidden" name="announcement_id" value="<?= $editAnnouncement['id'] ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label class="form-label" for="title">Title *</label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           class="form-input" 
                           required 
                           value="<?= htmlspecialchars($editAnnouncement['title'] ?? '') ?>"
                           placeholder="Enter announcement title">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="priority">Priority</label>
                    <select id="priority" name="priority" class="form-input form-select">
                        <option value="low" <?= ($editAnnouncement['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= ($editAnnouncement['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= ($editAnnouncement['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" 
                               name="is_active" 
                               value="1" 
                               <?= ($editAnnouncement['is_active'] ?? 1) ? 'checked' : '' ?>>
                        Active
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="content">Content *</label>
                <textarea id="content" 
                          name="content" 
                          class="form-input" 
                          rows="6" 
                          required 
                          placeholder="Enter announcement content..."><?= htmlspecialchars($editAnnouncement['content'] ?? '') ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> 
                    <?= $editAnnouncement ? 'Update' : 'Create' ?> Announcement
                </button>
                <?php if ($editAnnouncement): ?>
                    <a href="/AIMS_ver1/sao/announcements.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="filters-card">
    <form method="GET" class="filters-form">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-input form-select">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-input form-select">
                    <option value="all" <?= $priority === 'all' ? 'selected' : '' ?>>All Priorities</option>
                    <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                </select>
            </div>
            
            <div class="form-group" style="flex: 2;">
                <label class="form-label">Search</label>
                <input type="text" 
                       name="search" 
                       class="form-input" 
                       placeholder="Search announcements..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Announcements List -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Announcements (<?= $totalAnnouncements ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($announcements)): ?>
            <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <h3>No Announcements Found</h3>
                <p>Create your first announcement using the form above.</p>
            </div>
        <?php else: ?>
            <div class="announcements-list">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card priority-<?= $announcement['priority'] ?> <?= $announcement['is_active'] ? '' : 'inactive' ?>">
                        <div class="announcement-header">
                            <div class="announcement-title">
                                <h4><?= htmlspecialchars($announcement['title']) ?></h4>
                                <div class="announcement-badges">
                                    <span class="priority-badge priority-<?= $announcement['priority'] ?>">
                                        <?= ucfirst($announcement['priority']) ?>
                                    </span>
                                    <span class="status-badge <?= $announcement['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $announcement['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="announcement-actions">
                                <a href="?edit=<?= $announcement['id'] ?>" class="action-btn btn-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle status?')">
                                    <?= csrfTokenInput() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                    <button type="submit" class="action-btn btn-toggle" title="Toggle Status">
                                        <i class="fas fa-toggle-<?= $announcement['is_active'] ? 'on' : 'off' ?>"></i>
                                    </button>
                                </form>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this announcement?')">
                                    <?= csrfTokenInput() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                    <button type="submit" class="action-btn btn-delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="announcement-content">
                            <p><?= nl2br(htmlspecialchars($announcement['content'])) ?></p>
                        </div>
                        
                        <div class="announcement-footer">
                            <div class="announcement-meta">
                                <small>
                                    <i class="fas fa-user"></i>
                                    By <?= htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']) ?>
                                    • <i class="fas fa-clock"></i>
                                    <?= date('M j, Y g:i A', strtotime($announcement['posted_at'])) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&priority=<?= urlencode($priority) ?>&search=<?= urlencode($search) ?>" 
                           class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
        /* Action buttons styling */
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        font-size: 0.9rem;
        cursor: pointer;
        border: none;
        outline: none;
        transition: all 0.2s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.08);
    }

    .action-btn i {
        font-size: 1rem;
    }

    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    }

    /* Variants */
    .btn-edit {
        background: #2563eb;
        color: white;
    }
    .btn-edit:hover {
        background: #1e40af;
    }

    .btn-toggle {
        background: #f59e0b;
        color: white;
    }
    .btn-toggle:hover {
        background: #b45309;
    }

    .btn-delete {
        background: #dc2626;
        color: white;
    }
    .btn-delete:hover {
        background: #991b1b;
    }

    .announcement-form .form-row {
        display: flex;
        gap: 1rem;
        align-items: end;
    }
    
    .announcement-card {
        border: 1px solid var(--border-gray);
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        background: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }
    
    .announcement-card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .announcement-card.inactive {
        opacity: 0.7;
        background: #f8f9fa;
    }
    
    .announcement-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .announcement-title h4 {
        margin: 0 0 0.5rem 0;
        color: var(--primary-blue);
    }
    
    .announcement-badges {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    
    .priority-badge, .status-badge {
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
    }
    
    .priority-high { background: #fef2f2; color: #991b1b; }
    .priority-medium { background: #fffbeb; color: #92400e; }
    .priority-low { background: #f0fdf4; color: #166534; }
    
    .status-active { background: #d1fae5; color: #065f46; }
    .status-inactive { background: #f3f4f6; color: #6b7280; }
    
    .announcement-actions {
        display: flex;
        gap: 0.5rem;
        align-items: flex-start;
    }
    
    .announcement-content {
        margin-bottom: 1rem;
        line-height: 1.6;
    }
    
    .announcement-footer {
        border-top: 1px solid var(--border-gray);
        padding-top: 1rem;
    }
    
    .announcement-meta {
        color: var(--text-light);
        font-size: 0.9rem;
    }
    
    .filters-card {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .filters-form .form-row {
        display: flex;
        gap: 1rem;
        align-items: end;
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid var(--border-gray);
    }
    
    .pagination-link {
        padding: 0.5rem 1rem;
        border: 1px solid var(--border-gray);
        border-radius: 6px;
        text-decoration: none;
        color: var(--text-dark);
        transition: all 0.3s ease;
    }
    
    .pagination-link:hover,
    .pagination-link.active {
        background: var(--primary-blue);
        color: white;
        text-decoration: none;
    }
    
    @media (max-width: 768px) {
        .announcement-form .form-row,
        .filters-form .form-row {
            flex-direction: column;
        }
        
        .announcement-header {
            flex-direction: column;
            gap: 1rem;
        }
        
        .announcement-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>

<script>
    // Auto-expand textarea
    document.getElementById('content').addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
    
    // Form validation
    document.querySelector('.announcement-form').addEventListener('submit', function(e) {
        const title = document.getElementById('title').value.trim();
        const content = document.getElementById('content').value.trim();
        
        if (title.length < 3) {
            alert('Title must be at least 3 characters long.');
            e.preventDefault();
            return false;
        }
        
        if (content.length < 10) {
            alert('Content must be at least 10 characters long.');
            e.preventDefault();
            return false;
        }
    });
</script>

<?php require __DIR__ . '/../shared/footer.php'; ?>