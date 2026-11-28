<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Teacher');

$pageTitle = 'My Assigned Sections';
$query = $pdo->prepare("SELECT s.section_id, s.section_name, g.grade_level, y.school_year,
        (SELECT COUNT(*) FROM students st WHERE st.current_section_id = s.section_id) AS student_count
    FROM teacher_section_assignments a
    JOIN sections s ON s.section_id = a.section_id AND s.school_year_id = a.school_year_id
    JOIN grade_levels g ON g.grade_level_id = s.grade_level_id
    JOIN school_years y ON y.school_year_id = a.school_year_id AND y.status = 'Active'
    WHERE a.teacher_user_id = :teacher
    ORDER BY g.grade_level, s.section_name");
$query->execute(['teacher' => current_user()['user_id']]);
$sections = $query->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="form-card">
    <h1>My Assigned Sections</h1>
    <?php if (!$sections): ?>
        <p>You have no assigned sections for the active school year.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="portal-row-table" aria-label="Assigned sections">
                <thead>
                    <tr>
                        <th scope="col">Section</th>
                        <th scope="col">Grade</th>
                        <th scope="col">School Year</th>
                        <th scope="col">Students</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sections as $section): ?>
                        <tr>
                            <th scope="row"><?= e($section['section_name']) ?></th>
                            <td><?= e((string) $section['grade_level']) ?></td>
                            <td><?= e($section['school_year']) ?></td>
                            <td><?= (int) $section['student_count'] ?></td>
                            <td><a class="button button-outline" href="<?= BASE_URL ?>/teacher/student_records.php?section=<?= (int) $section['section_id'] ?>">View roster</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
