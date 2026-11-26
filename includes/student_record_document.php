<?php require __DIR__ . '/formal_report_start.php'; ?>
<table class="report-details" aria-label="Learner information">
    <tbody>
        <tr>
            <th scope="row">Learner name</th><td><?= e($recordStudentName) ?></td>
            <th scope="row">LRN</th><td><?= e($selectedStudent['lrn'] ?? 'Not provided') ?></td>
        </tr>
        <tr>
            <th scope="row">Grade level</th><td><?= e($selectedStudent['grade_level'] ?? 'Not assigned') ?></td>
            <th scope="row">Section</th><td><?= e($selectedStudent['section_name'] ?? 'Not assigned') ?></td>
        </tr>
        <tr><th scope="row">School year</th><td colspan="3"><?= e($activeSchoolYear['school_year']) ?></td></tr>
    </tbody>
</table>

<h3 class="report-section-title">Academic Record</h3>
<?php if ($gradeMatrix): ?>
    <table aria-label="Quarterly grades">
        <thead>
            <tr>
                <th scope="col">Learning area</th>
                <?php foreach ($gradingPeriods as $period): ?>
                    <th scope="col"><?= e($period) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($gradeMatrix as $subject): ?>
                <tr>
                    <th scope="row"><?= e($subject['subject_name']) ?></th>
                    <?php foreach ($gradingPeriods as $period): ?>
                        <td><?= e((string) ($subject['quarters'][$period] ?? '—')) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No grades recorded for this school year.</p>
<?php endif; ?>

<h3 class="report-section-title">Attendance Summary</h3>
<table aria-label="Attendance totals">
    <thead><tr><th>Present</th><th>Absent</th><th>Late</th><th>Excused</th><th>Total entries</th></tr></thead>
    <tbody><tr>
        <?php foreach (['present_count', 'absent_count', 'late_count', 'excused_count', 'total_records'] as $count): ?>
            <td><?= number_format((int) ($attendanceSummary[$count] ?? 0)) ?></td>
        <?php endforeach; ?>
    </tr></tbody>
</table>
<?php if ($attendanceRecords): ?>
    <h3 class="report-section-title">Recent Attendance Entries (up to 30)</h3>
    <table aria-label="Recent attendance entries">
        <thead><tr><th>Date</th><th>Learning area</th><th>Status</th><th>Remarks</th><th>Recorded by</th></tr></thead>
        <tbody>
            <?php foreach ($attendanceRecords as $record): ?>
                <tr>
                    <td><?= e($record['attendance_date']) ?></td>
                    <td><?= e($record['subject_name']) ?></td>
                    <td><?= e($record['attendance_status']) ?></td>
                    <td><?= e($record['remarks'] ?? '') ?></td>
                    <td><?= e($record['recorded_by_name'] ?? 'Previous record') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<footer class="report-footer">
    <p>BCNHS portal record summary for school reference. Official permanent records are issued separately.</p>
</footer>
</main>
</body>
</html>
