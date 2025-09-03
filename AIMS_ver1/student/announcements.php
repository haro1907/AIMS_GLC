<?php
// student/announcements.php - Enhanced student announcements view
require_once __DIR__ . '/../data/auth.php';
Auth::requireRole(['Student']);
require __DIR__ . '/../shared/header.php';

// Get filter parameters
$priority = getGet('priority', 'all');
$search = getGet('search');
$page = getGet('page', 1, 'int');
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = ['a.is_active = 1'];
$params = [];

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

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

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
    ORDER BY a.priority DESC, a.posted_at DESC
    LIMIT $limit OFFSET $offset
";

$announcements = fetchAll($announcementsQuery, $params);

// Get priority counts for quick filters
$priorityStats = fetchOne("
    SELECT 
        SUM(CASE WHEN priority = 'high' AND is_active = 1 THEN 1 ELSE 0 END) as high_count,
        SUM(CASE WHEN priority = 'medium' AND is_active = 1 THEN 1 ELSE 0 END) as medium_count,
        SUM(CASE WHEN priority = 'low' AND is_active = 1 THEN 1 ELSE 0 END) as low_count
    FROM announcements
");
?>

<div class="page-header">
    <h1><i class="fas fa-bullhorn"></i> School Announcements</h1>
    <p>Stay updated with the latest news and important information</p>
</div>

<!-- Quick Filters -->
<div class="quick-filters" style="margin-bottom: 2rem;">
    <a href="?priority=all" class="filter-btn <?= $priority === 'all' ? 'active' : '' ?>">
        <i class="fas fa-list"></i> All Announcements (<?= $totalAnnouncements ?>)
    </a>
    <a href="?priority=high" class="filter-btn <?= $priority === 'high' ? 'active' : '' ?> priority-high">
        <i class="fas fa-exclamation-circle"></i> High Priority (<?= $priorityStats['high_count'] ?>)
    </a>
    <a href="?priority=medium" class="filter-btn <?= $priority === 'medium' ? 'active' : '' ?> priority-medium">
        <i class="fas fa-info-circle"></i> Medium Priority (<?= $priorityStats['medium_count'] ?>)
    </a>
    <a href="?priority=low" class="filter-btn <?= $priority === 'low' ? 'active' : '' ?> priority-low">
        <i class="fas fa-check-circle"></i> Low Priority (<?= $priorityStats['low_count'] ?>)
    </a>
</div>

<!-- Search -->
<div class="search-card" style="margin-bottom: 2rem;">
    <form method="GET" class="search-form">
        <input type="hidden" name="priority" value="<?= htmlspecialchars($priority) ?>">
        <div class="search-input-group">
            <input type="text" 
                   name="search" 
                   class="search-input" 
                   placeholder="Search announcements..." 
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>
</div>

<!-- Announcements List -->
<?php if (empty($announcements)): ?>
    <div class="empty-state">
        <i class="fas fa-bullhorn"></i>
        <h3>No Announcements Found</h3>
        <p>There are no announcements matching your criteria at the moment.</p>
    </div>
<?php else: ?>
    <div class="announcements-list">
        <?php foreach ($announcements as $announcement): ?>
            <article class="announcement-card priority-<?= $announcement['priority'] ?>">
                <div class="announcement-header">
                    <div class="announcement-title">
                        <h2><?= htmlspecialchars($announcement['title']) ?></h2>
                        <div class="announcement-badges">
                            <span class="priority-badge priority-<?= $announcement['priority'] ?>">
                                <i class="fas fa-<?= $announcement['priority'] === 'high' ? 'exclamation-circle' : ($announcement['priority'] === 'medium' ? 'info-circle' : 'check-circle') ?>"></i>
                                <?= ucfirst($announcement['priority']) ?> Priority
                            </span>
                            <span class="date-badge">
                                <i class="fas fa-calendar-alt"></i>
                                <?= date('M j, Y', strtotime($announcement['posted_at'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="announcement-content">
                    <div class="content-text">
                        <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                    </div>
                </div>
                
                <div class="announcement-footer">
                    <div class="announcement-meta">
                        <div class="author-info">
                            <i class="fas fa-user-tie"></i>
                            <span>Posted by <strong><?= htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']) ?></strong></span>
                        </div>
                        <div class="time-info">
                            <i class="fas fa-clock"></i>
                            <span><?= date('g:i A', strtotime($announcement['posted_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&priority=<?= urlencode($priority) ?>&search=<?= urlencode($search) ?>" 
                   class="pagination-link">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&priority=<?= urlencode($priority) ?>&search=<?= urlencode($search) ?>" 
                   class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&priority=<?= urlencode($priority) ?>&search=<?= urlencode($search) ?>" 
                   class="pagination-link">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<style>
    .page-header {
        text-align: center;
        margin-bottom: 2rem;
        padding: 2rem;
        background: linear-gradient(135deg, var(--primary-blue), var(--light-blue));
        color: white;
        border-radius: 12px;
    }
    
    .page-header h1 {
        margin-bottom: 0.5rem;
        font-size: 2.5rem;
    }
    
    .page-header p {
        opacity: 0.9;
        font-size: 1.1rem;
    }
    
    .quick-filters {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .filter-btn {
        padding: 0.8rem 1.5rem;
        background: white;
        border: 2px solid var(--border-gray);
        border-radius: 25px;
        text-decoration: none;
        color: var(--text-dark);
        font-weight: 500;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .filter-btn:hover,
    .filter-btn.active {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        text-decoration: none;
    }
    
    .filter-btn.active {
        background: var(--primary-blue);
        color: white;
        border-color: var(--primary-blue);
    }
    
    .filter-btn.priority-high.active {
        background: #dc3545;
        border-color: #dc3545;
    }
    
    .filter-btn.priority-medium.active {
        background: #ffc107;
        border-color: #ffc107;
        color: var(--primary-blue);
    }
    
    .filter-btn.priority-low.active {
        background: #28a745;
        border-color: #28a745;
    }
    
    .search-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    
    .search-input-group {
        display: flex;
        max-width: 500px;
        margin: 0 auto;
    }
    
    .search-input {
        flex: 1;
        padding: 1rem 1.5rem;
        border: 2px solid var(--border-gray);
        border-radius: 25px 0 0 25px;
        border-right: none;
        font-size: 1rem;
        outline: none;
    }
    
    .search-input:focus {
        border-color: var(--accent-yellow);
    }
    
    .search-btn {
        padding: 1rem 1.5rem;
        background: var(--accent-yellow);
        border: 2px solid var(--accent-yellow);
        border-radius: 0 25px 25px 0;
        color: var(--primary-blue);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .search-btn:hover {
        background: var(--warning);
        border-color: var(--warning);
    }
    
    .announcements-list {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .announcement-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
        transition: all 0.3s ease;
        border-left: 4px solid var(--accent-yellow);
    }
    
    .announcement-card.priority-high {
        border-left-color: #dc3545;
    }
    
    .announcement-card.priority-medium {
        border-left-color: #ffc107;
    }
    
    .announcement-card.priority-low {
        border-left-color: #28a745;
    }
    
    .announcement-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .announcement-header {
        padding: 2rem 2rem 1rem;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid var(--border-gray);
    }
    
    .announcement-title h2 {
        color: var(--primary-blue);
        margin-bottom: 1rem;
        font-size: 1.5rem;
        line-height: 1.3;
    }
    
    .announcement-badges {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .priority-badge, .date-badge {
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .priority-badge.priority-high {
        background: #fee;
        color: #dc3545;
    }
    
    .priority-badge.priority-medium {
        background: #fff8e1;
        color: #f57c00;
    }
    
    .priority-badge.priority-low {
        background: #e8f5e8;
        color: #2e7d32;
    }
    
    .date-badge {
        background: var(--light-gray);
        color: var(--text-light);
    }
    
    .announcement-content {
        padding: 2rem;
    }
    
    .content-text {
        font-size: 1.1rem;
        line-height: 1.7;
        color: var(--text-dark);
    }
    
    .announcement-footer {
        padding: 1rem 2rem;
        background: #f8f9fa;
        border-top: 1px solid var(--border-gray);
    }
    
    .announcement-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--text-light);
        font-size: 0.9rem;
    }
    
    .author-info, .time-info {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-light);
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
    }
    
    .empty-state h3 {
        color: var(--text-dark);
        margin-bottom: 1rem;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 3rem;
        padding: 2rem;
    }
    
    .pagination-link {
        padding: 0.8rem 1.2rem;
        background: white;
        border: 2px solid var(--border-gray);
        border-radius: 8px;
        text-decoration: none;
        color: var(--text-dark);
        font-weight: 500;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .pagination-link:hover,
    .pagination-link.active {
        background: var(--primary-blue);
        color: white;
        border-color: var(--primary-blue);
        text-decoration: none;
        transform: translateY(-1px);
    }
    
    @media (max-width: 768px) {
        .page-header h1 {
            font-size: 2rem;
        }
        
        .quick-filters {
            flex-direction: column;
            align-items: center;
        }
        
        .filter-btn {
            width: 100%;
            max-width: 300px;
            justify-content: center;
        }
        
        .announcement-header,
        .announcement-content,
        .announcement-footer {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        .announcement-badges {
            flex-direction: column;
        }
        
        .announcement-meta {
            flex-direction: column;
            gap: 0.5rem;
            text-align: center;
        }
        
        .pagination {
            flex-wrap: wrap;
        }
    }
</style>

<?php require __DIR__ . '/../shared/footer.php'; ?>