(function () {
    'use strict';

    const modal = document.createElement('div');
    modal.className = 'sgm-modal';
    modal.hidden = true;
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'sgm-modal-title');

    const dialog = document.createElement('div');
    dialog.className = 'sgm-modal-dialog';

    const head = document.createElement('div');
    head.className = 'sgm-modal-head';

    const title = document.createElement('h2');
    title.id = 'sgm-modal-title';
    title.tabIndex = -1;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'sgm-modal-close';
    close.setAttribute('aria-label', 'Close');
    close.textContent = '×';

    const table = document.createElement('table');
    table.className = 'sgm-modal-table';
    const body = document.createElement('tbody');

    head.append(title, close);
    table.append(body);
    dialog.append(head, table);
    modal.append(dialog);
    document.body.append(modal);

    let opener = null;

    function addRow(label, value, className) {
        const row = document.createElement('tr');
        if (className) row.className = className;
        const heading = document.createElement('th');
        heading.scope = 'row';
        heading.textContent = label;
        const cell = document.createElement('td');
        cell.textContent = value === '' || value === null ? '—' : value;
        row.append(heading, cell);
        body.append(row);
    }

    function closeModal() {
        modal.hidden = true;
        if (opener) opener.focus();
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.sgm-view-button');
        if (!button) return;
        const student = JSON.parse(button.getAttribute('data-sgm-student'));
        opener = button;
        title.textContent = student.name;
        body.textContent = '';
        student.grades.forEach((grade) => addRow(grade.label, grade.value));
        addRow('Average', student.average, 'sgm-modal-average');
        modal.hidden = false;
        title.focus();
    });

    close.addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
