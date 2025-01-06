@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">{{ $promotion_status }} Promotion</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">{{ $promotion_status }} Promotion</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
                <div class="">
                </div>
            </div>
        </div>
        <!-- Page Header Close -->

        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header d-flex justify-content-between">
                    <form action="{{ url()->current() }}" method="get" id="filter-form"
                        class="d-flex justify-content-between gap-3">
                        <div class="form-group me-2">
                            <input class="form-control" type="text" placeholder="Search...." name="search"
                                value="{{ request()->search }}">
                        </div>
                        <div class="form-group me-2">
                            <select name="type" class="form-control">
                                <option value="">Select Type</option>
                                <option value="Group" {{ request()->type == 'Group' ? 'selected' : '' }}>Group</option>
                                <option value="Post" {{ request()->type == 'Post' ? 'selected' : '' }}>Post</option>
                            </select>
                        </div>
                        <div class="form-group me-2">
                            <select name="status" class="form-control">
                                <option value="">Select Status</option>
                                <option value="Completed" {{ request()->status == 'Completed' ? 'selected' : '' }}>Completed
                                </option>
                                <option value="Active" {{ request()->status == 'Active' ? 'selected' : '' }}>Active
                                </option>
                                <option value="Pending" {{ request()->status == 'Pending' ? 'selected' : '' }}>Ongoing
                                </option>
                                <option value="Inactive" {{ request()->status == 'Inactive' ? 'selected' : '' }}>Inactive
                                </option>
                            </select>
                        </div>
                        <div class="ms-2">
                            <select class="form-control currency-select" id="currency-select" name="currency">
                                <option value="All" {{ !request()->currency || request()->currency == 'All' ? 'selected' : '' }}>All</option>
                                @if (!in_array('Nigerian Naira (NGN)', \App\Constants\Finance\Currency\CurrencyConstants::CURRENCY_OPTIONS))
                                    <option value="Nigerian Naira (NGN)" {{ request()->currency == 'Nigerian Naira (NGN)' ? 'selected' : '' }}>
                                        Nigerian Naira (NGN)
                                    </option>
                                @endif
                                @foreach (\App\Constants\Finance\Currency\CurrencyConstants::CURRENCY_OPTIONS as $currency)
                                    <option value="{{ $currency }}" {{ request()->currency == $currency ? 'selected' : '' }}>
                                        {{ $currency }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-sm btn-success p-2">Filter</button>
                        </div>
                    </form>

                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">S/N</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Duration (Days)</th>
                                    <th scope="col">Cost</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date Created</th>
                                    <th scope="col">Expiry Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($promotions as $promotion)
                                    <tr>
                                        <td>{{ $sn++ }}</td>
                                        <td>{{ $promotion->user->getName() }}</td>
                                        <td>{{ $promotion->duration }}</td>
                                        <td>{{ format_money($promotion->cost, 2, $promotion->currency?->symbol ?? ($promotion->currency?->short_name ?? ($promotion->payment?->currencyModel?->symbol ?? "$"))) }}
                                        </td>
                                        <td>{{ $promotion->type() }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($promotion->status) }}-transparent">
                                                {{ $promotion->status }}
                                            </span>
                                        </td>
                                        <td>{{ $promotion->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>{{ $promotion->expires_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="hstack gap-2 fs-15">
                                                <a aria-label="anchor" data-bs-toggle="tooltip"
                                                    title="View Promoted Content" target="_blank"
                                                    href="{{ $promotion->contentWebUrl() }}"
                                                    class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-success-light"><i
                                                        class="ri-external-link-line"></i></a>
                                                <form action="{{ route('admin.promotions.update', $promotion->id) }}"
                                                    method="post" id="cancelPromotion_{{ $promotion->id }}"
                                                    onsubmit="return confirm('Are you sure of this action?')">
                                                    @csrf
                                                    <input type="hidden" name="status" value="Cancelled">
                                                    <button type="submit"
                                                        class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-danger-light"
                                                        data-bs-toggle="tooltip" title="Cancel Promoted Content"><i
                                                            class="ri-close-line"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
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
                        <div class="text-muted">
                            Showing {{ $promotions->firstItem() }} to {{ $promotions->lastItem() }} of
                            {{ $promotions->total() }} entries
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                {{ $promotions->links('pagination::bootstrap-4') }}
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const currencySelect = document.getElementById('currency-select');
            const form = document.getElementById('filter-form');
            currencySelect.addEventListener('change', () => {
                form.submit();
            });
            form.addEventListener('submit', (event) => {
                const submitter = event.submitter;
                if (!submitter || !submitter.classList.contains('btn-success')) {
                    event.preventDefault();
                    console.log("Submission prevented: Not the filter button.");
                }
            });
        });
    </script>
@endsection
