@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Advertising Costs</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">Advertising Costs</li>
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
                            <input class="form-control" type="text" placeholder="Search...." name="search"
                                value="{{ request()->search }}">
                        </div>
                        <div class="form-group">
                            <button class="btn btn-sm btn-success p-2">Filter</button>
                        </div>
                    </form>
                    <div class="">
                        <a href="{{ route('admin.promotion-pricings.create') }}" class="btn btn-primary btn-sm"><i
                                class="fe fe-plus"></i> <span class="ml-3">Create</span></a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column align-items-start mb-2 alert alert-info">
                        <div class="p-2">
                            <p class="mb-0">
                                <span class="fw-bold fs-6 text-dark">Note:</span>
                                <span class="fw-bold text-dark"></b>The daily generated impressions per post (<b>{{ number_format($post_performance->avg_impressions_per_day ?? 0) }}</b>) will be used as default for countries without specific settings.
                                </span>
                            </p>
                        </div>
                     
                    </div>

                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">S/N</th>
                                    <th scope="col">Country</th>
                                    {{-- <th scope="col">Currency</th> --}}
                                    <th scope="col">Amount</th>
                                    {{-- <th scope="col">Max Daily Amount</th> --}}
                                    <th scope="col">Impressions</th>
                                    <th scope="col">Default</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($promotion_pricings as $country_id => $promotion_pricing)
                                    <tr>
                                        <td>{{ $sn++ }}</td>
                                        <td>{{ $promotion_pricing->country->name }}</td>
                                        {{-- <td>{{ $promotion_pricing->currency->name }}</td> --}}
                                        <td>{{ $promotion_pricing->formattedAmount() }}</td>
                                        {{-- <td>{{ $promotion_pricing->formattedAmount('max_daily_amount') }}</td> --}}
                                        <td>{{ number_format($promotion_pricing->impressions) }}</td>
                                        <td>{{ $promotion_pricing->default ? 'Yes' : 'No' }}</td>
                                        <td>
                                            <span
                                                class="badge bg-{{ pillClasses($promotion_pricing->status) }}-transparent">
                                                {{ $promotion_pricing->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="hstack gap-2 fs-15">
                                                {{-- <a aria-label="anchor" href="{{ route('admin.promotion-pricings.show', $promotion_pricing->id) }}" class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-success-light"><i class="ri-eye-line"></i></a> --}}
                                                <a aria-label="anchor"
                                                    href="{{ route('admin.promotion-pricings.edit', $promotion_pricing->id) }}"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-info-light"><i
                                                        class="ri-edit-line"></i></a>
                                                <a class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-danger-light"
                                                    href="#"
                                                    onclick="openMultipleDeleteModal('{{ route('admin.promotion-pricings.destroy', $promotion_pricing->id) }}')"
                                                    data-bs-toggle="tooltip" title="Delete this plan">
                                                    <i class="ri-delete-bin-line"></i>
                                                </a>
                                                {{-- <a aria-label="anchor" data-bs-toggle="tooltip"
                                                    title="View Flutterwave Plans. For developers testing only. would be remove before it goes live"
                                                    target="_blank" href="{{ route('admin.view-flutterwave-plans') }}"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-success-light"><i
                                                        class="ri-external-link-line"></i></a> 
                                                        --}}
                                            </div>
                                        </td>
                                    </tr>
                                    @include('dashboards.admin.pages.finance.plan.country-pricing.delete-modal')
                                @empty
                                    <div class="alert alert-info text-center">
                                        No records found
                                    </div>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                {{ $promotion_pricings->links('pagination::bootstrap-4') }}
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
