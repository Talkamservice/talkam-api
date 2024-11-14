<div class="modal fade" id="delete_invite_{{ $invitation->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Delete Invitation</h5>
                <button type="button" class="btn close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                The user you invited would no longer be able to access this dashboard.
            </div>
            @php
                $url = isset($delete_url) ? $delete_url : $invitation->deleteUrl();
            @endphp
            <form action="{{ $url }}" method="post" onsubmit="return confirm('Are you sure of this action?')">@csrf @method('delete')
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>
