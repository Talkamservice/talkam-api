@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-name fw-semibold fs-18 mb-0">{{ isset($industry) ? 'Edit' : 'Create' }} Industry</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.industries.index') }}">Industries</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($industry) ? 'Edit' : 'Create' }}</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->
        <!-- Start:: row-1 -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <form
                            action="{{ isset($industry) ? route('admin.industries.update', $industry->id) : route('admin.industries.store') }}"
                            method="POST"> @csrf
                            @isset($industry)
                                @method('patch')
                            @endisset
                            <div class="gy-4 mb-4">
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="industry-name" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Name</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="text" class="form-control" name="name" id="industry-name"
                                            value="{{ old('name') ?? ($industry->name ?? '') }}" placeholder="Enter Name" required>
                                    </div>
                                </div>

                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="industry-order" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Sort order</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="number" min="0" class="form-control" name="sort_order" id="industry-order"
                                            value="{{ old('sort_order') ?? ($industry->sort_order ?? 0) }}" placeholder="0">
                                    </div>
                                </div>

                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="industry-status" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Status</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="status" id="industry-status" class="form-control">
                                            @foreach ($statusOptions as $key => $value)
                                                <option value="{{ $key }}"
                                                    {{ (old('status') ?? ($industry->status ?? \App\Constants\General\StatusConstants::ACTIVE)) == $key ? 'selected' : '' }}>
                                                    {{ $value }}</option>
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
