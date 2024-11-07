@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Start::page-header -->

        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <p class="fw-semibold fs-18 mb-0">Ads Revenue Manaagement</p>
            </div>
            <form method="GET" action="{{ url()->current() }}" class="d-inline">
                <div class="dropdown">
                    <button type="button" class="btn btn-primary btn-sm btn-wave waves-effect waves-light" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ !empty(request()->period) ? 'Sort Stats By ' . ucfirst(request()->period) : 'Sort Stats By' }}<i class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i>
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
            <div class="col-xxl-9 col-xl-12">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="row">
                            @foreach ($promotion_stats['cards'] as $index => $card)
                                <div class="col-12 col-md-6 col-lg-6 col-xl-6 col-xxl-4" id="stats-content">
                                    <div class="card custom-card overflow-hidden">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <span class="avatar avatar-md avatar-rounded bg-{{ $card['class'] }}">
                                                        <i class="ti ti-{{ $card['icon'] ?? 'udrtd' }} fs-16"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-fill ms-3">
                                                    <p class="text-muted mb-0">{{ $card['title'] }}</p>
                                                    <h4 class="fw-semibold mt-1">{{ $card['value'] }}</h4>
                                                </div>
                                                <div id="crm-total-customers-{{ $index }}" class="chart"></div>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-0">
                                                <div>
                                                    @if (!empty($card['url']))
                                                        <a class="text-{{ $card['class'] }}" href="{{ $card['url'] }}">
                                                            View All
                                                            <i class="ti ti-arrow-narrow-right ms-2 fw-semibold d-inline-block"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                                <div class="text-end">
                                                    <p class="mb-0 text-{{ $card['percentage'] >= 0 ? 'success' : 'danger' }} fw-semibold">
                                                        {{ $card['percentage'] >= 0 ? '+' : '' }}{{ $card['percentage'] }}%
                                                    </p>
                                                    <span class="text-muted op-7 fs-11">this {{ $card['period'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            <div class="col-xl-12">
                                <div class="card custom-card">
                                    <div class="card-header justify-content-between">
                                        <div class="card-title">
                                            Revenue Analytics
                                        </div>
                                        <div class="dropdown">
                                            <a href="javascript:void(0);" class="p-2 fs-12 text-muted" data-bs-toggle="dropdown" aria-expanded="false">
                                                View All<i class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i>
                                            </a>
                                            <ul class="dropdown-menu" role="menu">
                                                <li><a class="dropdown-item" href="javascript:void(0);">Today</a></li>
                                                <li><a class="dropdown-item" href="javascript:void(0);">This Week</a></li>
                                                <li><a class="dropdown-item" href="javascript:void(0);">Last Week</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="content-wrapper">
                                            <div id="crm-revenue-analytics"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-12">
                        <div class="card custom-card">
                            <div class="card-header d-flex justify-content-between">
                                <form action="{{ url()->current() }}" method="get" class="d-flex justify-content-between">
                                    <div class="form-group me-2">
                                        <input class="form-control" type="text" placeholder="Search...." name="search" value="{{ request()->search }}">
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
                                                <th scope="col">Name</th>
                                                <th scope="col">Duration (Days)</th>
                                                <th scope="col">Cost</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Date</th>
                                                <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($promotions as $promotion)
                                                <tr>
                                                    <td>{{ $promotion->user->getName() }}</td>
                                                    <td>{{ $promotion->duration }}</td>
                                                    <td>{{ format_money($promotion->cost) }}</td>
                                                    <td>
                                                        <span class="badge bg-{{ pillClasses($promotion->status) }}-transparent">
                                                            {{ $promotion->status }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $promotion->created_at->format('Y-m-d h:i A') }}</td>
                                                    <td>
                                                        <div class="hstack gap-2 fs-15">
                                                            <a aria-label="anchor" data-bs-toggle="tooltip" title="View Promoted Content" target="_blank" href="{{ $promotion->contentWebUrl() }}" class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-success-light"><i
                                                                    class="ri-external-link-line"></i></a>
                                                            <form action="{{ route('admin.promotions.update', $promotion->id) }}" method="post" id="cancelPromotion_{{ $promotion->id }}" onsubmit="return confirm('Are you sure of this action?')"> @csrf
                                                                <input type="hidden" name="status" value="Cancelled">
                                                                <button type="submit" class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-danger-light" data-bs-toggle="tooltip" title="Cancel Promoted Content"><i class="ri-close-line"></i></button>
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
            </div>
            <div class="col-xxl-3 col-xl-12">
                <div class="row">
                    <div class="col-xxl-12 col-xl-12">
                        <div class="row">
                            <div class="col-xl-12 col-xl-6">
                                <div class="card custom-card">
                                    <div class="card-header  justify-content-between">
                                        <div class="card-title">
                                            Top Promotions
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled crm-top-deals mb-0">
                                            @foreach ($high_promotions as $high_promotion)
                                                <li>
                                                    <div class="d-flex align-items-top flex-wrap">
                                                        <div class="flex-fill">
                                                            <p class="fw-semibold mb-0">{{ $high_promotion->user->getName() }}</p>
                                                            <span class="text-muted fs-12">{{ $high_promotion->user->email }}</span>
                                                        </div>
                                                        <div class="fw-semibold fs-15">{{ format_money($high_promotion->cost) }}</div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-12 col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                                <div class="card custom-card">
                                    <div class="card-header justify-content-between">
                                        <div class="card-title">Profit Earned</div>
                                    </div>
                                    <div class="card-body py-0 ps-0">
                                        <div id="crm-profits-earned"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-12 col-xl-6">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    Deals Status
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <h4 class="fw-bold mb-0">{{ $promotion_stats["status_card"]["value"] ?? 0 }}</h4>
                                    <div class="ms-2">
                                        <span class="badge bg-success-transparent">{{ $promotion_stats["status_card"]["percentage"] ?? 0 }}%
                                            {{-- <i class="ri-arrow-down-s-fill align-mmiddle ms-1"></i> --}}
                                        </span>
                                        <span class="text-muted ms-1">compared to last {{ request()->period ?? "month" }}</span>
                                    </div>
                                </div>
                                <div class="progress-stacked progress-animate progress-xs mb-4">
                                    @foreach ($collection as $item)
                                        
                                    @endforeach
                                    <div class="progress-bar" role="progressbar" style="width: 21%" aria-valuenow="21" aria-valuemin="0" aria-valuemax="100"></div>
                                    <div class="progress-bar bg-info" role="progressbar" style="width: 26%" aria-valuenow="26" aria-valuemin="0" aria-valuemax="100"></div>
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 35%" aria-valuenow="35" aria-valuemin="0" aria-valuemax="100"></div>
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 18%" aria-valuenow="18" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <ul class="list-unstyled mb-0 pt-2 crm-deals-status">
                                    <li class="primary">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>Successful Deals</div>
                                            <div class="fs-12 text-muted">987 deals</div>
                                        </div>
                                    </li>
                                    <li class="info">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>Pending Deals</div>
                                            <div class="fs-12 text-muted">1,073 deals</div>
                                        </div>
                                    </li>
                                    <li class="warning">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>Rejected Deals</div>
                                            <div class="fs-12 text-muted">1,674 deals</div>
                                        </div>
                                    </li>
                                    <li class="success">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>Upcoming Deals</div>
                                            <div class="fs-12 text-muted">921 deals</div>
                                        </div>
                                    </li>
                                </ul>
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
    <script>
        // Inject PHP dashboard data into JavaScript
        const dashboardData = @json($dashboardData);

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

            // Define a mapping from card class names to actual colors (RGB values)
            const colorMap = {
                'primary': '132, 90, 223',
                'info': '23, 162, 184',
                'warning': '255, 193, 7',
                'danger': '220, 53, 69',
                'success': '40, 167, 69'
            };

            // Check if dashboardData exists and has necessary data
            if (typeof dashboardData === 'undefined') {
                console.error('dashboardData is not defined.');
                return;
            }

            // Function to initialize charts
            function initializeCharts() {
                @foreach ($cards as $index => $card)
                    // Initialize data as an empty array
                    var data = [];

                    // Use the card title to decide which data to use
                    if ("{{ $card['title'] }}" === "Total Post Ads") {
                        data = dashboardData.currentPostAds;
                    } else if ("{{ $card['title'] }}" === "Total Group Ads") {
                        data = dashboardData.currentGroupAds;
                    } else if ("{{ $card['title'] }}" === "Total Post Ad Revenue") {
                        data = dashboardData.currentPostAdRevenue;
                    } else if ("{{ $card['title'] }}" === "Total Group Ad Revenue") {
                        data = dashboardData.currentGroupAdRevenue;
                    } else if ("{{ $card['title'] }}" === "Total Freemium User") {
                        data = dashboardData.currentFreemiumUser;
                    } else if ("{{ $card['title'] }}" === "Total Premium User") {
                        data = dashboardData.currentPremiumUser;
                    }

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
                            colors: undefined, // Leave undefined to be dynamically updated
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
                            data: data // Pass the dynamic data here
                        }],
                        yaxis: {
                            min: minValue,
                            max: maxValue,
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
                        colors: ["rgb(132, 90, 223)"], // Default color, will be updated
                    }

                    // Render the chart
                    document.getElementById('crm-total-customers-{{ $index }}').innerHTML = '';
                    var crm1Chart = new ApexCharts(document.querySelector(
                        "#crm-total-customers-{{ $index }}"), crm1);
                    crm1Chart.render();

                    // Function to update the chart color dynamically after chart is rendered
                    function crmtotalCustomers(cardClass) {
                        // Retrieve the color value from the colorMap based on cardClass
                        const colorValue = colorMap[cardClass] || '132, 90, 223'; // Default color if no match
                        crm1Chart.updateOptions({
                            colors: ["rgb(" + colorValue + ")"],
                            stroke: {
                                colors: ["rgb(" + colorValue + ")"] // Dynamically set stroke color
                            }
                        });
                    }

                    // Call the function to update the chart color with the current card's class
                    crmtotalCustomers("{{ $card['class'] }}");
                @endforeach
            }

            // Call the initializeCharts function after the page has loaded
            initializeCharts();
        });
    </script>
@endsection
