@extends('dashboards.admin.layout.app')
@section('content')
    <style>
        .disabled-link {
            pointer-events: none;
            color: #ccc;
            /* Change the color to indicate it's disabled */
            text-decoration: none;
            cursor: not-allowed;
            /* This will show a "not-allowed" cursor */
        }
    </style>
    <div class="container-fluid">

        <!-- Start::page-header -->

        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <p class="fw-semibold fs-18 mb-0">Ads Revenue Management</p>
            </div>
            <form method="GET" action="{{ url()->current() }}" class="d-inline">
                <div class="dropdown d-inline">
                    <!-- Period Selection -->
                    <button type="button" class="btn btn-primary btn-sm btn-wave waves-effect waves-light"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Sort Stats By {{ ucfirst(request()->period ?? 'month') }}<i
                            class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i>
                    </button>
                    <ul class="dropdown-menu" role="menu">
                        <li><a class="dropdown-item" href="javascript:void(0);" data-period="day">Day</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);" data-period="week">Week</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);" data-period="month">Month</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);" data-period="year">Year</a></li>
                    </ul>
                </div>

                <div class="dropdown d-inline">
                    <!-- Currency Selection -->
                    <button type="button" class="btn btn-primary btn-sm btn-wave waves-effect waves-light"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Currency: {{ request()->currency ?? 'Nigerian Naira (NGN)' }}<i
                            class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i>
                    </button>
                    <ul class="dropdown-menu scrollable-dropdown" role="menu">
                        @foreach (\App\Constants\Finance\Currency\CurrencyConstants::CURRENCY_OPTIONS as $currency)
                            <li>
                                <a class="dropdown-item " href="javascript:void(0);" data-currency="{{ $currency }}"
                                    data-currency-short-name="{{ \App\Constants\Finance\Currency\CurrencyConstants::CURRENCY_NAME_TO_CODE[$currency] ?? '' }}"
                                    data-currency-symbol="{{ \App\Constants\Finance\Currency\CurrencyConstants::CURRENCY_NAME_TO_SYMBOL[$currency] ?? '' }}">
                                    {{ $currency }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <input type="hidden" name="period" id="selected-period" value="{{ request()->period ?? 'month' }}">
                <input type="hidden" name="currency" id="selected-currency" value="{{ request()->currency ?? '' }}">
                <input type="hidden" name="currency_short_name" id="selected-currency-short-name"
                    value="{{ request()->currency_short_name ?? '' }}">
            </form>

        </div>

        <!-- End::page-header -->

        <!-- Start::row-1 -->
        <div class="row">
            <div class="col-xxl-9 col-xl-9 md-9 sm-12">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="row">
                            @foreach ($promotion_stats['cards'] as $index => $card)
                                <div class="col-m-12 col-md-6 col-lg-6 col-xl-6" id="stats-content">
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
                                                            <i
                                                                class="ti ti-arrow-narrow-right ms-2 fw-semibold d-inline-block"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                                <div class="text-end">
                                                    <p
                                                        class="mb-0 text-{{ $card['percentage'] >= 0 ? 'success' : 'danger' }} fw-semibold">
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
                                        {{-- <div class="dropdown">
                                            <a href="javascript:void(0);" class="p-2 fs-12 text-muted"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                View All<i
                                                    class="ri-arrow-down-s-line align-middle ms-1 d-inline-block"></i>
                                            </a>
                                            <ul class="dropdown-menu" role="menu">
                                                <li><a class="dropdown-item" href="javascript:void(0);">Today</a></li>
                                                <li><a class="dropdown-item" href="javascript:void(0);">This Week</a></li>
                                                <li><a class="dropdown-item" href="javascript:void(0);">Last Week</a></li>
                                            </ul>
                                        </div> --}}
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
                </div>
            </div>
            <div class="col-xxl-3 col-xl-3 md-3 sm-12">
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
                                                            <p class="fw-semibold mb-0">
                                                                {{ $high_promotion->user->getName() }}</p>
                                                            <span
                                                                class="text-muted fs-12">{{ $high_promotion->user->email }}</span>
                                                        </div>
                                                        <div class="fw-semibold fs-15">
                                                            {{ format_stat_money($high_promotion->cost, 2) }}</div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="col-xxl-12 col-xl-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    Promotions
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <h4 class="fw-bold mb-0">{{ $promotion_stats['status_card']['value'] ?? 0 }}</h4>
                                    <div class="ms-2">
                                        <span
                                            class="badge bg-success-transparent">{{ $promotion_stats['status_card']['percentage'] ?? 0 }}%
                                            {{-- <i class="ri-arrow-down-s-fill align-mmiddle ms-1"></i> --}}
                                        </span>
                                        <span class="text-muted ms-1">compared to last
                                            {{ request()->period ?? 'month' }}</span>
                                    </div>
                                </div>
                                <div class="progress-stacked progress-animate progress-xs mb-4">
                                    @foreach ($promotion_stats['status_card']['cards'] ?? [] as $promotion_stat)
                                        <div class="progress-bar bg-{{ $promotion_stat['class'] }}" role="progressbar"
                                            style="width: {{ $promotion_stat['value'] }}%"
                                            aria-valuenow="{{ $promotion_stat['value'] }}" aria-valuemin="0"
                                            aria-valuemax="100"></div>
                                    @endforeach
                                </div>
                                <ul class="list-unstyled mb-0 pt-2 crm-deals-status">
                                    @foreach ($promotion_stats['status_card']['cards'] ?? [] as $promotion_stat)
                                        <li class="{{ $promotion_stat['class'] }}">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <p>
                                                    <a href="{{ $promotion_stat['value'] == 0 ? 'javascript:void(0);' : route('admin.promotions.get-by-status', $promotion_stat['status']) }}"
                                                        target="{{ $promotion_stat['value'] == 0 ? '' : '_blank' }}"
                                                        rel="noopener noreferrer"
                                                        class="{{ $promotion_stat['value'] == 0 ? 'disabled-link' : '' }}">
                                                        {{ $promotion_stat['title'] }}
                                                    </a>
                                                </p>
                                                <p>
                                                    <a href="{{ $promotion_stat['value'] == 0 ? 'javascript:void(0);' : route('admin.promotions.get-by-status', $promotion_stat['status']) }}"
                                                        target="{{ $promotion_stat['value'] == 0 ? '' : '_blank' }}"
                                                        rel="noopener noreferrer"
                                                        class="{{ $promotion_stat['value'] == 0 ? 'disabled-link' : '' }}">
                                                        {{ $promotion_stat['value'] }}
                                                    </a>
                                                </p>
                                            </div>
                                        </li>
                                    @endforeach
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
    @include('dashboards.admin.pages.chart.revenue', ['revenue_data' => $revenue_data])
    <script>
        // Inject PHP dashboard data into JavaScript
        const dashboardData = @json($dashboardData);

        document.addEventListener('DOMContentLoaded', function() {
            // Handle dropdown item click
            document.querySelectorAll('.dropdown-menu .dropdown-item').forEach(item => {
                item.addEventListener('click', function() {
                    const period = this.getAttribute('data-period');
                    const currency = this.getAttribute('data-currency');
                    const currencyShortName = this.getAttribute('data-currency-short-name');  // Get short name (e.g., 'NGN')
                    const form = this.closest('form');
                    console.log('Currency Short Name:', currencyShortName);

                    if (period) {
                        form.querySelector('#selected-period').value = period;
                    }
                    if (currency) {
                        form.querySelector('#selected-currency').value = currency;
                    }
                    if (currencyShortName) {
                        form.querySelector('#selected-currency-short-name').value =
                            currencyShortName;
                    }

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
