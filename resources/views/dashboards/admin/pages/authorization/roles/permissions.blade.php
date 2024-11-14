@extends('dashboards.admin.layout.app')
@section('content')
    <!-- Page Header -->
    <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
        <h1 class="page-title fw-semibold fs-18 mb-0">Roles</h1>
        <div class="ms-md-1 ms-0">
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item" aria-current="page"><a href="{{ route('admin.authorization.roles.index') }}">Roles</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Permissions</li>
                </ol>
            </nav>
            <div class="">
            </div>
        </div>
    </div>
    <!-- Page Header Close -->
    <div class="card card-body shadow border-0 table-wrapper table-responsive">
        <form action="{{ route('admin.authorization.roles.update_permissions', $role->id) }}" method="post" onsubmit="return confirm('Are you sure you want to assign these permissions to this role?')">
            @csrf
            <table class="table user-table table-hover align-items-center">
                <thead>
                    <tr>
                        <th class="border-bottom">
                            <div class="form-check dashboard-check">
                                <input class="form-check-input" type="checkbox" id="permissionCheck">
                                <label class="form-check-label" for="userCheck55">
                                </label>
                            </div>
                        </th>
                        <th class="border-bottom">Permission</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissions as $permission)
                        <tr>
                            <td>
                                <div class="form-check dashboard-check">
                                    <input class="form-check-input" type="checkbox" {{ $permission->hasRole($role->id) ? 'checked' : '' }} name="checked_permissions[]" value="{{ $permission->id }}" id="userCheck1">
                                    <label class="form-check-label" for="userCheck1">
                                    </label>
                                </div>
                            </td>
                            <td>
                                <a href="#" class="d-flex align-items-center">
                                    <div class="d-block">
                                        <span class="fw-bold">{{ str_replace('_', ' ', $permission->name) }}</span>
                                    </div>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-4">
                <div class="btn-toolbar mt-2 mb-md-0">
                    <button type="submit" class="btn btn-sm btn-primary d-inline-flex align-items-center">
                        Update
                    </button>
                </div>
            </div>
        </form>
        <input type="hidden" value="{{ $permissions->count() }}" id="permissionCount">
    </div>
@endsection

@section('script')
    <script>
        $(window).on("load", function() {
            let count_checked_permissions = 0;
            $("input[name]").each(function(key, value) {
                if ($(this).is(':checked')) {
                    count_checked_permissions++
                };
            });

            if (count_checked_permissions == parseInt($("#permissionCount").val())) {
                $("#permissionCheck").click();
            }
        });


        $("#permissionCheck").click(function() {
            if ($(this).is(':checked')) {
                $("input[name]").each(function(key, value) {
                    $(value).prop('checked', true);
                });
            } else {
                $("input[name]").each(function(key, value) {
                    $(value).prop('checked', false);
                });
            }
        });
    </script>
@endsection
