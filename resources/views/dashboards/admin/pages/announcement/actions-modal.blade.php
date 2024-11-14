<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="modalMessage">Are you sure you want to perform this action?</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="actionForm" action="">
                    @csrf
                    <!-- Container for DELETE form method -->
                    <div id="methodFieldContainer"></div>
                    <input type="hidden" id="actionType" name="status" value="">
                    <button type="submit" class="btn btn-primary">Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openActionModal(actionType, actionUrl, status = '', label = '', icon = '') {
    let message = '';
    const actionTypeField = document.getElementById('actionType');
    const methodFieldContainer = document.getElementById('methodFieldContainer');

    // Reset form fields
    actionTypeField.value = '';
    methodFieldContainer.innerHTML = '';

    // Define message and hidden field behavior based on action type
    switch (actionType) {
        case 'active-announcement':
            message = 'Are you sure you want to activate this announcement?';
            actionTypeField.value = 'Active';
            break;
        case 'inactive-announcement':
            message = 'Are you sure you want to deactivate this announcement?';
            actionTypeField.value = 'Inactive';
            break;
        case 'delete-announcement':
            message = 'Are you sure you want to delete this announcement? This action cannot be undone.';
            methodFieldContainer.innerHTML = '<input type="hidden" name="_method" value="DELETE">';
            break;
        default:
            message = 'Are you sure you want to perform this action?';
    }

    // Update the modal content
    document.getElementById('modalMessage').innerText = message;
    document.getElementById('actionForm').action = actionUrl;

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    modal.show();
}

</script>