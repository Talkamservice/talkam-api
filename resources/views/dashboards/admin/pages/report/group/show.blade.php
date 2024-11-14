@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Group Report Information</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.reports.group.lists') }}">Index</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Group Report Information</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->

        <div class="row">
            <div class="col-xxl-4 col-xl-4">
                <div class="col-xxl-12 col-xl-12">
                    <div class="card custom-card overflow-hidden">
                        <div class="card-body p-0">
                            <div class="d-sm-flex align-items-top p-4 border-bottom-0 main-profile-cover">
                                <div>
                                    <span class="avatar avatar-xxl avatar-rounded ">
                                        <img src="{{ $group_report->group->image }}" alt="">
                                    </span>
                                </div>
                                <div class="flex-fill main-profile-info" style="margin-left: 15px;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h6 class="fw-semibold mb-1 text-fixed-white">{{ $group_report->group?->name }}</h6>
                                        <button
                                            class="btn bg-white btn-outline-{{ pillClasses($group_report->group?->status) }} btn-sm btn-wave">
                                            {{ $group_report->group?->status }}
                                        </button>
                                    </div>
                                    <div class="d-flex mb-0">
                                        <div class="me-4">
                                            <p class="fw-bold fs-23 text-fixed-white text-shadow mb-0">{{ $reasons_count }}
                                            </p>
                                            <p class="mb-0 fs-14 text-fixed-white">Reports</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 border-bottom border-block-end-dashed">
                                <p class="fs-15 mb-2 me-4 fw-semibold">Group Information :</p>
                                <div class="text-muted">
                                    <p class="mb-2">
                                        <b>Title:</b> {{ $group_report->group->name ?? 'N/A' }}
                                    </p>
                                </div>
                                <div class="text-muted">
                                    <p class="mb-2">
                                        <b>Description:</b> {{ $group_report->group->decription ?? 'N/A' }}
                                    </p>
                                    <p class="mb-2">
                                        <b>About:</b> {{ $group_report->group->about ?? 'N/A' }}
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-8 col-xl-8">
                <div class="card custom-card">
                    <div class="card-header d-flex justify-content-between">

                        <div class="dropdown ms-auto me-auto">
                            <a class="btn btn-outline-primary dropdown-toggle" href="#" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false" data-bs-toggle="tooltip"
                                data-bs-placement="top" title="Show Actions">
                                Action
                            </a>
                            <ul class="dropdown-menu">
                               
                                @if ($group_report->group->status === 'Suspended')
                                    <!-- Undo Suspension Action -->
                                    <li>
                                        <a class="dropdown-item text-primary" href="#"
                                            onclick="openActionsModal('undo', '{{ route('admin.reports.group.activate', $group_report->id) }}')"
                                            data-bs-toggle="tooltip" title="Undo Suspension">
                                            <i class="ri-alert-line"></i> | Undo Suspension
                                        </a>
                                    </li>

                                    <!-- Delete Group Action -->
                                    {{-- <li>
                                        <a class="dropdown-item text-danger" href="#"
                                            onclick="openActionsModal('delete', '{{ route('admin.reports.group.delete', $group_report->group->id) }}')"
                                            data-bs-toggle="tooltip" title="Delete Group">
                                            <i class="ri-alert-line"></i> | Delete
                                        </a>
                                    </li> --}}
                                @else
                                    <li>
                                        <a class="dropdown-item text-danger" href="#"
                                            onclick="openModal('{{ $group_report->group->id }}')"
                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                            title="Suspend Group">
                                            <i class="ri-alert-line"></i> | Suspend
                                        </a>
                                    </li>
                                    <li>
                                        <!-- Delete Group Action -->
                                    {{-- <li>
                                        <a class="dropdown-item text-danger" href="#"
                                            onclick="openActionsModal('delete', '{{ route('admin.reports.group.delete', $group_report->group->id) }}')"
                                            data-bs-toggle="tooltip" title="Delete Group">
                                            <i class="ri-alert-line"></i> | Delete
                                        </a>
                                    </li> --}}
                                    </li>
                                @endif
                            </ul>
                        </div>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table text-nowrap table-hover border table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">Reporter</th>
                                        <th scope="col">Reason</th>
                                        <th scope="col">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($group_report_lists as $report)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.users.show', $report->user_id) }}">
                                                    <div class="d-flex align-items-center fw-semibold">
                                                        <span class="avatar avatar-sm me-2 avatar-rounded">
                                                            <img src="{{ $report->user->image }}" alt="img">
                                                        </span>{{ $report->user->username }}
                                                    </div>
                                                </a>
                                            </td>
                                            <td>{{ $report->reason }}</td>
                                            <td>{{ $report->created_at->format('Y-m-d h:i A') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center"><img class="no-data-image"
                                                    src="{{ asset('admin_assets/images/empty/no-data-concept-illustration.jpg') }}"
                                                    alt=""></td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex align-items-center">
                            <div>
                                {{ $group_report_lists->links('pagination::bootstrap-4') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('dashboards.admin.pages.report.group.suspend-ban-modal')
    @include('dashboards.admin.pages.report.group.actions-modal');

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
    </script>
@endsection
