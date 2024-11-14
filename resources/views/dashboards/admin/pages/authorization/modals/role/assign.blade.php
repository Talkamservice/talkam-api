<div class="modal fade" id="assignNewRoleModal" tabindex="-1" role="dialog" aria-labelledby="addNewRoleModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.invite.sendInvite') }}" method="post" enctype="multipart/form-data" onsubmit="return confirm('Are you sure of this action?')"> @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Assign Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" value="{{ $source }}" name="source">
                    <div class="form-group">
                        <label for="">Enter emails (Seperate with comma) <span class="required">*</span></label>
                        <input class="form-control" name="emails" value="{{ old('emails') }}" multiple placeholder="johndoe@gmail.com, janedoe@gmail.com" />
                    </div>

                    <div class="form-group mt-3">
                        <label for="">Select Role <span class="required">*</span></label>
                        <select name="role_id" class="form-control" id="" required>
                            <option value="" disabled selected>Select Option</option>
                            @foreach ($roles as $key => $role)
                                <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn" data-bs-dismiss="modal"><i class="flaticon-cancel-12"></i> Discard</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
