  <!-- Modal for Group Suspension/Ban -->
  <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="feedbackModalLabel">Feedback Response</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="feedbackForm" method="POST" action="">
                @csrf
                @method('post')
                <input type="hidden" name="feedback_id" id="feedbackId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea id="message" name="message" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Send</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    
    function openModal(feedbackId) {
        document.getElementById('feedbackId').value = feedbackId;
        document.getElementById('feedbackForm').action =
            `{{ url('admin/feedback/respond') }}/${feedbackId}`;
        new bootstrap.Modal(document.getElementById('feedbackModal')).show();
    }
</script>
