@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- ApexCharts -->
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

        <!-- Start::page-header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <p class="fw-semibold fs-18 mb-0">Welcome back, {{ auth()->user()?->name }}</p>
            </div>
            <form method="GET" action="{{ route('admin.home') }}" class="d-inline">
                <div class="dropdown">
                    <button type="button" class="btn btn-primary btn-sm btn-wave waves-effect waves-light"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Sort By<i class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i>
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
        <div class="row" id="dashboard-content">
            <div class="col-xxl-12 col-xl-12">
                <div class="row">
                    @foreach ($cards as $index => $card)
                        <div class="col-12 col-md-6 col-lg-6 col-xl-6 col-xxl-3" id="stats-content">
                            <div class="card custom-card overflow-hidden">
                                <div class="card-body">
                                    <div class="d-flex align-items-top justify-content-between">
                                        <div>
                                            <span class="avatar avatar-md avatar-rounded bg-{{ $card['class'] }}">
                                                <i class="ti ti-{{ $card['icon'] ?? 'udrtd' }} fs-16"></i>
                                            </span>
                                        </div>
                                        <div class="flex-fill ms-3">
                                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                                <div>
                                                    <p class="text-muted mb-0">{{ $card['title'] }}</p>
                                                    <h4 class="fw-semibold mt-1">{{ $card['value'] }}</h4>
                                                </div>
                                                <div id="crm-total-customers-{{ $index }}" class="chart"></div>
                                                <!-- Unique ID -->
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-1">
                                                <div>
                                                    <a class="text-{{ $card['class'] }}" href="{{ $card['url'] }}">
                                                        View All
                                                        <i
                                                            class="ti ti-arrow-narrow-right ms-2 fw-semibold d-inline-block"></i>
                                                    </a>
                                                </div>
                                                <div class="text-end">
                                                    <p
                                                        class="mb-0 text-{{ $card['percentage'] >= 0 ? 'success' : 'danger' }} fw-semibold">
                                                        {{ $card['percentage'] >= 0 ? '+' : '' }}{{ $card['percentage'] }}%
                                                    </p>
                                                    <span class="text-muted op-7 fs-11">this month</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                </div>
                <!-- Other Content Sections -->
                <div class="row">
                    <div class="col-12 col-md-7 col-lg-7 col-xl-7 col-xxl-7">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">Latest Users</div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table text-nowrap table-hover border table-bordered">
                                        <thead>
                                            <tr>
                                                <th scope="col">Name</th>
                                                <th scope="col">Email</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Date</th>
                                                <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($users as $user)
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center fw-semibold">
                                                            <span class="avatar avatar-sm me-2 avatar-rounded">
                                                                <img src="{{ $user->avatarUrl() }}" alt="img">
                                                            </span>{{ $user->name }}
                                                        </div>
                                                    </td>
                                                    <td>{{ $user->email }}</td>
                                                    <td>
                                                        <span
                                                            class="badge bg-{{ pillClasses($user->status) }}-transparent">
                                                            {{ $user->status }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $user->created_at->format('Y-m-d h:i A') }}</td>
                                                    <td>
                                                        <div class="hstack gap-2 fs-15">
                                                            <a aria-label="anchor"
                                                                href="{{ route('admin.users.show', $user->id) }}"
                                                                class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-success-light">
                                                                <i class="ri-eye-line"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                @include('general.components.no_item', ['items' => []])
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="text-muted">
                                        Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of
                                        {{ $users->total() }} entries
                                    </div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination">
                                            {{ $users->links('pagination::bootstrap-4') }}
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-5 col-lg-5 col-xl-5 col-xxl-5">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">Recent Activity</div>
                                <div class="dropdown">
                                    <a href="{{ route('admin.activity-logs.index') }}" class="p-2 fs-12 text-muted"
                                        aria-expanded="false">
                                        @if ($activity_logs->count() >= 5)
                                            View All
                                        @endif
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div>
                                    <ul class="list-unstyled mb-0 crm-recent-activity">
                                        @forelse ($activity_logs as $log)
                                            <li class="crm-recent-activity-content">
                                                <div class="d-flex align-items-top">
                                                    <div class="me-3">
                                                        <span
                                                            class="avatar avatar-xs bg-primary-transparent avatar-rounded">
                                                            <i class="bi bi-circle-fill fs-8"></i>
                                                        </span>
                                                    </div>
                                                    <div class="crm-timeline-content">
                                                        <span class="fw-semibold">
                                                            {{ ucfirst($log->description) }}
                                                        </span>
                                                    </div>
                                                    <div class="flex-fill text-end">
                                                        <span
                                                            class="d-block text-muted fs-11 op-7">{{ $log->created_at->diffForHumans() }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </li>
                                        @empty
                                            @include('general.components.no_item', ['items' => []])
                                        @endforelse
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('DOMContentLoaded', function() {
                const dropdownItems = document.querySelectorAll('.dropdown-menu .dropdown-item');
                dropdownItems.forEach(item => {
                    item.addEventListener('click', function() {
                        const period = this.getAttribute('data-period');
                        alert(period);
                        const form = this.closest('form');
                        form.querySelector('#selected-period').value = period;
                        alert(form);
                        form.submit(); // Submit the form automatically
                    });
                });
            });



            // Initialize charts
            function initializeCharts() {
                @foreach ($cards as $index => $card)
                    // Retrieve the data for the current card
                    var data = @json($card['value']);
                    console.log(data); // Add this before initializing the chart

                    // Ensure data is an array
                    if (!Array.isArray(data)) {
                        console.error('Data for chart is not an array:', data);
                        data = []; // Default to an empty array if data is not valid
                    }

                    // Calculate dynamic y-axis min and max if data is available
                    var minValue = data.length ? Math.min(...data) - 10 : 0;
                    var maxValue = data.length ? Math.max(...data) + 10 : 0;

                    // Chart options for the current card
                    var chartOptions = {
                        chart: {
                            type: 'line',
                            height: 40,
                            width: 50,
                            sparkline: {
                                enabled: true
                            }
                        },
                        series: [{
                            name: '{{ $card['title'] }}',
                            data: data
                        }],
                        stroke: {
                            curve: 'smooth',
                            width: 2,
                            height: 20
                        },
                        xaxis: {
                            crosshairs: {
                                show: false
                            },
                            tooltip: {
                                enabled: false
                            }
                        },
                        yaxis: {
                            min: minValue,
                            max: maxValue,
                            labels: {
                                show: false
                            }
                        },
                        colors: ['#{{ $card['class'] }}']
                    };

                    // Render the chart
                    new ApexCharts(document.querySelector('#crm-stats-{{ $index }}'),
                            chartOptions)
                        .render();
                @endforeach
            }
            initializeCharts(); // Call the function to render charts initially
        });

        /* Total Customers chart */
        @foreach ($cards as $index => $card)
            // Retrieve the data for the current card
            var data = @json($card['value']);
            console.log(data); // Add this before initializing the chart

            // Ensure data is an array
            if (!Array.isArray(data)) {
                console.error('Data for chart is not an array:', data);
                data = []; // Default to an empty array if data is not valid
            }

            // Calculate dynamic y-axis min and max if data is available
            var minValue = data.length ? Math.min(...data) - 10 : 0;
            var maxValue = data.length ? Math.max(...data) + 10 : 0;
            var crm1 = {
                chart: {
                    type: 'line',
                    height: 40,
                    width: 100,
                    sparkline: {
                        enabled: true
                    }
                },
                stroke: {
                    show: true,
                    curve: 'smooth',
                    lineCap: 'butt',
                    colors: undefined,
                    width: 1.5,
                    dashArray: 0,
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        opacityFrom: 0.9,
                        opacityTo: 0.9,
                        stops: [0, 98],
                    }
                },
                series: [{
                    name: '{{ $card['title'] }}',
                    data: [20, 14, 19, 10, 23, 20, 22, 9, 12]
                }],
                yaxis: {
                    min: 0,
                    show: false,
                    axisBorder: {
                        show: false
                    },
                },
                xaxis: {
                    show: false,
                    axisBorder: {
                        show: false
                    },
                },
                tooltip: {
                    enabled: false,
                },
                colors: ["rgb(132, 90, 223)"],
            }
            document.getElementById('crm-total-customers-{{ $index }}').innerHTML = '';
            var crm1 = new ApexCharts(document.querySelector("#crm-total-customers-{{ $index }}"),
                crm1);
            crm1.render();

            function crmtotalCustomers() {
                crm1.updateOptions({
                    colors: ["rgb(" + myVarVal + ")"],
                });
            }
            /* Total Customers chart */

            function leads(myVarVal) {

                chartInstance.data.datasets[0] = {
                    label: 'My First Dataset',
                    data: [32, 27, 25, 16],
                    backgroundColor: [
                        `rgb(${myVarVal})`,
                        'rgb(35, 183, 229)',
                        'rgb(245, 184, 73)',
                        'rgb(38, 191, 148)',
                    ]
                }
                chartInstance.update();

            }
        @endforeach
    </script>
@endsection
