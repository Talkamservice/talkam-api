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
                            <input type="hidden" name="plan_benefit_id" value="{{ $plan_benefit->id }}">
                            <div class="gy-4">
                                <div class="row col-xl-10 col-sm-12 mb-3">
                                    <label for="input-placeholder" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Benefit</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="text" class="form-control" name="title" id="input-placeholder" value="{{ old('title') ?? ($plan_benefit->title ?? '') }}" placeholder="Enter benefit">
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
        <!-- End:: row-1 -->
    </div>
@endsection
