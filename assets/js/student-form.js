(function () {
    'use strict';

    const form = document.getElementById('sgm-student-form');
    if (!form) return;

    const subjectNames = ['math', 'science', 'history', 'music', 'pe'];
    const averageInput = form.querySelector('#sgm-average');
    const message = form.querySelector('#sgm-message');
    const averageButton = form.querySelector('#sgm-get-average');
    const saveButton = form.querySelector('#sgm-save');

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

    function getGrades() {
        const values = subjectNames.map((name) => {
            const input = form.elements[name];
            const value = input.value.trim();
            return isCompleteGrade(value) ? Number(value) : null;
        });
        return values;
    }

    function calculateAverage() {
        const grades = getGrades();
        if (grades.some((grade) => grade === null)) {
            averageInput.value = '';
            return null;
        }
        const average = grades.reduce((sum, grade) => sum + grade, 0) / grades.length;
        averageInput.value = average.toFixed(2);
        return average;
    }

    function showMessage(text, type) {
        message.textContent = text || '';
        message.className = 'sgm-message' + (type ? ' is-' + type : '');
    }

    averageButton.addEventListener('click', function () {
        const average = calculateAverage();
        if (average === null) {
            showMessage(SGM_CONFIG.messages.invalid, 'error');
            return;
        }
        showMessage('Average calculated: ' + average.toFixed(2), 'success');
    });

    subjectNames.forEach((name) => {
        guardGrade(form.elements[name]);
        form.elements[name].addEventListener('input', function () {
            averageInput.value = '';
            showMessage('', '');
        });
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const name = form.elements.name.value.trim();
        const average = calculateAverage();
        if (!name || average === null) {
            showMessage(SGM_CONFIG.messages.invalid, 'error');
            return;
        }

        const payload = new FormData();
        payload.append('action', 'sgm_save_student');
        payload.append('nonce', SGM_CONFIG.nonce);
        payload.append('name', name);
        subjectNames.forEach((subject) => payload.append(subject, form.elements[subject].value));

        saveButton.disabled = true;
        saveButton.textContent = 'saving...';
        showMessage('', '');

        try {
            const response = await fetch(SGM_CONFIG.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.data && result.data.message ? result.data.message : SGM_CONFIG.messages.error);
            }
            averageInput.value = Number(result.data.average).toFixed(2);
            showMessage(result.data.message || SGM_CONFIG.messages.success, 'success');
            form.reset();
            averageInput.value = '';
        } catch (error) {
            showMessage(error.message || SGM_CONFIG.messages.error, 'error');
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = 'save to database';
        }
    });
})();
