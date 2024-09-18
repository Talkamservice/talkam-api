<!-- Modal for User Ban -->
<div class="modal fade" id="BanModal" tabindex="-1" aria-labelledby="BanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="BanModalLabel">Ban User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="BanForm" method="POST" action="">
                @csrf
                @method('post')
                <input type="hidden" name="action_type" id="actionType">
                <input type="hidden" name="user_id" id="userId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="banReason" class="form-label">Reason for Ban</label>
                        <textarea id="banReason" name="suspend_ban_reason" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger" onclick="setActionType('ban')">Ban</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openBanUserModal(userId) {
        const modal = new bootstrap.Modal(document.getElementById('BanModal'));
        const form = document.getElementById('BanForm');
        const banRoute = `{{ route('admin.users.ban', ':id') }}`.replace(':id', userId);

        form.setAttribute('action', banRoute); // Set the form action with the correct user ID
        document.getElementById('userId').value = userId; // Set the hidden input value for the user ID
        modal.show();
    }

    function setActionType(type) {
        document.getElementById('actionType').value = type;
    }
</script>
