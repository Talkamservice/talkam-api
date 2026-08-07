@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Industries</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Business</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Industries</li>
                    </ol>
                </nav>
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
                            <input class="form-control" type="text" placeholder="Search...." name="search"
                                value="{{ request('search') }}">
                        </div>
                        <div class="form-group me-2" style="margin-top: 20px;">
                            <button class="btn btn-sm btn-success p-2">Filter</button>
                        </div>
                    </form>
                    <div class="">
                        <a href="{{ route('admin.industries.create') }}" class="btn btn-primary btn-sm"><i class="fe fe-plus"></i> <span class="ml-3">Create</span></a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Order</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($industries as $industry)
                                    <tr>
                                        <td>{{ $industry->sort_order }}</td>
                                        <td>{{ $industry->name }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($industry->status) }}-transparent">
                                                {{ $industry->status }}
                                            </span>
                                        </td>
                                        <td>{{ $industry->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="hstack gap-2 fs-15">
                                                <a aria-label="anchor" href="{{ route('admin.industries.edit', $industry->id) }}" class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-info-light"><i class="ri-edit-line"></i></a>

                                                <a data-bs-toggle="tooltip" title="Delete Industry"
                                                    class="dropdown-item text-danger" href="#"
                                                    onclick="openDeleteModal('{{ route('admin.industries.destroy', $industry->id) }}')">
                                                    <i class="ri-delete-bin-line"></i> | Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <div class="alert alert-info text-center mb-0">No record found</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $industries->withQueryString()->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('dashboards.admin.pages.delete-modal')
@endsection
