@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Sub Categories of <b>{{ $category->name }}</b></h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.post-categories.index') }}">Categories</b></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
                <div class="">
                </div>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Start::row-1 -->
        <div class="row">
            <div class="col-xxl-4 col-xl-4">
                <div class="col-xxl-12 col-xl-12">
                    <div class="card custom-card overflow-hidden">
                        <div class="card-body p-0">
                            <div class="d-sm-flex align-items-top p-4 border-bottom-0 main-profile-cover">
                                <div>
                                    <span class="avatar avatar-xxl avatar-rounded online me-3">
                                        <img src="{{ $category->image }}" alt="">
                                    </span>
                                </div>
                                <div class="flex-fill main-profile-info">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h6 class="fw-semibold mb-1 text-fixed-white">{{ $category->name }}</h6>
                                        <button
                                            class="btn bg-white btn-outline-{{ pillClasses($category->status) }} btn-sm btn-wave">
                                            {{ $category->status }}</button>
                                    </div>
                                    <div class="d-flex mb-0">
                                        <div class="me-4">
                                            <p class="fw-bold fs-23 text-fixed-white text-shadow mb-0">
                                                {{ $category->posts?->count() }}</p>
                                            <p class="mb-0 fs-14 text-fixed-white">Posts</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 border-bottom border-block-end-dashed">
                                <p class="fs-15 mb-2 me-4 fw-semibold">Category Information :</p>
                                <div class="text-muted">
                                    <p class="mb-2">
                                        <b>Name:</b> {{ $category->name ?? 'N/A' }}
                                    </p>
                                    <p class="mb-2">
                                        <b>Description:</b> {{ $category->description ?? 'N/A' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-8 col-xl-8">
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
                            <a href="{{ route('admin.categories.sub-categories.create-sub-category', $category->id) }}"
                                class="btn btn-primary btn-sm"><i class="fe fe-plus"></i> <span class="ml-3">Create</span></a>
                        </div>
                    </div>
                    <div class="card-body">
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
                                    @forelse ($categories as $subcategory)
                                        <tr>
                                            <td class="d-flex justify-content-center">
                                                <img src="{{ $subcategory->image }}" alt=""
                                                    style="width: 50px; height:50px; border-radius:10px">
                                            </td>
                                            <td><img src="{{ $subcategory->icon_image }}" alt=""
                                                    style="width: 30px; height:30px; border-radius:10px"></td>
                                            <td>{{ $subcategory->name }}</td>
                                            <td><span class="fw-normal"><a data-bs-toggle="modal"
                                                        data-bs-target="#responseModal_{{ $subcategory->id }}"
                                                        class="btn btn-primary btn-sm">Description</a></span></td>
                                            <td>
                                                <span class="badge bg-{{ pillClasses($subcategory->status) }}-transparent">
                                                    {{ $subcategory->status }}
                                                </span>
                                            </td>
                                            <td>{{ $subcategory->created_at->format('Y-m-d h:i A') }}</td>
                                            <td>
                                                <div class="hstack gap-2 fs-15">
                                                    <a aria-label="anchor"
                                                        href="{{ route('admin.categories.sub-categories.edit-sub-category', [$category->id, $subcategory->id]) }}"
                                                        class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-info-light"><i
                                                            class="ri-edit-line"></i></a>
                                                    <!-- Delete Subcategory Button -->
                                                    <button data-bs-toggle="tooltip" title="Delete Subcategory"
                                                        type="button"
                                                        class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-danger-light ms-2"
                                                        onclick="openDeleteModal('{{ route('admin.categories.sub-categories.delete-sub-category', [$category->id, $subcategory->id]) }}')">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        @include('dashboards.admin.pages.content.modals.category_info', [
                                            'modalKey' => "responseModal_$subcategory->id",
                                            'modalContent' => $subcategory->description,
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
                                {{ $categories->links('pagination::bootstrap-4') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('dashboards.admin.pages.delete-modal')
@endsection
