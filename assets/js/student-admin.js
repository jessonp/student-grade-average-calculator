(function () {
    'use strict';

    const averageButton = document.getElementById('sgm-admin-get-average');
    const saveButton = document.getElementById('sgm-admin-save');
    if (!averageButton || !saveButton) return;

    const subjects = ['math', 'science', 'history', 'music', 'pe'];
    const nameInput = document.getElementById('sgm_admin_name');
    const averageInput = document.getElementById('sgm_admin_average');
    const message = document.getElementById('sgm-admin-message');

    function isAllowedGrade(value) {
        if (/^0*100\./.test(value)) return false;
        return /^\d{0,3}(\.\d{0,2})?$/.test(value) && (value === '' || value === '.' || Number(value) <= 100);
    }

    function isCompleteGrade(value) {
        return value !== '.' && value !== '' && isAllowedGrade(value);
    }

    function guardGrade(input) {
        let last = input.value;
        input.addEventListener('input', function () {
            if (isAllowedGrade(input.value)) {
                last = input.value;
            } else {
                input.value = last;
            }
        });
    }

    function calculateAverage() {
        const grades = subjects.map((subject) => {
            const value = document.getElementById('sgm_admin_' + subject).value.trim();
            return isCompleteGrade(value) ? Number(value) : null;
        });
        if (grades.some((grade) => grade === null)) {
            averageInput.value = '';
            return null;
        }
        const average = grades.reduce((sum, grade) => sum + grade, 0) / grades.length;
        averageInput.value = average.toFixed(2);
        return average;
    }

    function showMessage(text, type) {
        message.textContent = text;
        message.className = 'sgm-admin-message' + (type ? ' is-' + type : '');
    }

    averageButton.addEventListener('click', function () {
        const average = calculateAverage();
        if (average === null) {
            showMessage(SGM_ADMIN.invalid, 'error');
            return;
        }
        showMessage('Average calculated: ' + average.toFixed(2), 'success');
    });

    saveButton.addEventListener('click', function (event) {
        if (nameInput.value.trim() === '' || calculateAverage() === null) {
            event.preventDefault();
            showMessage(SGM_ADMIN.invalid, 'error');
        }
    });

    subjects.forEach((subject) => {
        guardGrade(document.getElementById('sgm_admin_' + subject));
        document.getElementById('sgm_admin_' + subject).addEventListener('input', function () {
            averageInput.value = '';
            showMessage('', '');
        });
    });
})();
