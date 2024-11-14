@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Start::page-header -->

        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <p class="fw-semibold fs-18 mb-0">Promotion </p>
            </div>
            <form method="GET" action="{{ url()->current() }}" class="d-inline">
                <div class="dropdown">
                    <button type="button" class="btn btn-primary btn-sm btn-wave waves-effect waves-light"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        {{ !empty(request()->period) ? 'Sort Stats By ' . ucfirst(request()->period) : 'Sort Stats By' }}<i
                            class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i>
                    </button>
                    <ul class="dropdown-menu" role="menu">
                        <li><a class="dropdown-item" href="javascript:void(0);" data-period="day">Day</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);" data-period="week">Week</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);" data-period="month">Month</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);" data-period="year">Year</a></li>
                    </ul>
                </div>

                <!-- Hidden input to capture selected period -->
                <input type="hidden" name="period" id="selected-period" value="{{ request('period', 'day') }}">
            </form>

        </div>

        <!-- End::page-header -->

        <!-- Start::row-1 -->
        <div class="row">
            <div class="col-xxl-12 col-xl-12">
                <div class="row">

                    <div class="col-xl-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    Promotion Report
                                </div>
                                <div>
                                    <button type="button" class="btn btn-primary-light btn-wave"><i
                                            class="ri-share-forward-line me-1 align-middle d-inline-block"></i>Export</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="audienceReport"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <!-- End::row-1 -->
    </div>
@endsection


@section('script')
    @include('dashboards.admin.pages.chart.single-promotion', [
        'promotion_Data' => $promotion_data,
        'data_labels' => $data_labels,
        'period' => ucfirst(request()->period),
        'promotion' => $promotion,
    ])

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle dropdown item click
            document.querySelectorAll('.dropdown-menu .dropdown-item').forEach(item => {
                item.addEventListener('click', function() {
                    const period = this.getAttribute('data-period');
                    const form = this.closest('form');
                    form.querySelector('#selected-period').value = period;
                    form.submit(); // Submit the form automatically
                });
            });
        });
    </script>
@endsection
