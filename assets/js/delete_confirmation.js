const portalDeleteDialog = document.getElementById('portalDeleteDialog');

if (portalDeleteDialog && typeof portalDeleteDialog.showModal === 'function') {
    let pendingForm;
    let pendingButton;
    let confirmedForm;

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form.matches('form[data-confirm-delete]') || form === confirmedForm) return;

        event.preventDefault();
        pendingForm = form;
        pendingButton = event.submitter;
        document.getElementById('portalDeleteMessage').textContent = form.dataset.confirmDelete;
        document.getElementById('portalDeleteConfirm').textContent = pendingButton?.textContent.trim() || 'Delete';
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
