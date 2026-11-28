
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$listParameters = array_filter(array_intersect_key($_GET, array_flip(['search', 'search_by', 'grade'])), 'is_string');

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

// Verify CSRF token.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid request. Please refresh the page and try again.');
}

// Validate student ID.
$studentId = filter_var(
    $_POST['student_id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$studentId || $studentId < 1) {
    redirect('/admin/student_records.php?' . http_build_query($listParameters + ['error' => 'invalid']));
}

$admin = current_user();
$uploadedFiles = array();

try {

    $pdo->beginTransaction();

    // Find the student and confirm that the account has Student role.
    $sql = "SELECT
                s.student_id,
                s.user_id,
                s.lrn,
                s.first_name,
                s.last_name
            FROM students s
            INNER JOIN users u ON u.user_id = s.user_id
            INNER JOIN roles r ON r.role_id = u.role_id
            WHERE s.student_id = :student_id
            AND r.role_name = 'Student'
            LIMIT 1
            FOR UPDATE";

    $query = $pdo->prepare($sql);
    $query->execute(['student_id' => $studentId]);
    $student = $query->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $pdo->rollBack();
        redirect('/admin/student_records.php?' . http_build_query($listParameters + ['error' => 'not_found']));
    }

    $userId = $student['user_id'];

    // Get uploaded document paths before deleting enrollment records.
    $sql = "SELECT ed.file_path
            FROM enrollment_documents ed
            INNER JOIN enrollment_applications ea
                ON ea.application_id = ed.application_id
            WHERE ea.student_id = :student_id";

    $query = $pdo->prepare($sql);
    $query->execute(['student_id' => $studentId]);
    $documents = $query->fetchAll(PDO::FETCH_ASSOC);

    foreach ($documents as $document) {
        if (!empty($document['file_path'])) {
            $uploadedFiles[] = $document['file_path'];
        }
    }

    // Record the deletion in the audit log.
    $studentName = $student['first_name'] . ' ' . $student['last_name'];

    $details = 'Permanently deleted student account: ' .
        $studentName .
        ' | LRN: ' . ($student['lrn'] ?? 'Not provided');

    $sql = "INSERT INTO audit_logs
                (user_id, action, entity_type, entity_id, details)
            VALUES
                (:user_id, 'Permanent Student Deletion', 'Student',
                 :entity_id, :details)";

    $query = $pdo->prepare($sql);
    $query->execute([
        'user_id' => $admin['user_id'],
        'entity_id' => $studentId,
        'details' => $details
    ]);

    // Delete stipend records first.
    $sql = "DELETE FROM stipend_records
            WHERE scholarship_application_id IN (
                SELECT scholarship_application_id
                FROM scholarship_applications
                WHERE student_id = :student_id
            )";

    $query = $pdo->prepare($sql);
    $query->execute(['student_id' => $studentId]);

    // Delete scholarship monitoring records.
    $sql = "DELETE FROM scholarship_monitoring
            WHERE scholarship_application_id IN (
                SELECT scholarship_application_id
                FROM scholarship_applications
                WHERE student_id = :student_id
            )";

    $query = $pdo->prepare($sql);
    $query->execute(['student_id' => $studentId]);

    // Delete scholarship applications.
    $query = $pdo->prepare(
        "DELETE FROM scholarship_applications
         WHERE student_id = :student_id"
    );
    $query->execute(['student_id' => $studentId]);

    // Delete uploaded enrollment document records.
    $sql = "DELETE ed
            FROM enrollment_documents ed
            INNER JOIN enrollment_applications ea
                ON ea.application_id = ed.application_id
            WHERE ea.student_id = :student_id";

    $query = $pdo->prepare($sql);
    $query->execute(['student_id' => $studentId]);

    // Delete enrollment applications.
    $query = $pdo->prepare(
        "DELETE FROM enrollment_applications
         WHERE student_id = :student_id"
    );
    $query->execute(['student_id' => $studentId]);

    // Delete academic records.
    $query = $pdo->prepare(
        "DELETE FROM academic_records
         WHERE student_id = :student_id"
    );
    $query->execute(['student_id' => $studentId]);

    // Delete attendance records.
    $query = $pdo->prepare(
        "DELETE FROM attendance_records
         WHERE student_id = :student_id"
    );
    $query->execute(['student_id' => $studentId]);

    // Delete subject enrollment records.
    $query = $pdo->prepare(
        "DELETE FROM student_subject_enrollments
         WHERE student_id = :student_id"
    );
    $query->execute(['student_id' => $studentId]);

    // Delete guardian information.
    $query = $pdo->prepare(
        "DELETE FROM student_guardians
         WHERE student_id = :student_id"
    );
    $query->execute(['student_id' => $studentId]);

    // Delete parent information.
    $query = $pdo->prepare(
        "DELETE FROM student_parent_information
         WHERE student_id = :student_id"
    );
    $query->execute(['student_id' => $studentId]);

    // Delete the student profile.
    $query = $pdo->prepare(
        "DELETE FROM students
         WHERE student_id = :student_id"
    );
    $query->execute(['student_id' => $studentId]);

    // Delete the student's login account.
    $query = $pdo->prepare(
        "DELETE FROM users
         WHERE user_id = :user_id"
    );
    $query->execute(['user_id' => $userId]);

    // Commit all database changes.
    $pdo->commit();

    // Delete uploaded files after the database transaction succeeds.
    $projectRoot = dirname(__DIR__);
    $uploadDirectory = realpath($projectRoot . '/uploads/enrollment');

    if ($uploadDirectory !== false) {

        foreach ($uploadedFiles as $filePath) {

            $relativePath = str_replace(
                array('/', '\\'),
                DIRECTORY_SEPARATOR,
                $filePath
            );

            $fullPath = realpath(
                $projectRoot . DIRECTORY_SEPARATOR . $relativePath
            );

            // Only delete files inside uploads/enrollment.
            if (
                $fullPath !== false &&
                strpos(
                    $fullPath,
                    $uploadDirectory . DIRECTORY_SEPARATOR
                ) === 0 &&
                is_file($fullPath)
            ) {
                if (!unlink($fullPath)) {
                    error_log('Unable to delete uploaded file: ' . $fullPath);
                }
            }
        }
    }

    redirect('/admin/student_records.php?' . http_build_query($listParameters + ['deleted' => '1']));

} catch (PDOException $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Student deletion error: ' . $exception->getMessage());

    redirect('/admin/student_records.php?' . http_build_query($listParameters + ['error' => 'delete_failed']));
}
?>
