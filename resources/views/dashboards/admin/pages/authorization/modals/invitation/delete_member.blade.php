<div class="modal fade" id="removeAsAdminForm_{{ $admin->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document" style="width: 390px">
        <div class="modal-content" style="width: 100%">
            <div class="modal-body">
                <form class="deleteForm_{{ $admin->id }}"
                    action="{{ route('admin.members.delete-member', $admin->id) }}" method="post"
                    onsubmit="return confirm('Are you sure of this action?')">
                    @csrf
                    <input type="hidden" name="member_id" value="{{ $admin->id }}">
                    <input type="hidden" name="source" value="admin">
                    <div class="d-flex justify-content-between">
                        <img src="{{ asset('admin_assets/images/action/reject-icon.png') }}"
                            style="width: 45px; height:46px" alt="">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="mt-3 mb-3">
                        <h5 class="modal-title">Remove Admin Membership</h5>
                    </div>

                    <div class="mb-4">
                        Do you want to remove this membership record along with the user account?
                    </div>

                    <div class="d-flex justify-content-around mt-3">
                        <button type="button" class="btn btn-danger text-white"
                            style="padding: 8px 40px; margin-left:20px"
                            onclick="submitForm('yes', {{ $admin->id }})">Yes</button>
                        <button type="button" class="btn btn-outline-danger text-dark"
                            style="padding: 8px 40px; margin-right:20px"
                            onclick="submitForm('no', {{ $admin->id }})">No</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
