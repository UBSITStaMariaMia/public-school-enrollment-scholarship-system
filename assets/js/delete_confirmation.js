const portalDeleteDialog = document.getElementById('portalDeleteDialog');

if (portalDeleteDialog && typeof portalDeleteDialog.showModal === 'function') {
    let pendingForm;
    let pendingButton;
    let confirmedForm;

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form.matches('form[data-confirm-delete]') || form === confirmedForm) return;

        if (form.hasAttribute('data-confirm-inactive-only') && form.elements.status.value !== 'Inactive') return;

        event.preventDefault();
        pendingForm = form;
        pendingButton = event.submitter;
        document.getElementById('portalDeleteTitle').textContent = form.dataset.confirmTitle || 'Confirm removal';
        document.getElementById('portalDeleteMessage').textContent = form.dataset.confirmDelete;
        document.getElementById('portalDeleteConfirm').textContent = form.dataset.confirmLabel || pendingButton?.textContent.trim() || 'Delete';
        portalDeleteDialog.returnValue = '';
        portalDeleteDialog.showModal();
    });

    portalDeleteDialog.addEventListener('close', function () {
        if (portalDeleteDialog.returnValue === 'confirm' && pendingForm) {
            confirmedForm = pendingForm;
            pendingForm.requestSubmit(pendingButton || undefined);
            confirmedForm = null;
        } else {
            pendingButton?.focus();
        }
        pendingForm = null;
        pendingButton = null;
    });
}
