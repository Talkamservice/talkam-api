@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Plans</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Plans</a></li>
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
                            <label for="">Search</label>
                            <input class="form-control" type="text" placeholder="Search...." name="search">
                        </div>
                        <div class="form-group me-2" style="margin-top: 20px;">
                            <button class="btn btn-sm btn-success p-2">Filter</button>
                        </div>
                    </form>
                    <div class="">
                        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary"><i class="fe fe-plus"></i> <span
                                class="ml-3">Create</span></a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Duration (Days)</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($plans as $plan)
                                    <tr>
                                        <td>{{ $plan->name }}</td>
                                        <td>{{ $plan->defaultDuration()?->price }}</td>
                                        <td>{{ $plan->defaultDuration()?->duration }}</td>
                                        <td><span class="fw-normal">{{ str_limit($plan->description) ?? 'N/A' }}</span></td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($plan->status) }}-transparent">
                                                {{ $plan->status }}
                                            </span>
                                        </td>
                                        <td>{{ $plan->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="hstack gap-2 fs-15">
                                                <a aria-label="anchor" href="{{ route('admin.plans.show', $plan->id) }}"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-success-light"><i
                                                        class="ri-eye-line"></i></a>
                                                <a aria-label="anchor" href="{{ route('admin.plans.edit', $plan->id) }}"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-info-light"><i
                                                        class="ri-edit-line"></i></a>
                                                <form action="{{ route('admin.plans.destroy', $plan->id) }}" method="post"
                                                    id="deletePlan_{{ $plan->id }}"
                                                    onsubmit="return confirm('Are you sure of this action?')"> @csrf
                                                    @method('delete')
                                                    <button type="submit"
                                                        class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-danger-light"><i
                                                            class="ri-delete-bin-line"></i></button>
                                                </form>
                                                <!-- Subscription Icon - Opens Modal -->
                                                <a type="button"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-primary-light"
                                                    data-bs-toggle="modal" data-bs-target="#subscriptionModal"
                                                    data-bs-toggle="tooltip" title="Manage Subscription">
                                                    <i class="ri-wallet-line"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    @include(
                                        'dashboards.admin.pages.finance.subscription.subscribe-modal',
                                        ['users', $users, 'plan' => $plan]
                                    )

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
