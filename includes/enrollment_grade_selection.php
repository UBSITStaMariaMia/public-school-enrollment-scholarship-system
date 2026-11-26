<script>
document.addEventListener('DOMContentLoaded', function () {
    const enrollmentType = document.querySelector('select[name="enrollment_type"]');
    const grade = document.querySelector('select[name="requested_grade_level_id"]');
    if (!enrollmentType || !grade) return;

    const gradeOptions = Array.from(grade.options);
    const gradeSeven = gradeOptions.find(function (option) { return option.dataset.gradeLevel === '7'; });

    function syncGradeOptions() {
        if (enrollmentType.value === 'Incoming Grade 7' && gradeSeven) {
            grade.replaceChildren(gradeSeven);
            grade.value = gradeSeven.value;
        } else {
            const previousGrade = grade.value;
            grade.replaceChildren(...gradeOptions);
            grade.value = previousGrade;
        }
    }

    enrollmentType.addEventListener('change', syncGradeOptions);
    syncGradeOptions();
});
</script>
