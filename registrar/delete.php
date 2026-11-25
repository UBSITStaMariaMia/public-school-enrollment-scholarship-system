<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

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

// Validate section ID.
$sectionId = filter_var(
    $_POST['section_id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$sectionId || $sectionId < 1) {
    redirect('/registrar/sections.php?error=invalid');
}

$registrar = current_user();

try {
    $pdo->beginTransaction();

    // Find and lock the section before attempting deletion.
    $query = $pdo->prepare(
        "SELECT
            section_id,
            section_name,
            grade_level_id,
            school_year_id
         FROM sections
         WHERE section_id = :section_id
         LIMIT 1
         FOR UPDATE"
    );

    $query->execute(['section_id' => $sectionId]);
    $section = $query->fetch(PDO::FETCH_ASSOC);

    if (!$section) {
        $pdo->rollBack();
        redirect('/registrar/sections.php?error=not_found');
    }

    /*
     * Check all database foreign keys that reference sections.
     * This prevents deletion when the section is still connected
     * to student records or other school records.
     */
    $referenceQuery = $pdo->prepare(
        "SELECT
            TABLE_NAME,
            COLUMN_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
           AND REFERENCED_TABLE_NAME = 'sections'
           AND REFERENCED_COLUMN_NAME = 'section_id'"
    );

    $referenceQuery->execute();
    $references = $referenceQuery->fetchAll(PDO::FETCH_ASSOC);

    $blockingReferences = [];

    foreach ($references as $reference) {
        $tableName = $reference['TABLE_NAME'];
        $columnName = $reference['COLUMN_NAME'];

        // Only use valid identifiers returned by information_schema.
        if (
            !preg_match('/^[A-Za-z0-9_]+$/', $tableName) ||
            !preg_match('/^[A-Za-z0-9_]+$/', $columnName)
        ) {
            continue;
        }

        $checkSql = sprintf(
            'SELECT COUNT(*) FROM `%s` WHERE `%s` = :section_id',
            $tableName,
            $columnName
        );

        $checkQuery = $pdo->prepare($checkSql);
        $checkQuery->execute(['section_id' => $sectionId]);

        $recordCount = (int)$checkQuery->fetchColumn();

        if ($recordCount > 0) {
            $blockingReferences[] = $tableName . ' (' . $recordCount . ')';
        }
    }

    if ($blockingReferences) {
        $pdo->rollBack();

        // Keep the message concise in the URL; sections.php can display it.
        redirect('/registrar/sections.php?error=in_use');
    }

    // Record the deletion in the audit log before removing the section.
    $details = 'Deleted section: ' .
        $section['section_name'] .
        ' | Section ID: ' . $sectionId .
        ' | Grade Level ID: ' . $section['grade_level_id'] .
        ' | School Year ID: ' . $section['school_year_id'];

    $auditQuery = $pdo->prepare(
        "INSERT INTO audit_logs
            (user_id, action, entity_type, entity_id, details)
         VALUES
            (:user_id, 'Section Deletion', 'Section', :entity_id, :details)"
    );

    $auditQuery->execute([
        'user_id' => $registrar['user_id'],
        'entity_id' => $sectionId,
        'details' => $details
    ]);

    // Delete the section only after confirming it has no dependent records.
    $deleteQuery = $pdo->prepare(
        "DELETE FROM sections
         WHERE section_id = :section_id"
    );

    $deleteQuery->execute(['section_id' => $sectionId]);

    if ($deleteQuery->rowCount() !== 1) {
        throw new RuntimeException('The section could not be deleted.');
    }

    $pdo->commit();

    redirect('/registrar/sections.php?deleted=1');

} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Section deletion error: ' . $exception->getMessage());

    if ($exception->getCode() === '23000') {
        redirect('/registrar/sections.php?error=in_use');
    }

    redirect('/registrar/sections.php?error=delete_failed');

} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Section deletion error: ' . $exception->getMessage());

    redirect('/registrar/sections.php?error=delete_failed');
}
?>
