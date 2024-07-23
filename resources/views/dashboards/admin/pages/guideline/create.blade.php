@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-name fw-semibold fs-18 mb-0">{{ isset($guideline) ? 'Edit' : 'Create' }} Guideline</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.guidelines.index') }}">Guideline</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($guideline) ? 'Edit' : 'Create' }}</li>
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
                        <form action="{{ isset($guideline) ? route('admin.guidelines.update', $guideline->id) : route('admin.guidelines.store') }}" method="POST" enctype="multipart/form-data"> @csrf
                            @isset($guideline)
                                @method('patch')
                            @endisset
                            <div class="gy-4 mb-4">
                               
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Title</label>
                                    <div class="col-xl- col-lg-8 col-md-8 col-sm-12">
                                        <input type="text" class="form-control" name="title" id="input-placeholder" value="{{ old('title') ?? ($guideline->title ?? '') }}" placeholder="Enter Title">
                                    </div>
                                </div>
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Description</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <textarea name="description" class="form-control" name="description" id="" cols="30" rows="2">{{ old('description') ?? ($guideline->description ?? '') }}</textarea>
                                    </div>
                                </div>
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Status</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="status" id="" class="form-control">
                                            <option value="" disabled selected>Select Option</option>
                                            @foreach ($statusOptions as $key => $value)
                                                <option value="{{ $key }}" {{ (old('status') ?? ($guideline->status ?? '')) == $key ? 'selected' : '' }}>{{ $value }}</option>
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

