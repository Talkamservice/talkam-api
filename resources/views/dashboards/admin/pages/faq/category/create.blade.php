@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-name fw-semibold fs-18 mb-0">{{ isset($faq_category) ? 'Edit' : 'Create' }} Faq Category</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.faq-categories.index') }}">Faqs</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.faq-categories.index') }}">Categories</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($faq_category) ? 'Edit' : 'Create' }}</li>
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
                        <form action="{{ isset($faq_category) ? route('admin.faq-categories.update', $faq_category->id) : route('admin.faq-categories.store') }}" method="POST" enctype="multipart/form-data"> @csrf
                            @isset($faq_category)
                                @method('patch')
                            @endisset
                            <div class="gy-4 mb-4">
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Name</label>
                                    <div class="col-xl- col-lg-8 col-md-8 col-sm-12">
                                        <input type="text" class="form-control" name="name" id="input-placeholder" value="{{ old('name') ?? ($faq_category->name ?? '') }}" placeholder="Enter Name">
                                    </div>
                                </div>
                               
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Status</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="status" id="" class="form-control">
                                            <option value="" disabled selected>Select Option</option>
                                            @foreach ($statusOptions as $key => $value)
                                                <option value="{{ $key }}" {{ (old('status') ?? ($faq_category->status ?? '')) == $key ? 'selected' : '' }}>{{ $value }}</option>
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


