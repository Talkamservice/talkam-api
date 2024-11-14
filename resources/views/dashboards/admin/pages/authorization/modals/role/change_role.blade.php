<div class="modal fade" id="changeRoleModal_{{$admin->id }}" tabindex="-1" role="dialog" aria-labelledby="addNewRoleModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ $form_url }}" method="post" enctype="multipart/form-data"> @csrf
                <div class="modal-header">
                    @if (!empty(($names = optional($admin->user)->name)))
                        <h6 class="modal-title" id="exampleModalLabel">Change Role of {{ $names }}</h6>
                    @else
                        <h5 class="modal-title" id="exampleModalLabel">Change Role</h5>
                    @endif
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" value="{{ $source }}" name="source">
                    <input type="hidden" value="{{$admin->id }}" name="member_id">

                    <div class="form-group">
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
