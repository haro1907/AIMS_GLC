<?php
// student/dashboard.php - Enhanced student dashboard
require_once __DIR__ . '/../data/auth.php';
Auth::requireRole(['Student']);
require __DIR__ . '/../shared/header.php';

$userId = $_SESSION['user_id'];

// Get student statistics
$gradeStats = fetchOne("
    SELECT 
        COUNT(*) as total_subjects,
        AVG(grade) as average_grade,
        MAX(grade) as highest_grade,
        MIN(grade) as lowest_grade
    FROM grades 
    WHERE user_id = ?
", [$userId]);

$fileStats = fetchOne("
    SELECT 
        COUNT(*) as total_files,
        SUM(file_size) as total_size
    FROM student_files 
    WHERE user_id = ?
", [$userId]);

$borrowStats = fetchOne("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as active_borrows,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_items
    FROM borrow_transactions 
    WHERE borrower_id = ?
", [$userId]);

// Get recent grades
$recentGrades = fetchAll("
    SELECT subject, grade, semester, school_year, created_at
    FROM grades 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
", [$userId]);

// Get recent files
$recentFiles = fetchAll("
    SELECT file_name, file_path, uploaded_at
    FROM student_files 
    WHERE user_id = ? 
    ORDER BY uploaded_at DESC 
    LIMIT 5
", [$userId]);

// Get active borrow requests
$activeBorrows = fetchAll("
    SELECT bt.*, ii.item_name, ii.item_code, ii.category
    FROM borrow_transactions bt
    JOIN inventory_items ii ON bt.item_id = ii.id
    WHERE bt.borrower_id = ? 
    AND bt.status IN ('pending', 'approved', 'borrowed', 'overdue')
    ORDER BY bt.created_at DESC
    LIMIT 5
", [$userId]);

// Get recent announcements
$recentAnnouncements = fetchAll("
    SELECT a.title, a.content, a.posted_at, a.priority, u.first_name, u.last_name
    FROM announcements a
    LEFT JOIN users u ON a.posted_by = u.id
    WHERE a.is_active = 1
    ORDER BY a.posted_at DESC
    LIMIT 3
");

// Calculate file size in readable format
$totalFileSize = $fileStats['total_size'] ?? 0;
$fileSizeFormatted = $totalFileSize > 0 ? Security::formatBytes($totalFileSize) : '0 B';
?>

<div class="page-header">
    <h1><i class="fas fa-tachometer-alt"></i> Student Dashboard</h1>
    <p>Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?>!</p>
</div>

<!-- Quick Stats -->
<div class="grid grid-4" style="margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--success); color: white;">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= number_format($gradeStats['average_grade'] ?? 0, 1) ?>%</div>
            <div class="stats-label">Average Grade</div>
            <small style="color: var(--text-light);"><?= $gradeStats['total_subjects'] ?? 0 ?> subjects</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--light-blue); color: white;">
            <i class="fas fa-folder"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $fileStats['total_files'] ?? 0 ?></div>
            <div class="stats-label">Files Uploaded</div>
            <small style="color: var(--text-light);"><?= $fileSizeFormatted ?> total</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: var(--warning); color: white;">
            <i class="fas fa-hand-holding"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $borrowStats['active_borrows'] ?? 0 ?></div>
            <div class="stats-label">Active Borrows</div>
            <small style="color: var(--text-light);"><?= $borrowStats['pending_requests'] ?? 0 ?> pending</small>
        </div>
    </div>
    
    <div class="stats-card">
        <div class="stats-icon" style="background: <?= ($borrowStats['overdue_items'] ?? 0) > 0 ? 'var(--error)' : 'var(--success)' ?>; color: white;">
            <i class="fas fa-<?= ($borrowStats['overdue_items'] ?? 0) > 0 ? 'exclamation-triangle' : 'check-circle' ?>"></i>
        </div>
        <div class="stats-content">
            <div class="stats-value"><?= $borrowStats['overdue_items'] ?? 0 ?></div>
            <div class="stats-label">Overdue Items</div>
            <small style="color: var(--text-light);">Return ASAP</small>
        </div>
    </div>
</div>

<!-- Overdue Alert -->
<?php if (($borrowStats['overdue_items'] ?? 0) > 0): ?>
    <div class="alert alert-error" style="margin-bottom: 2rem;">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Overdue Items Alert!</strong>
            <p>You have <?= $borrowStats['overdue_items'] ?> overdue item(s). Please return them immediately to avoid penalties.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="quick-actions" style="margin-bottom: 2rem;">
    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div class="action-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
        <a href="/AIMS_ver1/student/grades.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="action-content">
                <h4>View Grades</h4>
                <p>Check your academic performance</p>
            </div>
        </a>
        
        <a href="/AIMS_ver1/student/borrow.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-hand-holding"></i>
            </div>
            <div class="action-content">
                <h4>Borrow Items</h4>
                <p>Request school equipment</p>
            </div>
        </a>
        
        <a href="/AIMS_ver1/student/files.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-folder"></i>
            </div>
            <div class="action-content">
                <h4>My Files</h4>
                <p>Access uploaded documents</p>
            </div>
        </a>
        
        <a href="/AIMS_ver1/student/announcements.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div class="action-content">
                <h4>Announcements</h4>
                <p>Read important notices</p>
            </div>
        </a>
    </div>
</div>

<!-- Dashboard Content Grid -->
<div class="grid grid-2" style="gap: 2rem;">
    <!-- Recent Grades -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-chart-line"></i> Recent Grades</h3>
            <a href="/AIMS_ver1/student/grades.php" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($recentGrades)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <p>No grades available yet</p>
            </div>
        <?php else: ?>
            <div class="grade-list">
                <?php foreach ($recentGrades as $grade): ?>
                    <div class="grade-item">
                        <div class="grade-info">
                            <strong><?= htmlspecialchars($grade['subject']) ?></strong>
                            <div class="grade-details">
                                <small><?= htmlspecialchars($grade['semester']) ?> • <?= htmlspecialchars($grade['school_year']) ?></small>
                            </div>
                        </div>
                        <div class="grade-score <?= $grade['grade'] >= 85 ? 'grade-excellent' : ($grade['grade'] >= 75 ? 'grade-good' : 'grade-needs-improvement') ?>">
                            <?= $grade['grade'] ?>%
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Active Borrow Requests -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-hand-holding"></i> Borrow Status</h3>
            <a href="/AIMS_ver1/student/borrow.php" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($activeBorrows)): ?>
            <div class="empty-state">
                <i class="fas fa-hand-holding"></i>
                <p>No active borrow requests</p>
                <a href="/AIMS_ver1/student/borrow.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Borrow Items
                </a>
            </div>
        <?php else: ?>
            <div class="borrow-list">
                <?php foreach ($activeBorrows as $borrow): ?>
                    <div class="borrow-item">
                        <div class="borrow-info">
                            <strong><?= htmlspecialchars($borrow['item_name']) ?></strong>
                            <div class="borrow-details">
                                <small><?= htmlspecialchars($borrow['item_code']) ?> • Qty: <?= $borrow['quantity'] ?></small>
                                <br><small>Due: <?= date('M j, Y', strtotime($borrow['expected_return_date'])) ?></small>
                            </div>
                        </div>
                        <div class="borrow-status">
                            <span class="status-badge status-<?= $borrow['status'] ?>">
                                <?= ucfirst($borrow['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Files and Announcements -->
<div class="grid grid-2" style="gap: 2rem; margin-top: 2rem;">
    <!-- Recent Files -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-folder"></i> Recent Files</h3>
            <a href="/AIMS_ver1/student/files.php" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($recentFiles)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No files uploaded yet</p>
            </div>
        <?php else: ?>
            <div class="file-list">
                <?php foreach ($recentFiles as $file): ?>
                    <div class="file-item">
                        <div class="file-icon">
                            <i class="fas fa-file-<?= getFileIcon($file['file_name']) ?>"></i>
                        </div>
                        <div class="file-info">
                            <strong><?= htmlspecialchars($file['file_name']) ?></strong>
                            <small>Uploaded <?= date('M j, Y', strtotime($file['uploaded_at'])) ?></small>
                        </div>
                        <a href="<?= htmlspecialchars($file['file_path']) ?>" target="_blank" class="btn-sm btn-primary">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Announcements -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
            <a href="/AIMS_ver1/student/announcements.php" class="btn-link">View All</a>
        </div>
        
        <?php if (empty($recentAnnouncements)): ?>
            <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <p>No recent announcements</p>
            </div>
        <?php else: ?>
            <div class="announcement-list">
                <?php foreach ($recentAnnouncements as $announcement): ?>
                    <div class="announcement-item priority-<?= $announcement['priority'] ?>">
                        <div class="announcement-header">
                            <strong><?= htmlspecialchars($announcement['title']) ?></strong>
                            <span class="priority-badge priority-<?= $announcement['priority'] ?>">
                                <?= ucfirst($announcement['priority']) ?>
                            </span>
                        </div>
                        <div class="announcement-content">
                            <p><?= htmlspecialchars(substr($announcement['content'], 0, 100)) ?>...</p>
                        </div>
                        <div class="announcement-meta">
                            <small>
                                By <?= htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']) ?> • 
                                <?= date('M j, Y', strtotime($announcement['posted_at'])) ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Academic Performance Chart Section -->
<div class="dashboard-section" style="margin-top: 2rem;">
    <div class="section-header">
        <h3><i class="fas fa-chart-area"></i> Academic Performance Overview</h3>
    </div>
    
    <?php if ($gradeStats['total_subjects'] > 0): ?>
        <div class="performance-summary">
            <div class="performance-item">
                <div class="performance-label">Highest Grade</div>
                <div class="performance-value grade-excellent"><?= number_format($gradeStats['highest_grade'], 1) ?>%</div>
            </div>
            <div class="performance-item">
                <div class="performance-label">Average Grade</div>
                <div class="performance-value <?= $gradeStats['average_grade'] >= 85 ? 'grade-excellent' : ($gradeStats['average_grade'] >= 75 ? 'grade-good' : 'grade-needs-improvement') ?>">
                    <?= number_format($gradeStats['average_grade'], 1) ?>%
                </div>
            </div>
            <div class="performance-item">
                <div class="performance-label">Lowest Grade</div>
                <div class="performance-value <?= $gradeStats['lowest_grade'] >= 75 ? 'grade-good' : 'grade-needs-improvement' ?>">
                    <?= number_format($gradeStats['lowest_grade'], 1) ?>%
                </div>
            </div>
            <div class="performance-item">
                <div class="performance-label">Total Subjects</div>
                <div class="performance-value"><?= $gradeStats['total_subjects'] ?></div>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chart-area"></i>
            <p>No grade data available for performance analysis</p>
        </div>
    <?php endif; ?>
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
        background: var(--accent-yellow);
        color: var(--primary-blue);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }
    
    .action-content h4 {
        color: var(--primary-blue);
        margin-bottom: 0.3rem;
    }
    
    .action-content p {
        color: var(--text-light);
        font-size: 0.9rem;
        margin: 0;
    }
    
    .dashboard-section {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        border-top: 3px solid var(--accent-yellow);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-gray);
    }
    
    .section-header h3 {
        color: var(--primary-blue);
        margin: 0;
    }
    
    .btn-link {
        color: var(--accent-yellow);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: color 0.3s ease;
    }
    
    .btn-link:hover {
        color: var(--warning);
        text-decoration: underline;
    }
    
    .empty-state {
        text-align: center;
        padding: 2rem;
        color: var(--text-light);
    }
    
    .empty-state i {
        font-size: 3rem;
        opacity: 0.3;
        margin-bottom: 1rem;
        display: block;
    }
    
    .grade-item, .borrow-item, .file-item, .announcement-item {
        padding: 1rem;
        border-bottom: 1px solid var(--border-gray);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .grade-item:last-child, .borrow-item:last-child, .file-item:last-child, .announcement-item:last-child {
        border-bottom: none;
    }
    
    .grade-score {
        font-size: 1.2rem;
        font-weight: 700;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
    }
    
    .grade-excellent { background: #d1fae5; color: #065f46; }
    .grade-good { background: #dbeafe; color: #1e40af; }
    .grade-needs-improvement { background: #fef3c7; color: #92400e; }
    
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
    
    .priority-badge {
        padding: 0.2rem 0.5rem;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
    }
    
    .priority-high { background: #fef2f2; color: #991b1b; }
    .priority-medium { background: #fffbeb; color: #92400e; }
    .priority-low { background: #f0fdf4; color: #166534; }
    
    .announcement-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .announcement-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .announcement-content {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .announcement-meta {
        width: 100%;
    }
    
    .file-item {
        align-items: center;
    }
    
    .file-icon {
        color: var(--accent-yellow);
        font-size: 1.2rem;
        margin-right: 0.5rem;
    }
    
    .performance-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .performance-item {
        text-align: center;
        padding: 1rem;
        background: var(--light-gray);
        border-radius: 8px;
    }
    
    .performance-label {
        color: var(--text-light);
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }
    
    .performance-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-blue);
    }
    
    @media (max-width: 768px) {
        .grid-2, .grid-4 {
            grid-template-columns: 1fr;
        }
        
        .action-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-card, .action-card {
            flex-direction: column;
            text-align: center;
        }
        
        .performance-summary {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<?php 
function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf': return 'pdf';
        case 'doc':
        case 'docx': return 'word';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif': return 'image';
        case 'txt': return 'text';
        default: return 'file';
    }
}

require __DIR__ . '/../shared/footer.php'; 
?>