  <!-- Modal for Group Suspension/Ban -->
  <div class="modal fade" id="suspendOrBanModal" tabindex="-1" aria-labelledby="suspendOrBanModalLabel" aria-hidden="true">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="suspendOrBanModalLabel">Suspend or Ban Group</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form id="suspendOrBanForm" method="POST" action="">
                  @csrf
                  @method('post')
                  <input type="hidden" name="action_type" id="actionType">
                  <input type="hidden" name="group_id" id="groupId">
                  <div class="modal-body">
                      <div class="mb-3">
                          <label for="suspensionReason" class="form-label">Reason for Suspension/Ban</label>
                          <textarea id="suspensionReason" name="suspension_reason" class="form-control" rows="4" required></textarea>
                      </div>
                      <div class="mb-3" id="durationField" style="display">
                          <label for="suspensionDuration" class="form-label">Suspension Duration</label>
                          <select id="suspensionDuration" name="duration" class="form-select">
                              <option value="1">24-48 hours</option>
                              <option value="2">7 days</option>
                              <option value="3">30 days</option>
                          </select>
                      </div>
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                      <button type="submit" class="btn btn-danger" onclick="setActionType('ban')">Ban</button>
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
              `{{ url('admin/reports/group/suspend-or-ban') }}/${groupId}`;
          new bootstrap.Modal(document.getElementById('suspendOrBanModal')).show();
      }
  </script>
