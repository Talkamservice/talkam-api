@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Country Plans</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">Country Plans</li>
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
                            <input class="form-control" type="text" placeholder="Search...." name="search">
                        </div>
                        <div class="form-group">
                            <button class="btn btn-sm btn-success p-2">Filter</button>
                        </div>
                    </form>
                    <div class="">
                        <a href="{{ route('admin.country-plan-pricings.create') }}" class="btn btn-primary btn-sm"><i
                                class="fe fe-plus"></i> <span class="ml-3">Create</span></a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">S/N</th>
                                    <th scope="col">Country</th>
                                    <th scope="col">Lowered Cost</th>
                                    <th scope="col">Discount</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($country_plan_pricings->groupBy('country_id') as $country_id => $plans)
                                    @php
                                        $country_plan = $plans->first();
                                    @endphp
                                    <tr>
                                        <td>{{ $sn++ }}</td>
                                        <td>{{ $country_plan->country->name }} <small
                                                class="text-primary">({{ 'plus ' . $plans->count() - 1 . ' extra plan' }})</small>
                                        </td>
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
                                                <a aria-label="anchor"
                                                    href="{{ route('admin.country-plan-pricings.show', $country_plan->id) }}"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-success-light"><i
                                                        class="ri-eye-line"></i></a>
                                                <a aria-label="anchor"
                                                    href="{{ route('admin.country-plan-pricings.edit', $country_plan->id) }}"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-info-light"><i
                                                        class="ri-edit-line"></i></a>
                                                <a class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-danger-light"
                                                    href="#"
                                                    onclick="openDeleteModal('{{ route('admin.country-plan-pricings.destroy', $country_plan->id) }}')"
                                                    data-bs-toggle="tooltip" title="Delete this plan">
                                                    <i class="ri-delete-bin-line"></i>
                                                </a>
                                                <a aria-label="anchor" data-bs-toggle="tooltip"
                                                title="View Flutterwave Plans" target="_blank"
                                                href="{{route("admin.view-flutterwave-plans")}}"
                                                class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-success-light"><i
                                                    class="ri-external-link-line"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No records found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            Showing {{ $country_plan_pricings->firstItem() }} to {{ $country_plan_pricings->lastItem() }}
                            of
                            {{ $country_plan_pricings->total() }} entries
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                {{ $country_plan_pricings->links('pagination::bootstrap-4') }}
                            </ul>
                        </nav>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
