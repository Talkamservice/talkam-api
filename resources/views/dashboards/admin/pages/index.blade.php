@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Start::page-header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <p class="fw-semibold fs-18 mb-0">Welcome back, {{ auth()->user()->name }}</p>
            </div>
            {{-- <div class="btn-list mt-md-0 mt-2">
                <button type="button" class="btn btn-primary btn-wave">
                    <i class="ri-filter-3-fill me-2 align-middle d-inline-block"></i>Filters
                </button>
                <button type="button" class="btn btn-outline-secondary btn-wave">
                    <i class="ri-upload-cloud-line me-2 align-middle d-inline-block"></i>Export
                </button>
            </div> --}}
        </div>

        <!-- End::page-header -->


        <!-- Start::row-1 -->
        <div class="row">
            <div class="col-xxl-12 col-xl-12">
                <div class="row">
                    @foreach ($cards as $card)
                        <div class="col-xxl-3 col-lg-3 col-md-6">
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
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-1">
                                                <div>
                                                    <a class="text-{{ $card['class'] }}" href="{{ $card['url'] }}">
                                                        View All
                                                        <i class="ti ti-arrow-narrow-right ms-2 fw-semibold d-inline-block"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                </div>
                <div class="col-xl-12">
                    <div class="card custom-card">
                        <div class="card-header justify-content-between">
                            <div class="card-title">
                                Latest Users
                            </div>
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
                                                    <span class="badge bg-{{ pillClasses($user->status) }}-transparent">
                                                        {{ $user->status }}
                                                    </span>
                                                </td>
                                                <td>{{ $user->created_at->format('Y-m-d h:i A') }}</td>
                                                <td>
                                                    <div class="hstack gap-2 fs-15">
                                                        <a aria-label="anchor" href="{{ route('admin.users.show', $user->id) }}" class="btn btn-icon btn-wave waves-effect waves-light btn-sm btn-success-light"><i class="ri-eye-line"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            @include('general.components.no_item', ['items' => []])
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{-- <div class="col-xxl-3 col-xl-12">
                <div class="row">
                    <div class="col-xxl-12 col-xl-12">
                        <div class="row">
                            <div class="col-xl-12 col-xl-6">
                                <div class="card custom-card">
                                    <div class="card-header justify-content-between">
                                        <div class="card-title">
                                            Therapists by Status
                                        </div>
                                    </div>
                                    <div class="card-body p-0 overflow-hidden">
                                        <div class="leads-source-chart d-flex align-items-center justify-content-center">
                                            <canvas id="leads-source" class="chartjs-chart w-100 p-4"></canvas>
                                            <div class="lead-source-value">
                                                <span class="d-block fs-14">Total</span>
                                                <span class="d-block fs-25 fw-bold">{{ formatNumber($total_therapists ?? 0) }}</span>
                                            </div>
                                        </div>
                                        <div class="row row-cols-12 border-top border-block-start-dashed">
                                            <div class="col p-0">
                                                <div class="ps-4 py-3 pe-3 text-center border-end border-inline-end-dashed">
                                                    <span class="text-muted fs-12 mb-1 crm-lead-legend mobile d-inline-block">Pending
                                                    </span>
                                                    <div><span class="fs-16 fw-semibold">{{ formatNumber($chart['pending'] ?? 0) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col p-0">
                                                <div class="p-3 text-center border-end border-inline-end-dashed">
                                                    <span class="text-muted fs-12 mb-1 crm-lead-legend desktop d-inline-block">Approved
                                                    </span>
                                                    <div><span class="fs-16 fw-semibold">{{ formatNumber($chart['approved'] ?? 0) }}</span></div>
                                                </div>
                                            </div>
                                            <div class="col p-0">
                                                <div class="p-3 text-center border-end border-inline-end-dashed">
                                                    <span class="text-muted fs-12 mb-1 crm-lead-legend laptop d-inline-block">Declined
                                                    </span>
                                                    <div><span class="fs-16 fw-semibold">{{ formatNumber($chart['declined'] ?? 0) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> --}}
        </div>
        <!-- End::row-1 -->
        {{-- <input type="hidden" id="therapist_chart_data" value="{{ json_encode($chart_data) }}"> --}}
    </div>
@endsection

{{-- @section('script')
    <script>
        let chart_data = $.parseJSON($("#therapist_chart_data").val());

        var chartInstance = new Chart(document.getElementById("leads-source"), {
            type: "doughnut",
            data: {
                datasets: [{
                    label: "Therapist chart",
                    data: chart_data,
                    // data: [32, 27, 25, 16],
                    backgroundColor: [
                        "rgb(3,101,161)",
                        "rgb(35, 183, 229)",
                        "rgb(245, 184, 73)",
                    ],
                }, ],
            },
            plugins: [{
                afterUpdate: function(chart) {
                    const arcs = chart.getDatasetMeta(0).data;

                    arcs.forEach(function(arc) {
                        arc.round = {
                            x: (chart.chartArea.left + chart.chartArea.right) / 2,
                            y: (chart.chartArea.top + chart.chartArea.bottom) / 2,
                            radius: (arc.outerRadius + arc.innerRadius) / 2,
                            thickness: (arc.outerRadius - arc.innerRadius) / 2,
                            backgroundColor: arc.options.backgroundColor,
                        };
                    });
                },
                afterDraw: (chart) => {
                    const {
                        ctx,
                        canvas
                    } = chart;

                    chart.getDatasetMeta(0).data.forEach((arc) => {
                        const startAngle = Math.PI / 2 - arc.startAngle;
                        const endAngle = Math.PI / 2 - arc.endAngle;

                        ctx.save();
                        ctx.translate(arc.round.x, arc.round.y);
                        ctx.fillStyle = arc.options.backgroundColor;
                        ctx.beginPath();
                        ctx.arc(
                            arc.round.radius * Math.sin(endAngle),
                            arc.round.radius * Math.cos(endAngle),
                            arc.round.thickness,
                            0,
                            2 * Math.PI
                        );
                        ctx.closePath();
                        ctx.fill();
                        ctx.restore();
                    });
                },
            }, ],
        });
    </script>
@endsection --}}
