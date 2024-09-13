@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Reported Groups</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Reported Groups Section -->
        <div class="col-xl-12 mb-4">
            <div class="card custom-card">
                <div class="card-header">
                    <form action="{{ url()->current() }}" method="get" class="row g-3">
                        <div class="col-12 col-md-5 col-xl-5 col-lg-5">
                            <div class="form-group">
                                <label for="search">Search (name,status)</label>
                                <input class="form-control" type="text" value="{{ request()->search }}"
                                    placeholder="Search..." name="search">
                            </div>
                        </div>

                        <div class="col-12 col-md-5 col-xl-5 col-lg-5">
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input class="form-control" type="date" value="{{ request()->date }}" name="date">
                            </div>
                        </div>

                        <div class="col-12 col-md-2 col-xl-2 col-lg-2 pt-4">
                            <div class="form-group d-flex align-items-end">
                                <button class="btn btn-sm btn-success w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card-body">
                    <div class="table-responsive" style="min-height: 250px">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Group Name</th>
                                    <th scope="col">Group Description</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($group_report_lists->groupBy('group_id') as $group_id => $group_reports)
                                    @php
                                        $group_report = $group_reports->first();
                                    @endphp
                                    <tr>
                                        <td>
                                            <a class="text-primary"
                                                href="{{ url('https://web.talkam.prodevs.io/group/' . $group_report->group->id . '/featured') }}"
                                                target="_blank"
                                                rel="noopener noreferrer">{{ $group_report->group->name }}</a>
                                        </td>
                                        <td>{{ $group_report->group->description }}</td>
                                        <td>
                                            <span
                                                class="badge bg-{{ pillClasses($group_report->group->status) }}-transparent">
                                                {{ $group_report->group->status }}
                                            </span>
                                        </td>
                                        <td>{{ $group_report->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                    role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Show Actions">
                                                    Action
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item"
                                                            href="{{ route('admin.reports.group.show', $group_report->id) }}"
                                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                                            title="View Report">
                                                            <i class="ri-eye-line"></i> View
                                                        </a>
                                                    </li>
                                                    @if ($group_report->group->status === 'Suspended')
                                                        <!-- Undo Suspension Form -->
                                                        <form id="undoSuspensionForm_{{ $group_report->id }}"
                                                            action="{{ route('admin.reports.group.activate', $group_report->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Are you sure you want to lift this suspension?')">
                                                            @csrf
                                                            <a class="dropdown-item text-primary" href="#"
                                                                onclick="document.getElementById('undoSuspensionForm_{{ $group_report->id }}').submit()"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="Undo Suspension">
                                                                <i class="ri-alert-line"></i> Undo Suspension
                                                            </a>
                                                        </form>

                                                        <!-- Delete Group Form -->
                                                        <form id="deleteGroupForm_{{ $group_report->id }}"
                                                            action="{{ route('admin.reports.group.delete', $group_report->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Are you sure you want to permanently delete this group?')">
                                                            @csrf
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="document.getElementById('deleteGroupForm_{{ $group_report->id }}').submit()"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="Delete Group">
                                                                <i class="ri-alert-line"></i> Delete
                                                            </a>
                                                        </form>

                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="openModal('{{ $group_report->group->id }}')"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="Suspend Group">
                                                                <i class="ri-alert-line"></i> Suspend
                                                            </a>
                                                        </li>
                                                    @else
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="openModal('{{ $group_report->group->id }}')"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="Suspend Group">
                                                                <i class="ri-alert-line"></i> Suspend
                                                            </a>
                                                        </li>
                                                        <!-- Delete Group Form -->
                                                        <form id="deleteGroupForm_{{ $group_report->id }}"
                                                            action="{{ route('admin.reports.group.delete', $group_report->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Are you sure you want to permanently delete this group?')">
                                                            @csrf
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="document.getElementById('deleteGroupForm_{{ $group_report->id }}').submit()"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="Delete Group">
                                                                <i class="ri-alert-line"></i> Delete
                                                            </a>
                                                        </form>
                                                    @endif
                                                </ul>
                                            </div>
                                        </td>

                                    </tr>
                                @empty
                                    <div class="alert alert-info text-center">
                                        No record found
                                    </div>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                {{-- <div class="card-footer">
                <div class="d-flex align-items-center">
                    <div>
                        {{ $group_report_lists->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </div> --}}
            </div>
        </div>
    </div>

    @include('dashboards.admin.pages.report.group.suspend-ban-modal')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
    </script>
@endsection
