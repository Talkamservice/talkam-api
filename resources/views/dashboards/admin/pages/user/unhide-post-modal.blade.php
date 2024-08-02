<!-- Unhide Posts Modal -->
<div class="modal fade" id="unhidePostModal" tabindex="-1" aria-labelledby="unhidePostModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unhidePostModalLabel">Unhide Posts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to unhide these posts?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="unhidePostForm" method="POST">
                    @csrf
                    <input type="hidden" id="unhidePostUrl" name="unhidePostUrl">
                    <button type="submit" class="btn btn-success">Unhide Posts</button>
                </form>
            </div>
        </div>
    </div>
</div>