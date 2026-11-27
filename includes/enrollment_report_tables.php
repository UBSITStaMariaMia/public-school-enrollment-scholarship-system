    <div class="report-section">
        <h2>1. Applications by Status (<?= e($selectedYearName) ?>)</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Application Status</th>
                        <th>Total</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applicationStatuses as $status): ?>
                        <?php
                        $total = $statusCounts[$status];
                        $percentage = $totalApplications > 0
                            ? ($total / $totalApplications) * 100
                            : 0;
                        ?>
                        <tr>
                            <td><?= e($status) ?></td>
                            <td><?= e($total) ?></td>
                            <td><?= e(number_format($percentage, 1)) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="report-table-total">
                        <td>Total Applications</td>
                        <td><?= e($totalApplications) ?></td>
                        <td><?= $totalApplications > 0 ? '100%' : '0%' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-section">
        <h2>2. Applications by Enrollment Type</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Enrollment Type</th>
                        <th>Total</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollmentTypes as $type): ?>
                        <?php
                        $total = $typeCounts[$type];
                        $percentage = $totalByType > 0
                            ? ($total / $totalByType) * 100
                            : 0;
                        ?>
                        <tr>
                            <td><?= e($type) ?></td>
                            <td><?= e($total) ?></td>
                            <td><?= e(number_format($percentage, 1)) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="report-table-total">
                        <td>Total Applications by Type</td>
                        <td><?= e($totalByType) ?></td>
                        <td><?= $totalByType > 0 ? '100%' : '0%' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-section">
        <h2>3. Applications by Requested Grade Level</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Grade Level</th>
                        <?php foreach ($applicationStatuses as $status): ?>
                            <th><?= e($status) ?></th>
                        <?php endforeach; ?>
                        <th>Total</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gradeLevels as $grade): ?>
                        <?php
                        $gradeId = (int) $grade['grade_level_id'];
                        $gradeTotal = $gradeTotals[$gradeId] ?? 0;
                        $gradeShare = $totalApplications > 0
                            ? ($gradeTotal / $totalApplications) * 100
                            : 0;
                        ?>
                        <tr>
                            <td>Grade <?= e($grade['grade_level']) ?></td>
                            <?php foreach ($applicationStatuses as $status): ?>
                                <td><?= e($gradeCounts[$gradeId][$status] ?? 0) ?></td>
                            <?php endforeach; ?>
                            <td><strong><?= e($gradeTotal) ?></strong></td>
                            <td><?= e(number_format($gradeShare, 1)) ?>%</td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$gradeLevels): ?>
                        <tr>
                            <td colspan="7">No grade levels found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

