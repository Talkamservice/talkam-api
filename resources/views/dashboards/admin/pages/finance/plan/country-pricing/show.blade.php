@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">{{ $country_plan_pricing->country->name }} Pricing</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{route('admin.country-plan-pricings.index')}}">Country Pricing</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $country_plan_pricing->country->name }}</li>
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
               
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">S/N</th>
                                    <th scope="col">Plan Name</th>
                                    <th scope="col">Default Cost</th>
                                    <th scope="col">Lowered Cost</th>
                                    <th scope="col">Percentage (%)</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($country_plan_pricing_providers as $country_plan_pricing_provider)
                                    <tr>
                                        <td>{{ $sn++ }}</td>
                                        <td>{{ $country_plan_pricing_provider->plan->name }} - {{ $country_plan_pricing_provider->planDuration?->frequency }}</td>
                                        <td>{{ format_money($country_plan_pricing_provider->planDuration?->price) }}</td>
                                        <td>{{ format_money($country_plan_pricing_provider->price) }}</td>
                                        <td>{{ $country_plan_pricing_provider->getPercentage()  }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($country_plan_pricing_provider->status) }}-transparent">
                                                {{ $country_plan_pricing_provider->status }}
                                            </span>
                                        </td>
                                        <td>{{ $country_plan_pricing_provider->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="hstack gap-2 fs-15">
                                                
                                                <a class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-danger-light"
                                                    href="#"
                                                    onclick="openDeleteModal('{{ route('admin.country-plan-pricings.provider.delete', [$country_plan_pricing->id, $country_plan_pricing_provider->id]) }}')"
                                                    data-bs-toggle="tooltip" title="Delete this plan">
                                                    <i class="ri-delete-bin-line"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    @include('dashboards.admin.pages.delete-modal')
                                @empty
                                    <div class="alert alert-info text-center">
                                        No records found
                                    </div>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            {{ $country_plan_pricing_providers->links("pagination::bootstrap-4") }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
