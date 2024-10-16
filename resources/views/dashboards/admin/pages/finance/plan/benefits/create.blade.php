@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">{{ isset($plan_benefit) ? 'Edit' : 'Create' }} Plan</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.plans.index') }}">Plans</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.plans.plan-benefits.index', $plan->id) }}">Benefits of {{ $plan->name }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($plan_benefit) ? 'Edit' : 'Create' }}</li>
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
                        <form action="{{ isset($plan_benefit) ? route('admin.plans.plan-benefits.update', [$plan->id, $plan_benefit->id]) : route('admin.plans.plan-benefits.store', $plan->id) }}" method="POST" enctype="multipart/form-data"> @csrf
                            @isset($plan_benefit)
                              @method('put')
                            @endisset
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <div class="gy-4">
                                <div class="form-group form-row mb-2">
                                    <label for="input-placeholder" class="form-label">Title</label>
                                    <input type="text" class="form-control" name="title" value="{{ old('title') ?? ($plan_benefit->title ?? '') }}" id="input-placeholder" placeholder="Enter benefit title">
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                                    <label for="input-placeholder" class="form-label">Description</label>
                                    <input type="text" class="form-control" name="description" value="{{ old('description') ?? ($plan_benefit->description ?? '') }}" id="input-placeholder" placeholder="Enter plan description">
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                                    <label for="input-placeholder" class="form-label">Value</label>
                                    <input type="text" class="form-control" name="value" value="{{ old('value') ?? ($plan_benefit->value ?? '') }}" id="input-placeholder" placeholder="Enter benefit value">
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                                    <label for="input-placeholder" class="form-label">Type</label>
                                    <select name="value_type" id="" class="form-control">
                                        <option value="" disabled selected>Select Option</option>
                                        @foreach ($typeOptions as $key => $value)
                                            <option value="{{ $key }}" {{ (old('value_type') ?? ($plan_benefit->value_type ?? '')) == $key ? 'selected' : '' }}>{{ $value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                                    <label for="input-placeholder" class="form-label">Status</label>
                                    <select name="status" id="" class="form-control">
                                        <option value="" disabled selected>Select Option</option>
                                        @foreach ($statusOptions as $key => $value)
                                            <option value="{{ $key }}" {{ (old('status') ?? ($plan_benefit->status ?? '')) == $key ? 'selected' : '' }}>{{ $value }}</option>
                                        @endforeach
                                    </select>
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
        <!-- End:: row-1 -->
    </div>
@endsection
