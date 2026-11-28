
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'Student Information';

$studentId = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$studentId || $studentId < 1) {
    http_response_code(404);
    exit('Student record not found.');
}

// Get student profile and account information.
$sql = "SELECT
            s.*,
            u.username,
            u.email,
            u.account_status,
            g.grade_level,
            sec.section_name
        FROM students s
        INNER JOIN users u ON u.user_id = s.user_id
        LEFT JOIN grade_levels g
            ON g.grade_level_id = s.current_grade_level_id
        LEFT JOIN sections sec
            ON sec.section_id = s.current_section_id
        WHERE s.student_id = :student_id
        LIMIT 1";

$query = $pdo->prepare($sql);
$query->execute(['student_id' => $studentId]);
$student = $query->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    http_response_code(404);
    exit('Student record not found.');
}

// Get parent information.
$query = $pdo->prepare(
    "SELECT * FROM student_parent_information
     WHERE student_id = :student_id"
);
$query->execute(['student_id' => $studentId]);
$parents = $query->fetchAll(PDO::FETCH_ASSOC);

// Get guardian information.
$query = $pdo->prepare(
    "SELECT * FROM student_guardians
     WHERE student_id = :student_id"
);
$query->execute(['student_id' => $studentId]);
$guardians = $query->fetchAll(PDO::FETCH_ASSOC);

// Get enrollment history.
$sql = "SELECT
            ea.*,
            sy.school_year,
            gl.grade_level,
            sec.section_name
        FROM enrollment_applications ea
        LEFT JOIN school_years sy
            ON sy.school_year_id = ea.school_year_id
        LEFT JOIN grade_levels gl
            ON gl.grade_level_id = ea.requested_grade_level_id
        LEFT JOIN sections sec
            ON sec.section_id = ea.assigned_section_id
        WHERE ea.student_id = :student_id
        ORDER BY ea.submitted_at DESC";

$query = $pdo->prepare($sql);
$query->execute(['student_id' => $studentId]);
$enrollments = $query->fetchAll(PDO::FETCH_ASSOC);

// Get academic records.
$sql = "SELECT
            ar.*,
            sub.subject_name,
            sub.subject_code,
            sy.school_year
        FROM academic_records ar
        INNER JOIN subjects sub ON sub.subject_id = ar.subject_id
        INNER JOIN school_years sy ON sy.school_year_id = ar.school_year_id
        WHERE ar.student_id = :student_id
        ORDER BY sy.school_year DESC, ar.grading_period ASC";

$query = $pdo->prepare($sql);
$query->execute(['student_id' => $studentId]);
$grades = $query->fetchAll(PDO::FETCH_ASSOC);

// Get attendance records.
$sql = "SELECT
            a.*,
            sub.subject_name
        FROM attendance_records a
        LEFT JOIN subjects sub ON sub.subject_id = a.subject_id
        WHERE a.student_id = :student_id
        ORDER BY a.attendance_date DESC";

$query = $pdo->prepare($sql);
$query->execute(['student_id' => $studentId]);
$attendance = $query->fetchAll(PDO::FETCH_ASSOC);

// Get scholarship applications.
$sql = "SELECT
            sa.*,
            sp.program_name,
            sy.school_year
        FROM scholarship_applications sa
        INNER JOIN special_programs sp ON sp.program_id = sa.program_id
        INNER JOIN school_years sy ON sy.school_year_id = sa.school_year_id
        WHERE sa.student_id = :student_id
        ORDER BY sa.application_date DESC";

$query = $pdo->prepare($sql);
$query->execute(['student_id' => $studentId]);
$scholarships = $query->fetchAll(PDO::FETCH_ASSOC);

// Get scholarship monitoring records.
$sql = "SELECT
            sm.*,
            sp.program_name
        FROM scholarship_monitoring sm
        INNER JOIN scholarship_applications sa
            ON sa.scholarship_application_id = sm.scholarship_application_id
        INNER JOIN special_programs sp
            ON sp.program_id = sa.program_id
        WHERE sa.student_id = :student_id
        ORDER BY sm.monitoring_date DESC";

$query = $pdo->prepare($sql);
$query->execute(['student_id' => $studentId]);
$monitoring = $query->fetchAll(PDO::FETCH_ASSOC);

// Get stipend records.
$sql = "SELECT
            sr.*,
            sp.program_name
        FROM stipend_records sr
        INNER JOIN scholarship_applications sa
            ON sa.scholarship_application_id = sr.scholarship_application_id
        INNER JOIN special_programs sp
            ON sp.program_id = sa.program_id
        WHERE sa.student_id = :student_id
        ORDER BY sr.release_date DESC";

$query = $pdo->prepare($sql);
$query->execute(['student_id' => $studentId]);
$stipends = $query->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<?php
$listParameters = array_filter(array_intersect_key($_GET, array_flip(['search', 'search_by', 'grade'])), 'is_string');
$listUrl = BASE_URL . '/admin/student_records.php' . ($listParameters ? '?' . http_build_query($listParameters) : '');
?>
<div class="portal-back-row">
    <a href="<?= e($listUrl) ?>"
    class="portal-back-link">
    Back to Student Records
    </a>
</div>
<section class="form-card">


    <h1>Student Information</h1>

    <h2>
        <?= e($student['first_name'] . ' ' .
            ($student['middle_name'] ? $student['middle_name'] . ' ' : '') .
            $student['last_name']) ?>
    </h2>

    <p>LRN: <?= e($student['lrn'] ?? '—') ?></p>
    <details class="student-record-delete">
        <summary>Manage record removal</summary>
        <form method="post" action="<?= e(BASE_URL . '/admin/delete_student.php?' . http_build_query($listParameters)) ?>"
              data-confirm-title="Permanently delete student record?"
              data-confirm-label="Delete student record"
              data-confirm-delete="This permanently deletes the student account and its connected records, including enrollment, grades, attendance, and scholarship data.">
            <input type="hidden" name="student_id" value="<?= (int) $student['student_id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-actions">
                <button type="submit" class="button button-danger-outline portal-record-action">Delete student record</button>
            </div>
        </form>
    </details>


</section>


<section class="form-card">

    <h2>Account Information</h2>

    <div class="table-responsive">
    <table>
        <tr>
            <th>Username</th>
            <td><?= e($student['username']) ?></td>
        </tr>

        <tr>
            <th>Email</th>
            <td><?= e($student['email']) ?></td>
        </tr>

        <tr>
            <th>Account Status</th>
            <td><?= e($student['account_status']) ?></td>
        </tr>

        <tr>
            <th>Created At</th>
            <td><?= e($student['created_at']) ?></td>
        </tr>
    </table>
    </div>

</section>


<section class="form-card">

    <h2>Personal Information</h2>

    <div class="table-responsive">
    <table>
        <tr><th>First Name</th><td><?= e($student['first_name']) ?></td></tr>
        <tr><th>Middle Name</th><td><?= e($student['middle_name'] ?? '—') ?></td></tr>
        <tr><th>Last Name</th><td><?= e($student['last_name']) ?></td></tr>
        <tr><th>Birth Date</th><td><?= e($student['birth_date'] ?? '—') ?></td></tr>
        <tr><th>Birthplace</th><td><?= e($student['birthplace'] ?? '—') ?></td></tr>
        <tr><th>Sex</th><td><?= e($student['sex'] ?? '—') ?></td></tr>
        <tr><th>Civil Status</th><td><?= e($student['civil_status'] ?? '—') ?></td></tr>
        <tr><th>Nationality</th><td><?= e($student['nationality'] ?? '—') ?></td></tr>
        <tr><th>Religion</th><td><?= e($student['religion'] ?? '—') ?></td></tr>
        <tr><th>Contact Number</th><td><?= e($student['contact_number'] ?? '—') ?></td></tr>
        <tr><th>Current Address</th><td><?= e($student['current_address'] ?? '—') ?></td></tr>
        <tr><th>Permanent Address</th><td><?= e($student['permanent_address'] ?? '—') ?></td></tr>
        <tr><th>Grade Level</th><td><?= e($student['grade_level'] ?? 'Not assigned') ?></td></tr>
        <tr><th>Section</th><td><?= e($student['section_name'] ?? 'Not assigned') ?></td></tr>
        <tr><th>Readmission Status</th><td><?= e($student['readmission_status']) ?></td></tr>
    </table>
    </div>

</section>


<section class="form-card">

    <h2>Parent Information</h2>

    <?php if (!$parents): ?>
        <p>No parent information available.</p>
    <?php else: ?>

        <?php foreach ($parents as $parent): ?>

            <h3>Mother's Information</h3>

            <div class="table-responsive">
            <table>
                <tr><th>Name</th><td><?= e($parent['mother_name'] ?? '—') ?></td></tr>
                <tr><th>Occupation</th><td><?= e($parent['mother_occupation'] ?? '—') ?></td></tr>
                <tr><th>Contact Number</th><td><?= e($parent['mother_contact_number'] ?? '—') ?></td></tr>
                <tr><th>Email</th><td><?= e($parent['mother_email'] ?? '—') ?></td></tr>
                <tr><th>Address</th><td><?= e($parent['mother_address'] ?? '—') ?></td></tr>
            </table>
            </div>

            <h3>Father's Information</h3>

            <div class="table-responsive">
            <table>
                <tr><th>Name</th><td><?= e($parent['father_name'] ?? '—') ?></td></tr>
                <tr><th>Occupation</th><td><?= e($parent['father_occupation'] ?? '—') ?></td></tr>
                <tr><th>Contact Number</th><td><?= e($parent['father_contact_number'] ?? '—') ?></td></tr>
                <tr><th>Email</th><td><?= e($parent['father_email'] ?? '—') ?></td></tr>
                <tr><th>Address</th><td><?= e($parent['father_address'] ?? '—') ?></td></tr>
            </table>
            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</section>


<section class="form-card">

    <h2>Guardian Information</h2>

    <?php if (!$guardians): ?>
        <p>No guardian information available.</p>
    <?php else: ?>

        <?php foreach ($guardians as $guardian): ?>

            <div class="table-responsive">
            <table>
                <tr><th>Name</th><td><?= e($guardian['full_name']) ?></td></tr>
                <tr><th>Relationship</th><td><?= e($guardian['relationship'] ?? '—') ?></td></tr>
                <tr><th>Contact Number</th><td><?= e($guardian['contact_number'] ?? '—') ?></td></tr>
                <tr><th>Email</th><td><?= e($guardian['email'] ?? '—') ?></td></tr>
                <tr><th>Occupation</th><td><?= e($guardian['occupation'] ?? '—') ?></td></tr>
                <tr><th>Address</th><td><?= e($guardian['address'] ?? '—') ?></td></tr>
                <tr><th>Primary Guardian</th><td><?= $guardian['is_primary'] ? 'Yes' : 'No' ?></td></tr>
            </table>
            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</section>


<section class="form-card">

    <h2>Enrollment History</h2>

    <?php if (!$enrollments): ?>
        <p>No enrollment records available.</p>
    <?php else: ?>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>School Year</th>
                        <th>Enrollment Type</th>
                        <th>Requested Grade</th>
                        <th>Section</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($enrollments as $enrollment): ?>
                        <tr>
                            <td><?= e($enrollment['school_year']) ?></td>
                            <td><?= e($enrollment['enrollment_type']) ?></td>
                            <td><?= e($enrollment['grade_level']) ?></td>
                            <td><?= e($enrollment['section_name'] ?? 'Not assigned') ?></td>
                            <td><?= e($enrollment['application_status']) ?></td>
                            <td><?= e($enrollment['submitted_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>


<section class="form-card">

    <h2>Academic Records</h2>

    <?php if (!$grades): ?>
        <p>No academic records available.</p>
    <?php else: ?>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>School Year</th>
                        <th>Subject</th>
                        <th>Grading Period</th>
                        <th>Grade</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($grades as $grade): ?>
                        <tr>
                            <td><?= e($grade['school_year']) ?></td>
                            <td><?= e($grade['subject_name']) ?></td>
                            <td><?= e($grade['grading_period'] ?? '—') ?></td>
                            <td><?= e((string) ($grade['grade'] ?? '—')) ?></td>
                            <td><?= e($grade['remarks'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>


<section class="form-card">

    <h2>Attendance Records</h2>

    <?php if (!$attendance): ?>
        <p>No attendance records available.</p>
    <?php else: ?>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($attendance as $record): ?>
                        <tr>
                            <td><?= e($record['attendance_date']) ?></td>
                            <td><?= e($record['subject_name'] ?? '—') ?></td>
                            <td><?= e($record['attendance_status']) ?></td>
                            <td><?= e($record['remarks'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>


<section class="form-card">

    <h2>Scholarship Information</h2>

    <?php if (!$scholarships): ?>
        <p>No scholarship applications found.</p>
    <?php else: ?>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Scholarship Program</th>
                        <th>School Year</th>
                        <th>Application Status</th>
                        <th>Application Date</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($scholarships as $scholarship): ?>
                        <tr>
                            <td><?= e($scholarship['program_name']) ?></td>
                            <td><?= e($scholarship['school_year']) ?></td>
                            <td><?= e($scholarship['application_status']) ?></td>
                            <td><?= e($scholarship['application_date']) ?></td>
                            <td><?= e($scholarship['remarks'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>


<section class="form-card">

    <h2>Scholarship Monitoring</h2>

    <?php if (!$monitoring): ?>
        <p>No scholarship monitoring records found.</p>
    <?php else: ?>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Monitoring Date</th>
                        <th>Academic Performance</th>
                        <th>Behavior Remarks</th>
                        <th>Participation</th>
                        <th>Renewal Eligibility</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($monitoring as $record): ?>
                        <tr>
                            <td><?= e($record['program_name']) ?></td>
                            <td><?= e($record['monitoring_date']) ?></td>
                            <td><?= e($record['academic_performance'] ?? '—') ?></td>
                            <td><?= e($record['behavior_remarks'] ?? '—') ?></td>
                            <td><?= e($record['participation_status']) ?></td>
                            <td><?= e($record['renewal_eligibility']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>


<section class="form-card">

    <h2>Stipend Records</h2>

    <?php if (!$stipends): ?>
        <p>No stipend records found.</p>
    <?php else: ?>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Amount</th>
                        <th>Release Date</th>
                        <th>Recipient</th>
                        <th>Claiming Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($stipends as $stipend): ?>
                        <tr>
                            <td><?= e($stipend['program_name']) ?></td>
                            <td><?= e((string) $stipend['amount']) ?></td>
                            <td><?= e($stipend['release_date'] ?? '—') ?></td>
                            <td><?= e($stipend['recipient_name']) ?></td>
                            <td><?= e($stipend['claiming_status']) ?></td>
                            <td><?= e($stipend['remarks'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
