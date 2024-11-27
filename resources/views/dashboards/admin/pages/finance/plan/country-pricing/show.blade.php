@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Country Plans</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{route('admin.country-plan-pricings.index')}}">Plans</a></li>
                        <li class="breadcrumb-item">Country Plans</li>
                        <li class="breadcrumb-item active" aria-current="page">Show</li>
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
                            <input class="form-control" type="text" placeholder="Search...." name="search">
                        </div>
                        <div class="form-group">
                            <button class="btn btn-sm btn-success p-2">Filter</button>
                        </div>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Country</th>
                                    <th scope="col">Plan Name</th>
                                    <th scope="col">Default Cost</th>
                                    <th scope="col">Lowered Cost (%)</th>
                                    <th scope="col">Discount</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($country_plan_pricings as $country_plan)
                                    <tr>
                                        <td>{{ $country_plan->country->name }}</td>
                                        <td>{{ $country_plan->plan->name }}</td>
                                        <td>{{ $country_plan->plan->defaultDuration()?->formattedAmount() }}</td>
                                        <td>{{ $country_plan->lowered_cost }}%</td>
                                        <td>{{ $country_plan->formattedAmount() }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($country_plan->status) }}-transparent">
                                                {{ $country_plan->status }}
                                            </span>
                                        </td>
                                        <td>{{ $country_plan->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="hstack gap-2 fs-15">
                                                
                                                <a class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-danger-light"
                                                    href="#"
                                                    onclick="openDeleteModal('{{ route('admin.country-plan-pricings.destroy', $country_plan->id) }}')"
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
                {{-- <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            Showing 5 Entries
                        </div>
                    </div>
                </div> --}}
            </div>
        </div>
    </div>
@endsection
