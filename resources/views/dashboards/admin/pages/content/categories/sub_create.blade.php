@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-name fw-semibold fs-18 mb-0">{{ isset($sub_category) ? 'Edit' : 'Create' }} Sub Category</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.categories.sub-categories.index', $category->id) }}">Sub Categories</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($sub_category) ? 'Edit' : 'Create' }}</li>
                    </ol>
                </nav>
                <div class="">
                </div>
            </div>
        </div>
        <!-- Page Header Close -->
        <!-- Start:: row-1 -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <form action="{{ isset($sub_category) ? route('admin.categories.sub-categories.update-sub-category', [$category->id, $sub_category->id]) : route('admin.categories.sub-categories.save-sub-category', $category->id) }}" method="POST" enctype="multipart/form-data"> @csrf
                            @isset($sub_category)
                                @method('patch')
                            @endisset
                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                            <div class="gy-4 mb-4">
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Background Image</label>

                                    @if (!isset($sub_category) || empty($sub_category?->image ?? null))
                                        <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                            <input type="file" name="image" class="form-control" id="input-placeholder">
                                        </div>
                                    @else
                                        <div class="row col-xl-10 col-lg-10 col-md-10 col-sm-12">
                                            <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                                <input type="file" class="form-control" name="image" id="input-placeholder">
                                            </div>
                                            @if (!empty($sub_category->image))
                                                <div class="col-xl-4 col-lg-4 col-md-4 col-sm-2">
                                                    <a href="{{ $sub_category->image }}" target="_blank" class="btn btn-outline-info btn-sm">Preview Current</a>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Icon</label>

                                    @if (!isset($sub_category) || empty($sub_category?->icon_image ?? null))
                                        <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                            <input type="file" name="icon_image" class="form-control" id="input-placeholder">
                                        </div>
                                    @else
                                        <div class="row col-xl-10 col-lg-10 col-md-10 col-sm-12">
                                            <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                                <input type="file" class="form-control" name="icon_image" id="input-placeholder">
                                            </div>
                                            @if (!empty($sub_category->icon_image))
                                                <div class="col-xl-4 col-lg-4 col-md-4 col-sm-2">
                                                    <a href="{{ $sub_category->icon_image }}" target="_blank" class="btn btn-outline-info btn-sm">Preview Current</a>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Name</label>
                                    <div class="col-xl- col-lg-8 col-md-8 col-sm-12">
                                        <input type="text" class="form-control" name="name" id="input-placeholder" value="{{ old('name') ?? ($sub_category->name ?? '') }}" placeholder="Enter name">
                                    </div>
                                </div>
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Description</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <textarea name="description" class="form-control" name="description" id="" cols="30" rows="2">{{ old('description') ?? ($sub_category->description ?? '') }}</textarea>
                                    </div>
                                </div>
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Status</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="status" id="" class="form-control">
                                            <option value="" disabled selected>Select Option</option>
                                            @foreach ($statusOptions as $key => $value)
                                                <option value="{{ $key }}" {{ (old('status') ?? ($sub_category->status ?? '')) == $key ? 'selected' : '' }}>{{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-success">Submit</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer d-none border-top-0">
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

