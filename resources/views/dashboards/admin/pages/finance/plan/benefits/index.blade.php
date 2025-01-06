@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Plan Details</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.plans.index') }}">Plans</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Benefits</li>
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
                    <div class="">
                        <h5>Plans</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <div class="table-responsive">
                            <table class="table text-nowrap table-hover border table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th scope="col">Description</th>
                                        <th scope="col">Durations</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($plans as $plan)
                                        <tr>
                                            <td>{{ $plan->name }}</td>
                                            <td><span class="fw-normal">{{ Str::limit($plan->description, 50) ?? 'N/A' }}</span></td>
                                            <td>
                                                @if ($plan->durations->isNotEmpty())
                                                    <ul class="list-unstyled mb-0">
                                                        @foreach ($plan->durations as $duration)
                                                            <li>
                                                                <strong>{{ ucfirst($duration->frequency) }} ({{ $duration->duration }} Days):</strong> 
                                                                {{ format_money($duration->price, 2, "$") }}
                                                                @if (!empty($duration->discount))
                                                                    <span class="text-success">(-{{ $duration->discount }}%)</span>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <span class="text-muted">No durations available</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ pillClasses($plan->status) }}-transparent">
                                                    {{ $plan->status }}
                                                </span>
                                            </td>
                                            <td>{{ $plan->created_at->format('Y-m-d h:i A') }}</td>
                                            <td>
                                                <div class="hstack gap-2 fs-15">
                                                   
                                                    <a href="{{ route('admin.plans.edit', $plan->id) }}" class="btn btn-icon btn-sm btn-info-light">
                                                        <i class="ri-edit-line"></i>
                                                    </a>
                                                   
                                                    <a class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-danger-light" href="#" onclick="openMultipleDeleteModal('{{ route('admin.plans.destroy', $plan->id) }}')" data-bs-toggle="tooltip"
                                                        title="Delete this plan">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        @include('dashboards.admin.pages.finance.plan.country-pricing.delete-modal')
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">
                                                <div class="alert alert-info mb-0">No records found</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        
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
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header d-flex justify-content-between">
                    <div class="">
                        <h5>Benefits</h5>
                    </div>
                    <div class="">
                        <a data-bs-toggle="modal" data-bs-target="#createBenefit" class="btn btn-primary btn-sm"><i class="fe fe-plus"></i> <span class="ml-3">Create</span></a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Title</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($plan_benefits as $plan_benefit)
                                    <tr>
                                        <td>{{ $plan_benefit->title }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($plan_benefit->status) }}-transparent">
                                                {{ $plan_benefit->status }}
                                            </span>
                                        </td>
                                        <td>{{ $plan_benefit->created_at->format('Y-m-d h:i A') }}</td>
                                        <td class="d-flex justify-content-center">
                                            <div class="hstack gap-2 fs-15">
                                                <a aria-label="anchor" data-bs-toggle="modal" data-bs-target="#updateBenefit_{{ $plan_benefit->id }}" class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-info-light"><i class="ri-edit-line"></i></a>
                                                <form action="{{ route('admin.plans.plan-benefits.destroy', [$plan->id, $plan_benefit->id]) }}" method="post" id="deletePlan_{{ $plan_benefit->id }}" onsubmit="return confirm('Are you sure of this action?')"> @csrf @method('delete')
                                                    <button type="submit" class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-danger-light"><i class="ri-delete-bin-line"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @include('dashboards.admin.pages.finance.plan.benefits.modals.update')
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
            @include('dashboards.admin.pages.finance.plan.benefits.modals.create')
        </div>
    </div>
@endsection
