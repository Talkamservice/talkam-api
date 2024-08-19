@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Reported Group Members</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Reported Group Members</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Reported Group Members Section -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="table-responsive" style="min-height: 250px">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Member Name</th>
                                    <th scope="col">Group Name</th>
                                    <th scope="col">Group Description</th>
                                    <th scope="col">Total Suspension</th>
                                    <th scope="col">Suspension Ends At</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reported_members as $report)
                                    <tr>
                                        <td>
                                            <a class="text-primary" href="{{ route('admin.users.show',  $report->groupMember->user->id) }}">
                                                <div class="d-flex align-items-center fw-semibold">
                                                    <span class="avatar avatar-sm me-2 avatar-rounded">
                                                        <img src="{{  $report->groupMember->user->avatarUrl() }}"
                                                            alt="img">
                                                    </span>{{  $report->groupMember->user->username ??  $report->groupMember->user->full_name}}
                                                </div>
                                            </a>
                                        </td>
                                        <td>
                                            <a class="text-primary" href="{{ url('https://web.talkam.prodevs.io/group/' . $report->groupMember->group_id . '/featured') }}" target="_blank" rel="noopener noreferrer">{{ $report->groupMember->group->name }}</a>
                                        </td>
                                        <td>{{ $report->groupMember->group->name }}</td>
                                        <td>{{ $report->groupMember->suspension_count ?? 0 }}</td>
                                        <td>
                                            @if ($report->groupMember->suspension_end)
                                                {{ carbon()->parse($report->groupMember->suspension_end)->format('Y-m-d h:i A') }}
                                            @else
                                                N/A
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
                {{-- <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            {{ $reported_members->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div> --}}
            </div>
        </div>

    </div>
@endsection
