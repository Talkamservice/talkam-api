@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Report Information</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.reports.group.lists') }}">Index</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Report Information</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->

        <div class="row">
            <div class="col-xxl-4 col-xl-4">
                <div class="card custom-card overflow-hidden">
                    <div class="card-body p-0">
                        <div class="d-sm-flex align-items-top p-4 border-bottom-0 main-profile-cover">
                            <div>
                                <span class="avatar avatar-xxl avatar-rounded">
                                    <!-- Check if image exists, otherwise use a default image -->
                                    <img src="{{ $group_member_report->groupMember->user->avatarUrl() }}"
                                        alt="Group Member Image">
                                </span>
                            </div>
                            <div class="flex-fill main-profile-info">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="fw-semibold mb-1 text-fixed-white">
                                        {{ $group_member_report->groupMember?->user?->full_name }}</h6>
                                    <button
                                        class="btn bg-white btn-outline-{{ pillClasses($group_member_report->groupMember?->status) }} btn-sm btn-wave">
                                        {{ $group_member_report->groupMember?->status }}
                                    </button>
                                </div>
                                <div class="d-flex mb-0">
                                    <div class="me-4">
                                        <p class="fw-bold fs-23 text-fixed-white text-shadow mb-0">{{ $reasons_count }}</p>
                                        <p class="mb-0 fs-14 text-fixed-white">Reports</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 border-bottom border-block-end-dashed">
                            <p class="fs-15 mb-2 me-4 fw-semibold">Group Member Information :</p>
                            <div class="text-muted">
                                <p class="mb-2"><b>Username:</b>
                                    {{ $group_member_report->groupMember->user->full_name ?? 'N/A' }}</p>
                                <p class="mb-2"><b>Email:</b>
                                    {{ $group_member_report->groupMember->user->email ?? 'N/A' }}</p>
                                <p class="mb-2"><b>Status:</b> {{ $group_member_report->groupMember->status ?? 'N/A' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-8 col-xl-8">
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
                        <div class="dropdown ms-auto me-auto">
                            <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Action
                            </a>
                            <ul class="dropdown-menu">
                               
                                @if ($group_member_report->user->banned)
                                    <li>
                                        <a class="dropdown-item text-danger" href="#">
                                            <i class="ri-error-warning-line"></i> User Banned
                                        </a>
                                    </li>
                                @elseif ($group_member_report->groupMember->suspension_end && $group_member_report->groupMember->suspension_end > now())
                                    <li>
                                        <form id="undoSuspensionForm_{{ $group_member_report->groupMember->id }}"
                                            action="{{ route('admin.reports.group-member.undo-suspension', $group_member_report->groupMember->id) }}"
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to lift this suspension?')">
                                            @csrf
                                            <a class="dropdown-item text-primary" href="#"
                                                onclick="document.getElementById('undoSuspensionForm_{{ $group_member_report->groupMember->id }}').submit()">
                                                <i class="ri-alert-line"></i> Undo Suspension
                                            </a>
                                        </form>
                                    </li>
                                @endif
                                <li>
                                    <a class="dropdown-item text-danger suspend-btn" href="#" onclick="openModal('{{ $group_member_report->id }}')">
                                        <i class="ri-alert-line"></i> Suspend/Ban
                                    </a>
                                </li>
                               
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
                                    @forelse ($group_member_report_lists as $group_member_report)
                                        <tr>
                                            <td>
                                                <a class="text-primary" href="{{ route('admin.users.show', $group_member_report->user_id) }}">
                                                    <div class="d-flex align-items-center fw-semibold">
                                                        <span class="avatar avatar-sm me-2 avatar-rounded">
                                                            <!-- Check if image exists, otherwise use a default image -->
                                                            <img src="{{ $group_member_report->user->avatarUrl() }}"
                                                                alt="Reporter Image">
                                                        </span>{{ $group_member_report->user->full_name ?? 'Unknown' }}
                                                    </div>
                                                </a>
                                            </td>
                                            <td>{{ $group_member_report->reason ?? 'No reason provided' }}</td>
                                            <td>{{ $group_member_report->created_at->format('Y-m-d h:i A') }}</td>
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
                                {{ $group_member_report_lists->links('pagination::bootstrap-4') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('dashboards.admin.pages.report.group.member.suspend-ban-modal')
@endsection
