@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Categories</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Categories</a></li>
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
                        <a href="{{ route('admin.post-categories.create') }}" class="btn btn-primary btn-sm"><i
                                class="fe fe-plus"></i> <span class="ml-3">Create</span></a>
                    </div>

                </div>

                <div class="card-body">
                    <div class="p-2">
                        <p class="text-danger text-center fw-bold">
                            <span class="fw-bold">Disclaimer!!!</span>: Deleting a category will remove all posts and other
                            content associated with it.
                        </p>
                    </div>

                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Background Image</th>
                                    <th scope="col">Icon</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($categories as $category)
                                    <tr>
                                        <td class="d-flex justify-content-center">
                                            <span>
                                                <img src="{{ $category->image }}" alt=""
                                                    style="width: 50px; height:50px: border-radius:10px">
                                            </span>
                                        </td>
                                        <td><img src="{{ $category->icon_image }}" alt=""
                                                style="width: 30px; height:30px: border-radius:10px"></td>
                                        <td>{{ $category->name }}</td>
                                        <td>{{ str_limit($category->description, 30) }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($category->status) }}-transparent">
                                                {{ $category->status }}
                                            </span>
                                        </td>
                                        <td>{{ $category->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="hstack gap-2 fs-15">
                                                <a data-bs-toggle="tooltip" title="Sub Categories" aria-label="anchor"
                                                    href="{{ route('admin.categories.sub-categories.index', $category->id) }}"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-primary-light"><i
                                                        class="ri-eye-line"></i></a>
                                                <a data-bs-toggle="tooltip" title="Edit Category" aria-label="anchor"
                                                    href="{{ route('admin.post-categories.edit', $category->id) }}"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-info-light"><i
                                                        class="ri-edit-line"></i></a>
                                                <!-- Delete Button -->
                                                <button data-bs-toggle="tooltip" title="Delete Category" type="button"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-danger-light ms-2"
                                                    onclick="openDeleteModal('{{ route('admin.post-categories.destroy', $category->id) }}')">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
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
                            {{ $categories->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal HTML -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirm Action</h5>
                    <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalMessage">
                    <!-- Dynamic confirmation message -->
                </div>
                <div class="modal-footer">
                    <form id="confirmationForm" action="" method="POST" style="display:inline;">
                        @csrf
                        <div id="methodFieldContainer"></div> <!-- Placeholder for method spoofing -->
                        <button type="submit" class="btn btn-primary">Yes, Proceed</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

  @include('dashboards.admin.pages.delete-modal')
@endsection
