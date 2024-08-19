@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Group Report</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Group Report</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
            </div>
        </div>
<<<<<<< HEAD
        <!-- Page Header Close -->

        <!-- Reported Groups Section -->
        <div class="col-xl-12 mb-4">
            <div class="card custom-card">
                <div class="card-body">
                    <h5 class="card-title">Reported Groups</h5>
                    <div class="table-responsive" style="min-height: 250px">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Group Name</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($group_report_lists->groupBy('group_id') as $group_id => $group_reports)
                                    @php
                                        $first_report = $group_reports->first();
                                    @endphp
                                    @if ($first_report && $first_report->group)
                                        <tr>
                                            <td>
                                                <a href="{{ url('https://web.talkam.prodevs.io/group/' . $first_report->group->id . '/featured') }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer">{{ $first_report->group->name }}</a>
                                            </td>
                                            <td>{{ $first_report->group->description }}</td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ pillClasses($first_report->group->status) }}-transparent">
                                                    {{ $first_report->group->status }}
                                                </span>
                                            </td>
                                            <td>{{ $first_report->created_at->format('Y-m-d h:i A') }}</td>
                                            <td>
                                                <div class="dropdown">
                                                    <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Action
                                                    </a>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item"
                                                                href="{{ route('admin.reports.group.show', $first_report->id) }}">
                                                                <i class="ri-eye-line"></i> View
                                                            </a>
                                                        </li>
                                                        <li>
                                                            @if ($first_report->group->status === 'Suspended')
                                                                <form id="activateGroup_{{ $first_report->group->id }}"
                                                                    action="{{ route('admin.reports.group.activate', $first_report->group->id) }}"
                                                                    method="POST"
                                                                    onsubmit="return confirm('Are you sure you want to lift the suspension for this group?')">
                                                                    @csrf
                                                                    @method('post')
                                                                    <a class="dropdown-item text-success" href="#"
                                                                        onclick="document.getElementById('activateGroup_{{ $first_report->group->id }}').submit()">
                                                                        <i class="ri-check-line"></i> Lift Suspension
                                                                    </a>
                                                                </form>
                                                            @else
                                                                <form id="suspendGroup_{{ $first_report->group->id }}"
                                                                    action="{{ route('admin.reports.group.suspend', $first_report->group->id) }}"
                                                                    method="POST"
                                                                    onsubmit="return confirm('Are you sure you want to suspend this group?')">
                                                                    @csrf
                                                                    @method('post')
                                                                    <a class="dropdown-item text-danger" href="#"
                                                                        onclick="document.getElementById('suspendGroup_{{ $first_report->group->id }}').submit()">
                                                                        <i class="ri-alert-line"></i> Suspend Group
                                                                    </a>
                                                                </form>
                                                            @endif
                                                        </li>
                                                    </ul>
=======
    </div>
    <!-- Page Header Close -->

    <!-- Start::row-1 -->
    <div class="col-xl-12">
        <div class="card custom-card">

            <div class="card-body">
                <div class="table-responsive" style="min-height: 250px">
                    <table class="table text-nowrap table-hover border table-bordered">
                        <thead>
                            <tr>
                                <th scope="col">Group Name</th>
                                <th scope="col">Description</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($group_report_lists->groupBy('group_id') as $group_id => $group_reports)
                                @php
                                    $first_report = $group_reports->first();
                                @endphp
                                @if ($first_report && $first_report->group && $first_report->user)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.users.show', $first_report->id) }}">
                                                <div class="d-flex align-items-center fw-semibold">
                                                    <span class="avatar avatar-sm me-2 avatar-rounded">
                                                        <img src="{{ $first_report->group->image }}" alt="img">
                                                    </span>
                                                    <a class="text-primary" href="{{ url('https://web.talkam.prodevs.io/group/' . $first_report->group->id . '/featured') }}" target="_blank" rel="noopener noreferrer">{{ $first_report->group->name }}</a>
>>>>>>> b3c3aac306096df41a5f769351d73014036f32dd
                                                </div>
                                            </td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td colspan="5" class="text-center">Some data is missing or removed</td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No records found</td>
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

        <!-- Reported Group Members Section -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-body">
                    <h5 class="card-title">Reported Group Members</h5>
                    <div class="table-responsive" style="min-height: 250px">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Member Name</th>
                                    <th scope="col">Group Name</th>
                                    <th scope="col">Suspension Count</th>
                                    <th scope="col">Suspension Ends</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reported_members as $report)
                                    <tr>
                                        <td>{{ $report->groupMember->user->full_name }}</td>
                                        <td>{{ $report->groupMember->group->name }}</td>
                                        <td>{{ $report->groupMember->suspension_count ?? 0 }}</td>
                                        <td>
                                            @if ($report->groupMember->suspension_end)
                                                {{ $report->groupMember->suspension_end->format('Y-m-d h:i A') }}
                                            @else
                                                Not Applicable
                                            @endif
                                        </td>
                                        <td>
                                            <span
                                                class="badge bg-{{ pillClasses($report->groupMember->status) }}-transparent">
                                                {{ $report->groupMember->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                    role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item"
                                                            href="{{ route('admin.reports.group-member.show', $report->id) }}">
                                                            <i class="ri-eye-line"></i> View
                                                        </a>
                                                    </li>
                                                    @if ($report->user->banned)
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#">
                                                                <i class="ri-error-warning-line"></i> User Banned
                                                            </a>
                                                        </li>
                                                    @elseif ($report->groupMember->suspension_end && $report->groupMember->suspension_end > now())
                                                        <li>
                                                            <form id="undoSuspension_{{ $report->id }}"
                                                                action="{{ route('admin.reports.group-member.undo-suspension', $report->id) }}"
                                                                method="POST"
                                                                onsubmit="return confirm('Are you sure you want to lift this suspension?')">
                                                                @csrf
                                                                <a class="dropdown-item text-primary" href="#"
                                                                    onclick="document.getElementById('undoSuspension_{{ $report->id }}').submit()">
                                                                    <i class="ri-alert-line"></i> Undo Suspension
                                                                </a>
                                                            </form>
                                                        </li>
                                                    @else
                                                        <li>
                                                            <form id="suspendUser_{{ $report->id }}"
                                                                action="{{ route('admin.reports.group-member.suspend', $report->id) }}"
                                                                method="POST"
                                                                onsubmit="return confirm('Are you sure you want to suspend this user?')">
                                                                @csrf
                                                                <a class="dropdown-item text-danger" href="#"
                                                                    onclick="document.getElementById('suspendUser_{{ $report->id }}').submit()">
                                                                    <i class="ri-alert-line"></i> Suspend User
                                                                </a>
                                                            </form>
                                                        </li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No records found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            {{ $reported_members->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
