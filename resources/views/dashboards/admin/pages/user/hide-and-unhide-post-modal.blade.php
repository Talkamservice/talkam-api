<!-- Hide and Permanently Remove Modal HTML -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="actionText">Are you sure you want to perform this action?</p>
                <form id="actionForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod">
                    <input type="hidden" name="action_url" id="actionUrl">
                    <div class="d-flex justify-content-between">
                        <button type="button" id="hideButton" class="btn btn-primary">Hide</button>
                        <button type="button" id="removeButton" class="btn btn-danger">Permanently Remove</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Restore Modal HTML -->
<div class="modal fade" id="unhidePostModal" tabindex="-1" aria-labelledby="unhidePostModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unhidePostModalLabel">Restore Posts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="unhideText">Restore the hidden posts for this user?</p>
                <form id="unhideForm" method="POST">
                    @csrf
                    <input type="hidden" id="unhideActionUrl">
                    <div class="d-flex justify-content-between">
                        <button type="button" id="cancelButton" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="restoreButton" class="btn btn-success">Restore</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
