<!-- Modal -->
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
        
        switch(action) {
            case 'suspend':
                message = 'Are you sure you want to suspend this group?';
                methodFieldContainer.innerHTML = ''; // Remove the DELETE method field for POST
                break;
            case 'delete':
                message = 'Are you sure you want to permanently delete this group?';
                methodFieldContainer.innerHTML = '@method("DELETE")'; // Add DELETE method field
                break;
            case 'undo':
                message = 'Are you sure you want to lift the suspension?';
                methodFieldContainer.innerHTML = ''; // Remove the DELETE method field for POST
                break;
            default:
                message = 'Are you sure you want to proceed with this action?';
        }

        // Set the confirmation message
        document.getElementById('modalMessage').innerText = message;

        // Set the form action dynamically
        document.getElementById('confirmationForm').action = formAction;

        // Show the modal
        const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        confirmationModal.show();
    }
</script>
