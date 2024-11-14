  <!-- Action Modal -->
  <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="actionModalLabel">Confirm Action</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                  Are you sure you want to perform this action? This action cannot be undone.
              </div>
              <div class="modal-footer">
                  <form id="modalForm" method="post">
                      @csrf
                      @method('delete')
                      <button type="submit" class="btn btn-danger">Yes, Proceed</button>
                  </form>
              </div>
          </div>
      </div>
  </div>

  <script>
      function openDeleteModal(actionUrl) {
          const modal = new bootstrap.Modal(document.getElementById('actionModal'));
          const form = document.getElementById('modalForm');
          form.action = actionUrl;
          modal.show();
      }
  </script>
