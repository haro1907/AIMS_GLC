<?php
// student/grades.php - Enhanced student grades view
require_once __DIR__ . '/../data/auth.php';
Auth::requireRole(['Student']);
require __DIR__ . '/../shared/header.php';

$userId = $_SESSION['user_id'];

// Get filter parameters
$semester = getGet('semester', 'all');
$schoolYear = getGet('school_year', 'all');
$subject = getGet('subject');

// Build query conditions
$whereConditions = ['user_id = ?'];
$params = [$userId];

if ($semester !== 'all') {
    $whereConditions[] = "semester = ?";
    $params[] = $semester;
}

if ($schoolYear !== 'all') {
    $whereConditions[] = "school_year = ?";
    $params[] = $schoolYear;
}

if ($subject) {
    $whereConditions[] = "subject LIKE ?";
    $params[] = "%$subject%";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get grades
$gradesQuery = "
    SELECT * FROM grades 
    $whereClause 
    ORDER BY school_year DESC, 
             CASE semester 
                WHEN '1st Semester' THEN 1 
                WHEN '2nd Semester' THEN 2 
                WHEN 'Summer' THEN 3 
                ELSE 4 
             END,
             subject ASC
";

$grades = fetchAll($gradesQuery, $params);

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_subjects,
        AVG(grade) as average_grade,
        MAX(grade) as highest_grade,
        MIN(grade) as lowest_grade,
        SUM(CASE WHEN grade >= 95 THEN 1 ELSE 0 END) as excellent_count,
        SUM(CASE WHEN grade >= 85 AND grade < 95 THEN 1 ELSE 0 END) as good_count,
        SUM(CASE WHEN grade >= 75 AND grade < 85 THEN 1 ELSE 0 END) as satisfactory_count,
        SUM(CASE WHEN grade < 75 THEN 1 ELSE 0 END) as needs_improvement_count
    FROM grades 
    $whereClause
";

$stats = fetchOne($statsQuery, $params);

// Get available semesters and school years for filters
$semesters = fetchAll("SELECT DISTINCT semester FROM grades WHERE user_id = ? ORDER BY semester", [$userId]);
$schoolYears = fetchAll("SELECT DISTINCT school_year FROM grades WHERE user_id = ? ORDER BY school_year DESC", [$userId]);

// Group grades by school year and semester
$groupedGrades = [];
foreach ($grades as $grade) {
    $key = $grade['school_year'] . '|' . $grade['semester'];
    if (!isset($groupedGrades[$key])) {
        $groupedGrades[$key] = [
            'school_year' => $grade['school_year'],
            'semester' => $grade['semester'],
            'grades' => [],
            'average' => 0,
            'total_subjects' => 0
        ];
    }
    $groupedGrades[$key]['grades'][] = $grade;
}

// Calculate averages for each group
foreach ($groupedGrades as &$group) {
    $total = 0;
    $count = count($group['grades']);
    foreach ($group['grades'] as $grade) {
        $total += $grade['grade'];
    }
    $group['average'] = $count > 0 ? $total / $count : 0;
    $group['total_subjects'] = $count;
}
?>

<div class="page-header">
    <h1><i class="fas fa-chart-line"></i> My Academic Grades</h1>
    <p>Track your academic performance and progress</p>
</div>

<!-- Academic Statistics -->
<?php if ($stats['total_subjects'] > 0): ?>
    <div class="stats-section">
        <div class="grid grid-4">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--primary-blue);">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $stats['total_subjects'] ?></div>
                    <div class="stat-label">Total Subjects</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: <?= $stats['average_grade'] >= 90 ? 'var(--success)' : ($stats['average_grade'] >= 80 ? 'var(--warning)' : 'var(--error)') ?>;">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($stats['average_grade'], 1) ?>%</div>
                    <div class="stat-label">Overall Average</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--success);">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($stats['highest_grade'], 1) ?>%</div>
                    <div class="stat-label">Highest Grade</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: <?= $stats['lowest_grade'] >= 75 ? 'var(--warning)' : 'var(--error)' ?>;">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($stats['lowest_grade'], 1) ?>%</div>
                    <div class="stat-label">Lowest Grade</div>
                </div>
            </div>
        </div>
        
        <!-- Grade Distribution -->
        <div class="grade-distribution">
            <h3><i class="fas fa-chart-pie"></i> Grade Distribution</h3>
            <div class="distribution-bars">
                <div class="distribution-item">
                    <div class="distribution-label">Excellent (95-100)</div>
                    <div class="distribution-bar">
                        <div class="distribution-fill excellent" 
                             style="width: <?= $stats['total_subjects'] > 0 ? ($stats['excellent_count'] / $stats['total_subjects'] * 100) : 0 ?>%;"></div>
                    </div>
                    <div class="distribution-value"><?= $stats['excellent_count'] ?></div>
                </div>
                
                <div class="distribution-item">
                    <div class="distribution-label">Good (85-94)</div>
                    <div class="distribution-bar">
                        <div class="distribution-fill good" 
                             style="width: <?= $stats['total_subjects'] > 0 ? ($stats['good_count'] / $stats['total_subjects'] * 100) : 0 ?>%;"></div>
                        </div>
                    </div>
                <div class="distribution-value"><?= $stats['good_count'] ?></div>
                </div>
                
                <div class="distribution-item">
                    <div class="distribution-label">Satisfactory (75-84)</div>
                    <div class="distribution-bar">
                        <div class="distribution-fill satisfactory" 
                             style="width: <?= $stats['total_subjects'] > 0 ? ($stats['satisfactory_count'] / $stats['total_subjects'] * 100) : 0 ?>%;"></div>
                    </div>
                    <div class="distribution-value"><?= $stats['satisfactory_count'] ?></div>
                </div>
                
                <div class="distribution-item">
                    <div class="distribution-label">Needs Improvement (&lt;75)</div>
                    <div class="distribution-bar">
                        <div class="distribution-fill needs-improvement" 
                             style="width: <?= $stats['total_subjects'] > 0 ? ($stats['needs_improvement_count'] / $stats['total_subjects'] * 100) : 0 ?>%;"></div>
                    </div>
                    <div class="distribution-value"><?= $stats['needs_improvement_count'] ?></div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-info-circle"></i>
        <p>No grades available yet</p>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="filters" style="margin: 2rem 0;">
    <form method="get" class="filter-form">
        <select name="semester">
            <option value="all" <?= $semester === 'all' ? 'selected' : '' ?>>All Semesters</option>
            <?php foreach ($semesters as $sem): ?>
                <option value="<?= htmlspecialchars($sem['semester']) ?>" <?= $semester === $sem['semester'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sem['semester']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="school_year">
            <option value="all" <?= $schoolYear === 'all' ? 'selected' : '' ?>>All School Years</option>
            <?php foreach ($schoolYears as $sy): ?>
                <option value="<?= htmlspecialchars($sy['school_year']) ?>" <?= $schoolYear === $sy['school_year'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sy['school_year']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="subject" placeholder="Search subject..." value="<?= htmlspecialchars($subject) ?>">

        <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Apply</button>
    </form>
</div>

<!-- Grouped Grades -->
<?php foreach ($groupedGrades as $group): ?>
    <div class="grade-group">
        <h3><?= htmlspecialchars($group['school_year']) ?> - <?= htmlspecialchars($group['semester']) ?></h3>
        <table class="grades-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Grade</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($group['grades'] as $grade): ?>
                    <tr>
                        <td><?= htmlspecialchars($grade['subject']) ?></td>
                        <td><?= number_format($grade['grade'], 1) ?>%</td>
                        <td>
                            <?php if ($grade['grade'] >= 75): ?>
                                <span class="status-pass">Passed</span>
                            <?php else: ?>
                                <span class="status-fail">Failed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Average</strong></td>
                    <td colspan="2"><?= number_format($group['average'], 1) ?>% (<?= $group['total_subjects'] ?> subjects)</td>
                </tr>
            </tfoot>
        </table>
    </div>
<?php endforeach; ?>

<style>
    .stats-section {
        margin-bottom: 2rem;
    }
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .stat-icon {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        display: flex;
        justify-content: center;
        align-items: center;
        color: white;
        font-size: 1.2rem;
    }
    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-blue);
    }
    .grade-distribution {
        margin-top: 2rem;
    }
    .distribution-item {
        display: flex;
        align-items: center;
        margin-bottom: 0.8rem;
        gap: 0.5rem;
    }
    .distribution-label {
        flex: 1;
    }
    .distribution-bar {
        flex: 3;
        height: 10px;
        background: #eee;
        border-radius: 6px;
        overflow: hidden;
    }
    .distribution-fill {
        height: 100%;
    }
    .distribution-fill.excellent { background: var(--success); }
    .distribution-fill.good { background: var(--primary-blue); }
    .distribution-fill.satisfactory { background: var(--warning); }
    .distribution-fill.needs-improvement { background: var(--error); }
    .distribution-value {
        width: 40px;
        text-align: right;
        font-weight: 600;
    }
    .filters {
        background: white;
        padding: 1rem;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .filter-form {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .filter-form select, 
    .filter-form input {
        padding: 0.5rem;
        border: 1px solid #ccc;
        border-radius: 6px;
    }
    .grade-group {
        margin-bottom: 2rem;
        background: white;
        padding: 1rem;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .grades-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }
    .grades-table th, 
    .grades-table td {
        padding: 0.8rem;
        border-bottom: 1px solid #eee;
    }
    .status-pass {
        color: var(--success);
        font-weight: 600;
    }
    .status-fail {
        color: var(--error);
        font-weight: 600;
    }
</style>

<?php require __DIR__ . '/../shared/footer.php'; ?>

                    