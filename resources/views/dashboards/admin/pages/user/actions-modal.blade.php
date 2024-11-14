<!-- Modal HTML -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmationModalLabel">Confirm Action</h5>
                <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalMessage">
                <!-- Dynamic confirmation message -->
            </div>
            <div class="modal-footer">
                <form id="confirmationForm" action="" method="POST" style="display:inline;">
                    @csrf
                    <input type="hidden" name="action" value="Resolved">
                    <input type="hidden" id="actionType" name="status" value="">
                    <div id="methodFieldContainer"></div> <!-- Placeholder for method spoofing -->
                    <button type="submit" class="btn btn-primary">Yes, Proceed</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openActionsModal(action, formAction) {
    let message = '';
    let methodFieldContainer = document.getElementById('methodFieldContainer');
    let actionTypeField = document.getElementById('actionType');
    let formMethod = ''; // For method spoofing

    // Reset method field container and actionTypeField
    methodFieldContainer.innerHTML = '';
    actionTypeField.value = '';

    // Determine message and form method based on the action
    switch (action) {
        case 'suspend':
            message = 'Are you sure you want to suspend this user?';
            actionTypeField.value = 'Inactive';
            break;
        case 'activate':
            message = 'Are you sure you want to activate this user?';
            actionTypeField.value = 'Active';
            break;
        case 'strike':
            message = 'Are you sure you want to strike this user?';
            break;
        case 'delete':
            message = 'Are you sure you want to delete this user?';
            formMethod = '<input type="hidden" name="_method" value="DELETE">'; // Use DELETE for removal
            break;
        case 'ban':
            message = 'Are you sure you want to ban this user?';
            actionTypeField.value = 'Banned';
            break;
        default:
            message = 'Are you sure you want to proceed with this action?';
    }

    // Set the confirmation message
    document.getElementById('modalMessage').innerText = message;

    // Set the form action dynamically
    document.getElementById('confirmationForm').action = formAction;

    // Set method spoofing if required
    methodFieldContainer.innerHTML = formMethod;

    // Show the modal
    const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    confirmationModal.show();
}

</script>