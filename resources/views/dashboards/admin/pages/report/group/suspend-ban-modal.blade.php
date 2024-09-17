  <!-- Modal for Group Suspension/Ban -->
  <div class="modal fade" id="suspendOrBanModal" tabindex="-1" aria-labelledby="suspendOrBanModalLabel" aria-hidden="true">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="suspendOrBanModalLabel">Suspend Group</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form id="suspendOrBanForm" method="POST" action="">
                  @csrf
                  @method('post')
                  <input type="hidden" name="action_type" id="actionType">
                  <input type="hidden" name="group_id" id="groupId">
                  <div class="modal-body">
                      <div class="mb-3">
                          <label for="suspensionReason" class="form-label">Reason for Suspension</label>
                          <textarea id="suspensionReason" name="suspension_reason" class="form-control" rows="4" required></textarea>
                      </div>
                      <div class="mb-3" id="durationField" style="display">
                          <label for="suspensionDuration" class="form-label">Suspension Duration</label>
                          <input type="date" id="suspensionDuration" class="form-control" name="duration">
                      </div>
                  </div>
                  <div class="modal-footer">
                      <button type="submit" class="btn btn-warning" onclick="setActionType('suspend')">Suspend</button>
                  </div>
              </form>
          </div>
      </div>
  </div>

  <script>
      function setActionType(type) {
          document.getElementById('actionType').value = type;

          const durationField = document.getElementById('durationField');
          durationField.style.display = type === 'suspend' ? 'block' : 'none';
      }

      function openModal(groupId) {
          document.getElementById('groupId').value = groupId;
          document.getElementById('suspendOrBanForm').action =
              `{{ url('admin/reports/group/suspend') }}/${groupId}`;
          new bootstrap.Modal(document.getElementById('suspendOrBanModal')).show();
      }
  </script>
