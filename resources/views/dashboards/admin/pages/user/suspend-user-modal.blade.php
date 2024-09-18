 <!-- Modal for User Suspension -->
<div class="modal fade" id="SuspendModal" tabindex="-1" aria-labelledby="SuspendModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="SuspendModalLabel">Suspend User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="SuspendForm" method="POST" action="">
                @csrf
                @method('post')
                <input type="hidden" name="user_id" id="userId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="suspendReason" class="form-label">Reason for Suspension</label>
                        <textarea id="suspendReason" name="suspend_reason" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="suspendDuration" class="form-label">Suspension Duration</label>
                        <input type="date" id="suspendDuration" class="form-control" name="duration" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="status" value="Inactive">
                    <button type="submit" class="btn btn-warning">Suspend</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openSuspendUserModal(userId) {
        const modal = new bootstrap.Modal(document.getElementById('SuspendModal'));
        const form = document.getElementById('SuspendForm');
        const suspendRoute = `{{ route('admin.users.suspend', ':id') }}`.replace(':id', userId);

        form.setAttribute('action', suspendRoute); // Set the form action with the correct user ID
        document.getElementById('userId').value = userId; // Set the hidden input value for the user ID
        modal.show();
    }

    function setActionType(type) {
        document.getElementById('actionType').value = type;
    }
</script>
