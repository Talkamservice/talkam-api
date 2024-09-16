@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Roles</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Roles</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
                <div class="">
                </div>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Start::row-1 -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header d-flex justify-content-between">
                    <form action="{{ url()->current() }}" method="get" class="d-flex justify-content-between">
                        <div class="form-group me-2">
                            <label for="">Search</label>
                            <input class="form-control" type="text" placeholder="Search...." name="search">
                        </div>
                        <div class="form-group me-2" style="margin-top: 20px;">
                            <button class="btn btn-sm btn-success p-2">Filter</button>
                        </div>
                    </form>
                    <div class="">
                        <a data-bs-toggle="modal" data-bs-target="#addNewRoleModal" class="btn btn-primary"><i class="fe fe-plus"></i> <span class="ml-3">Create</span></a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Created At</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($roles as $role)
                                    <tr>
                                        <td>{{ str_replace("_", " ", $role->name) }}</td>
                                        <td>{{ $role->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="hstack gap-2 fs-15">
                                                <a href="{{ route('admin.authorization.roles.show', $role->id) }}" class="btn btn-sm btn-primary d-inline-flex align-items-center">
                                                    Permissions
                                                </a>
                                                <a aria-label="anchor" data-bs-toggle="modal" data-bs-target="#editRoleModal_{{ $role->id }}"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-info-light"><i
                                                        class="ri-edit-line"></i></a>
                                                
                                                <a data-bs-toggle="tooltip" title="Delete role"
                                                class="dropdown-item text-danger" href="#"
                                                onclick="openDeleteModal('{{ route('admin.authorization.roles.destroy', $role->id) }}')">
                                                <i class="ri-delete-bin-line"></i> | Delete
                                            </a>
                                            </div>
                                        </td>
                                    </tr>
                                    @include('dashboards.admin.pages.authorization.modals.role.edit', [
                                        "role" => $role
                                    ])
                                @empty
                                    <div class="alert alert-info text-center">
                                        No record found
                                    </div>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            {{ $roles->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
            @include('dashboards.admin.pages.authorization.modals.role.add')
            @include('dashboards.admin.pages.delete-modal')
        </div>
    </div>
@endsection
