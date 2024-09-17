@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Permissions</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Permissions</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
                <div class="">
                </div>
            </div>
        </div>
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
                    {{-- <div class="">
                        <a href="javascript:void{}" data-bs-toggle="modal" data-bs-target="#addNewPermissionModal" class="btn btn-primary"><i class="fe fe-plus"></i>
                            <span class="ml-3">Add New</span></a>
                    </div> --}}
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th class="border-bottom">S/N</th>
                                    <th class="border-bottom">Name</th>
                                    <th class="border-bottom">Date Created</th>
                                    <th class="border-bottom">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($permissions as $permission)
                                    <tr>
                                        <td>
                                            <div>{{ $sn++ }}</div>
                                        </td>
                                        <td>
                                            <a href="#" class="d-flex align-items-center">
                                                <div class="d-block">
                                                    <span class="fw-normal">{{ str_replace('_', ' ', $permission->name) }}</span>
                                                </div>
                                            </a>
                                        </td>
                                        <td><span class="fw-normal">{{ $permission->created_at }}</span></td>
                                        <td>
                                            <div class="btn-group">
                                                <a aria-label="anchor" data-bs-toggle="modal" data-bs-target="#editPermissionModal_{{ $permission->id }}" class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-info-light"><i class="ri-edit-line"></i></a>
                                               
                                                <a data-bs-toggle="tooltip" title="Delete permission"
                                                class="dropdown-item text-danger" href="#"
                                                onclick="openDeleteModal('{{ route('admin.authorization.permissions.destroy', $permission->id) }}')">
                                                <i class="ri-delete-bin-line"></i> | Delete
                                            </a>
                                            </div>
                                        </td>
                                    </tr>
                                    @include('dashboards.admin.pages.authorization.modals.permissions.edit', ['permission' => $permission])
                                @endforeach
                            </tbody>
                        </table>
                        <div class="mt-2">
                            {{ $permissions->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
        @include('dashboards.admin.pages.authorization.modals.permissions.add')
        @include('dashboards.admin.pages.delete-modal')
    </div>
@endsection
