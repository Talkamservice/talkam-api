@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Report Information</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.reports.group.member.lists') }}">Index</a></li>
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

                        <div class="dropdown ms-auto me-auto">
                            <a class="btn btn-outline-primary dropdown-toggle" href="#" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false" data-bs-toggle="tooltip"
                                data-bs-placement="top" title="Show Actions">
                                Action
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item"
                                        href="{{ route('admin.reports.group-member.show', $group_member_report->id) }}"
                                        data-bs-toggle="tooltip" data-bs-placement="right" title="View Report">
                                        <i class="ri-eye-line"></i> | View
                                    </a>
                                </li>

                                @if (
                                    ($group_member_report->groupMember->suspension_end && $group_member_report->groupMember->suspension_end > now()) ||
                                        $group_member_report->groupMember->status === 'Suspended')
                                    <li>
                                        <a class="dropdown-item text-primary" href="#"
                                            onclick="openActionsModal('undo', '{{ route('admin.reports.group-member.undo-suspension', $group_member_report->groupMember->id) }}')"
                                            data-bs-toggle="tooltip" data-bs-placement="right" title="Undo Suspension">
                                            <i class="ri-alert-line"></i> | Undo Suspension
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-warning" href="#"
                                            onclick="openActionsModal('strike', '{{ route('admin.users.strike', $group_member_report->groupMember->user->id) }}')"
                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                            title="Send a warning to user">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="13"
                                                height="13" fill="currentColor">
                                                <path
                                                    d="M12.5002 2C12.2241 2 12.0002 2.22386 12.0002 2.5V12H10.0002V4.5C10.0002 4.22386 9.77634 4 9.5002 4C9.22405 4 9.0002 4.22386 9.0002 4.5V14C8.64653 14 7.00024 14 7.00024 14C6.61911 12.3792 5.64236 11.4407 4.5954 11.3216C4.87926 12.0664 5.36117 13.2592 6.16634 15.0995C7.02511 17.0622 7.89128 18.5218 9.00374 19.4986C10.0783 20.442 11.4586 21 13.5002 21C16.5378 21 19.0002 18.5377 19.0002 15.5002V7C19.0002 6.72386 18.7763 6.5 18.5002 6.5C18.2241 6.5 18.0002 6.72386 18.0002 7V12H16.0002V4C16.0002 3.72386 15.7763 3.5 15.5002 3.5C15.2241 3.5 15.0002 3.72386 15.0002 4V12H13.0002V2.5C13.0002 2.22386 12.7763 2 12.5002 2ZM21.0002 15.5002C21.0002 19.6424 17.6423 23 13.5002 23C11.0417 23 9.17214 22.308 7.68416 21.0015C6.23411 19.7283 5.22528 17.9381 4.33405 15.9012C3.40393 13.7753 2.89004 12.4804 2.60991 11.7235C2.25318 10.7597 2.74616 9.41212 4.08583 9.31846C5.24076 9.23771 6.22061 9.61249 7.0002 10.2587V4.5C7.0002 3.11929 8.11949 2 9.5002 2C9.68522 2 9.86554 2.0201 10.0391 2.05823C10.2477 0.888227 11.2702 0 12.5002 0C13.5602 0 14.4661 0.659694 14.8298 1.59091C15.0431 1.53167 15.268 1.5 15.5002 1.5C16.8809 1.5 18.0002 2.61929 18.0002 4V4.55001C18.1618 4.51722 18.329 4.5 18.5002 4.5C19.8809 4.5 21.0002 5.61929 21.0002 7V15.5002Z">
                                                </path>
                                            </svg> | Strike
                                        </a>
                                    </li>
                                @else
                                    <li>
                                        <a class="dropdown-item text-danger suspend-btn" href="#"
                                            onclick="openModal('{{ $group_member_report->id }}')" data-bs-toggle="tooltip"
                                            data-bs-placement="right" title="Suspend User from this group">
                                            <i class="ri-alert-line"></i> | Suspend
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-warning" href="#"
                                            onclick="openActionsModal('strike', '{{ route('admin.users.strike', $group_member_report->groupMember->user->id) }}')"
                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                            title="Send a warning to user">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="13"
                                                height="13" fill="currentColor">
                                                <path
                                                    d="M12.5002 2C12.2241 2 12.0002 2.22386 12.0002 2.5V12H10.0002V4.5C10.0002 4.22386 9.77634 4 9.5002 4C9.22405 4 9.0002 4.22386 9.0002 4.5V14C8.64653 14 7.00024 14 7.00024 14C6.61911 12.3792 5.64236 11.4407 4.5954 11.3216C4.87926 12.0664 5.36117 13.2592 6.16634 15.0995C7.02511 17.0622 7.89128 18.5218 9.00374 19.4986C10.0783 20.442 11.4586 21 13.5002 21C16.5378 21 19.0002 18.5377 19.0002 15.5002V7C19.0002 6.72386 18.7763 6.5 18.5002 6.5C18.2241 6.5 18.0002 6.72386 18.0002 7V12H16.0002V4C16.0002 3.72386 15.7763 3.5 15.5002 3.5C15.2241 3.5 15.0002 3.72386 15.0002 4V12H13.0002V2.5C13.0002 2.22386 12.7763 2 12.5002 2ZM21.0002 15.5002C21.0002 19.6424 17.6423 23 13.5002 23C11.0417 23 9.17214 22.308 7.68416 21.0015C6.23411 19.7283 5.22528 17.9381 4.33405 15.9012C3.40393 13.7753 2.89004 12.4804 2.60991 11.7235C2.25318 10.7597 2.74616 9.41212 4.08583 9.31846C5.24076 9.23771 6.22061 9.61249 7.0002 10.2587V4.5C7.0002 3.11929 8.11949 2 9.5002 2C9.68522 2 9.86554 2.0201 10.0391 2.05823C10.2477 0.888227 11.2702 0 12.5002 0C13.5602 0 14.4661 0.659694 14.8298 1.59091C15.0431 1.53167 15.268 1.5 15.5002 1.5C16.8809 1.5 18.0002 2.61929 18.0002 4V4.55001C18.1618 4.51722 18.329 4.5 18.5002 4.5C19.8809 4.5 21.0002 5.61929 21.0002 7V15.5002Z">
                                                </path>
                                            </svg> | Strike
                                        </a>
                                    </li>
                                @endif
                                @can(slugPermission('ban a user'))
                                    @if ($group_member_report->groupMember->user->status !== 'Banned')
                                        <li>
                                            <a class="dropdown-item text-danger" href="#"
                                                onclick="openBanUserModal('{{ $group_member_report->groupMember->user->id }}')"
                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                title="Banned a user from the application">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="13"
                                                    height="13" fill="currentColor">
                                                    <path
                                                        d="M12.5002 2C12.2241 2 12.0002 2.22386 12.0002 2.5V12H10.0002V4.5C10.0002 4.22386 9.77634 4 9.5002 4C9.22405 4 9.0002 4.22386 9.0002 4.5V14C8.64653 14 7.00024 14 7.00024 14C6.61911 12.3792 5.64236 11.4407 4.5954 11.3216C4.87926 12.0664 5.36117 13.2592 6.16634 15.0995C7.02511 17.0622 7.89128 18.5218 9.00374 19.4986C10.0783 20.442 11.4586 21 13.5002 21C16.5378 21 19.0002 18.5377 19.0002 15.5002V7C19.0002 6.72386 18.7763 6.5 18.5002 6.5C18.2241 6.5 18.0002 6.72386 18.0002 7V12H16.0002V4C16.0002 3.72386 15.7763 3.5 15.5002 3.5C15.2241 3.5 15.0002 3.72386 15.0002 4V12H13.0002V2.5C13.0002 2.22386 12.7763 2 12.5002 2ZM21.0002 15.5002C21.0002 19.6424 17.6423 23 13.5002 23C11.0417 23 9.17214 22.308 7.68416 21.0015C6.23411 19.7283 5.22528 17.9381 4.33405 15.9012C3.40393 13.7753 2.89004 12.4804 2.60991 11.7235C2.25318 10.7597 2.74616 9.41212 4.08583 9.31846C5.24076 9.23771 6.22061 9.61249 7.0002 10.2587V4.5C7.0002 3.11929 8.11949 2 9.5002 2C9.68522 2 9.86554 2.0201 10.0391 2.05823C10.2477 0.888227 11.2702 0 12.5002 0C13.5602 0 14.4661 0.659694 14.8298 1.59091C15.0431 1.53167 15.268 1.5 15.5002 1.5C16.8809 1.5 18.0002 2.61929 18.0002 4V4.55001C18.1618 4.51722 18.329 4.5 18.5002 4.5C19.8809 4.5 21.0002 5.61929 21.0002 7V15.5002Z">
                                                    </path>
                                                </svg> | Ban User
                                            </a>

                                        </li>
                                    @endif
                                @endcan
                                @can(slugPermission('suspend a user'))
                                    <li>
                                        @if ($group_member_report->groupMember->user->status === 'Inactive')
                                            @can(slugPermission('unsuspend a user'))
                                                <a class="dropdown-item text-primary" href="#"
                                                    onclick="openActionsModal('activate', '{{ route('admin.users.suspend', $group_member_report->groupMember->user->id) }}')">
                                                    <i class="ri-check-line"></i> | Activate User
                                                </a>
                                            @endcan
                                        @else
                                            @can(slugPermission('unsuspend a user'))
                                                <a class="dropdown-item text-warning" href="#"
                                                    onclick="openActionsModal('{{ $group_member_report->groupMember->user->id }}')"
                                                    data-bs-toggle="tooltip" data-bs-placement="right"
                                                    title="Suspend a user from the application">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="13"
                                                        height="13" fill="currentColor">
                                                        <path
                                                            d="M12.5002 2C12.2241 2 12.0002 2.22386 12.0002 2.5V12H10.0002V4.5C10.0002 4.22386 9.77634 4 9.5002 4C9.22405 4 9.0002 4.22386 9.0002 4.5V14C8.64653 14 7.00024 14 7.00024 14C6.61911 12.3792 5.64236 11.4407 4.5954 11.3216C4.87926 12.0664 5.36117 13.2592 6.16634 15.0995C7.02511 17.0622 7.89128 18.5218 9.00374 19.4986C10.0783 20.442 11.4586 21 13.5002 21C16.5378 21 19.0002 18.5377 19.0002 15.5002V7C19.0002 6.72386 18.7763 6.5 18.5002 6.5C18.2241 6.5 18.0002 6.72386 18.0002 7V12H16.0002V4C16.0002 3.72386 15.7763 3.5 15.5002 3.5C15.2241 3.5 15.0002 3.72386 15.0002 4V12H13.0002V2.5C13.0002 2.22386 12.7763 2 12.5002 2ZM21.0002 15.5002C21.0002 19.6424 17.6423 23 13.5002 23C11.0417 23 9.17214 22.308 7.68416 21.0015C6.23411 19.7283 5.22528 17.9381 4.33405 15.9012C3.40393 13.7753 2.89004 12.4804 2.60991 11.7235C2.25318 10.7597 2.74616 9.41212 4.08583 9.31846C5.24076 9.23771 6.22061 9.61249 7.0002 10.2587V4.5C7.0002 3.11929 8.11949 2 9.5002 2C9.68522 2 9.86554 2.0201 10.0391 2.05823C10.2477 0.888227 11.2702 0 12.5002 0C13.5602 0 14.4661 0.659694 14.8298 1.59091C15.0431 1.53167 15.268 1.5 15.5002 1.5C16.8809 1.5 18.0002 2.61929 18.0002 4V4.55001C18.1618 4.51722 18.329 4.5 18.5002 4.5C19.8809 4.5 21.0002 5.61929 21.0002 7V15.5002Z">
                                                        </path>
                                                    </svg> | Suspend User
                                                </a>
                                            @endcan
                                        @endif
                                    </li>
                                @endcan
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
                                                <a class="text-primary"
                                                    href="{{ route('admin.users.show', $group_member_report->user_id) }}">
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
    @include('dashboards.admin.pages.report.group.member.actions-modal')
    @include('dashboards.admin.pages.user.ban-user-modal')
    @include('dashboards.admin.pages.user.suspend-user-modal')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
@endsection
