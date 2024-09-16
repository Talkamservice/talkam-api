@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Reported Group Members</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Reported Group Members Section -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header">
                    <form action="{{ url()->current() }}" method="get" class="row g-3">
                        <div class="col-12 col-md-5 col-xl-5 col-lg-5">
                            <div class="form-group">
                                <label for="search">Search (suspension,status)</label>
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
                                    <th scope="col">Member Name</th>
                                    <th scope="col">Group Name</th>
                                    <th scope="col">Group Description</th>
                                    <th scope="col">Suspension Ends At</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reported_members as $report)
                                    <tr>
                                        <td>
                                            <a class="text-primary"
                                                href="{{ route('admin.users.show', $report->groupMember->user->id) }}">
                                                <div class="d-flex align-items-center fw-semibold">
                                                    <span class="avatar avatar-sm me-2 avatar-rounded">
                                                        <img src="{{ $report->groupMember->user->avatarUrl() }}"
                                                            alt="img">
                                                    </span>{{ $report->groupMember->user->username ?? $report->groupMember->user->full_name }}
                                                </div>
                                            </a>
                                        </td>
                                        <td>
                                            <a class="text-primary"
                                                href="{{ url('https://web.talkam.prodevs.io/group/' . $report->groupMember->group_id . '/featured') }}"
                                                target="_blank"
                                                rel="noopener noreferrer">{{ $report->groupMember->group->name }}</a>
                                        </td>
                                        <td>{{ $report->groupMember->group->name }}</td>
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
                                                    role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Show Actions">
                                                    Action
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item"
                                                            href="{{ route('admin.reports.group-member.show', $report->id) }}"
                                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                                            title="View Report">
                                                            <i class="ri-eye-line"></i> | View
                                                        </a>
                                                    </li>
                                                  
                                                    @if (
                                                        ($report->groupMember->suspension_end && $report->groupMember->suspension_end > now()) ||
                                                            $report->groupMember->status === 'Suspended')
                                                        <li>
                                                            <a class="dropdown-item text-primary" href="#"
                                                                onclick="openActionsModal('undo', '{{ route('admin.reports.group-member.undo-suspension', $report->groupMember->id) }}')"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="Undo Suspension">
                                                                <i class="ri-alert-line"></i> | Undo Suspension
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item text-warning" href="#"
                                                                onclick="openActionsModal('strike', '{{ route('admin.users.strike', $report->groupMember->user->id) }}')"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="Send a warning to user">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                    width="13" height="13" fill="currentColor">
                                                                    <path
                                                                        d="M12.5002 2C12.2241 2 12.0002 2.22386 12.0002 2.5V12H10.0002V4.5C10.0002 4.22386 9.77634 4 9.5002 4C9.22405 4 9.0002 4.22386 9.0002 4.5V14C8.64653 14 7.00024 14 7.00024 14C6.61911 12.3792 5.64236 11.4407 4.5954 11.3216C4.87926 12.0664 5.36117 13.2592 6.16634 15.0995C7.02511 17.0622 7.89128 18.5218 9.00374 19.4986C10.0783 20.442 11.4586 21 13.5002 21C16.5378 21 19.0002 18.5377 19.0002 15.5002V7C19.0002 6.72386 18.7763 6.5 18.5002 6.5C18.2241 6.5 18.0002 6.72386 18.0002 7V12H16.0002V4C16.0002 3.72386 15.7763 3.5 15.5002 3.5C15.2241 3.5 15.0002 3.72386 15.0002 4V12H13.0002V2.5C13.0002 2.22386 12.7763 2 12.5002 2ZM21.0002 15.5002C21.0002 19.6424 17.6423 23 13.5002 23C11.0417 23 9.17214 22.308 7.68416 21.0015C6.23411 19.7283 5.22528 17.9381 4.33405 15.9012C3.40393 13.7753 2.89004 12.4804 2.60991 11.7235C2.25318 10.7597 2.74616 9.41212 4.08583 9.31846C5.24076 9.23771 6.22061 9.61249 7.0002 10.2587V4.5C7.0002 3.11929 8.11949 2 9.5002 2C9.68522 2 9.86554 2.0201 10.0391 2.05823C10.2477 0.888227 11.2702 0 12.5002 0C13.5602 0 14.4661 0.659694 14.8298 1.59091C15.0431 1.53167 15.268 1.5 15.5002 1.5C16.8809 1.5 18.0002 2.61929 18.0002 4V4.55001C18.1618 4.51722 18.329 4.5 18.5002 4.5C19.8809 4.5 21.0002 5.61929 21.0002 7V15.5002Z">
                                                                    </path>
                                                                </svg> | Strike
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="openActionsModal('ban', '{{ route('admin.users.ban', $report->groupMember->user->id) }}')"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="Banned a user from the application">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                    width="13" height="13" fill="currentColor">
                                                                    <path
                                                                        d="M12.5002 2C12.2241 2 12.0002 2.22386 12.0002 2.5V12H10.0002V4.5C10.0002 4.22386 9.77634 4 9.5002 4C9.22405 4 9.0002 4.22386 9.0002 4.5V14C8.64653 14 7.00024 14 7.00024 14C6.61911 12.3792 5.64236 11.4407 4.5954 11.3216C4.87926 12.0664 5.36117 13.2592 6.16634 15.0995C7.02511 17.0622 7.89128 18.5218 9.00374 19.4986C10.0783 20.442 11.4586 21 13.5002 21C16.5378 21 19.0002 18.5377 19.0002 15.5002V7C19.0002 6.72386 18.7763 6.5 18.5002 6.5C18.2241 6.5 18.0002 6.72386 18.0002 7V12H16.0002V4C16.0002 3.72386 15.7763 3.5 15.5002 3.5C15.2241 3.5 15.0002 3.72386 15.0002 4V12H13.0002V2.5C13.0002 2.22386 12.7763 2 12.5002 2ZM21.0002 15.5002C21.0002 19.6424 17.6423 23 13.5002 23C11.0417 23 9.17214 22.308 7.68416 21.0015C6.23411 19.7283 5.22528 17.9381 4.33405 15.9012C3.40393 13.7753 2.89004 12.4804 2.60991 11.7235C2.25318 10.7597 2.74616 9.41212 4.08583 9.31846C5.24076 9.23771 6.22061 9.61249 7.0002 10.2587V4.5C7.0002 3.11929 8.11949 2 9.5002 2C9.68522 2 9.86554 2.0201 10.0391 2.05823C10.2477 0.888227 11.2702 0 12.5002 0C13.5602 0 14.4661 0.659694 14.8298 1.59091C15.0431 1.53167 15.268 1.5 15.5002 1.5C16.8809 1.5 18.0002 2.61929 18.0002 4V4.55001C18.1618 4.51722 18.329 4.5 18.5002 4.5C19.8809 4.5 21.0002 5.61929 21.0002 7V15.5002Z">
                                                                    </path>
                                                                </svg> | Ban
                                                            </a>

                                                        </li>
                                                    @else
                                                        <li>
                                                            <a class="dropdown-item text-danger suspend-btn" href="#"
                                                                onclick="openModal('{{ $report->id }}')"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="Suspend User from this group">
                                                                <i class="ri-alert-line"></i> | Suspend
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item text-warning" href="#"
                                                                onclick="openActionsModal('strike', '{{ route('admin.users.strike', $report->groupMember->user->id) }}')"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="Send a warning to user">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                    width="13" height="13" fill="currentColor">
                                                                    <path
                                                                        d="M12.5002 2C12.2241 2 12.0002 2.22386 12.0002 2.5V12H10.0002V4.5C10.0002 4.22386 9.77634 4 9.5002 4C9.22405 4 9.0002 4.22386 9.0002 4.5V14C8.64653 14 7.00024 14 7.00024 14C6.61911 12.3792 5.64236 11.4407 4.5954 11.3216C4.87926 12.0664 5.36117 13.2592 6.16634 15.0995C7.02511 17.0622 7.89128 18.5218 9.00374 19.4986C10.0783 20.442 11.4586 21 13.5002 21C16.5378 21 19.0002 18.5377 19.0002 15.5002V7C19.0002 6.72386 18.7763 6.5 18.5002 6.5C18.2241 6.5 18.0002 6.72386 18.0002 7V12H16.0002V4C16.0002 3.72386 15.7763 3.5 15.5002 3.5C15.2241 3.5 15.0002 3.72386 15.0002 4V12H13.0002V2.5C13.0002 2.22386 12.7763 2 12.5002 2ZM21.0002 15.5002C21.0002 19.6424 17.6423 23 13.5002 23C11.0417 23 9.17214 22.308 7.68416 21.0015C6.23411 19.7283 5.22528 17.9381 4.33405 15.9012C3.40393 13.7753 2.89004 12.4804 2.60991 11.7235C2.25318 10.7597 2.74616 9.41212 4.08583 9.31846C5.24076 9.23771 6.22061 9.61249 7.0002 10.2587V4.5C7.0002 3.11929 8.11949 2 9.5002 2C9.68522 2 9.86554 2.0201 10.0391 2.05823C10.2477 0.888227 11.2702 0 12.5002 0C13.5602 0 14.4661 0.659694 14.8298 1.59091C15.0431 1.53167 15.268 1.5 15.5002 1.5C16.8809 1.5 18.0002 2.61929 18.0002 4V4.55001C18.1618 4.51722 18.329 4.5 18.5002 4.5C19.8809 4.5 21.0002 5.61929 21.0002 7V15.5002Z">
                                                                    </path>
                                                                </svg> | Strike
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="openActionsModal('ban', '{{ route('admin.users.ban', $report->groupMember->user->id) }}')"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="Banned a user from the application">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                    width="13" height="13" fill="currentColor">
                                                                    <path
                                                                        d="M12.5002 2C12.2241 2 12.0002 2.22386 12.0002 2.5V12H10.0002V4.5C10.0002 4.22386 9.77634 4 9.5002 4C9.22405 4 9.0002 4.22386 9.0002 4.5V14C8.64653 14 7.00024 14 7.00024 14C6.61911 12.3792 5.64236 11.4407 4.5954 11.3216C4.87926 12.0664 5.36117 13.2592 6.16634 15.0995C7.02511 17.0622 7.89128 18.5218 9.00374 19.4986C10.0783 20.442 11.4586 21 13.5002 21C16.5378 21 19.0002 18.5377 19.0002 15.5002V7C19.0002 6.72386 18.7763 6.5 18.5002 6.5C18.2241 6.5 18.0002 6.72386 18.0002 7V12H16.0002V4C16.0002 3.72386 15.7763 3.5 15.5002 3.5C15.2241 3.5 15.0002 3.72386 15.0002 4V12H13.0002V2.5C13.0002 2.22386 12.7763 2 12.5002 2ZM21.0002 15.5002C21.0002 19.6424 17.6423 23 13.5002 23C11.0417 23 9.17214 22.308 7.68416 21.0015C6.23411 19.7283 5.22528 17.9381 4.33405 15.9012C3.40393 13.7753 2.89004 12.4804 2.60991 11.7235C2.25318 10.7597 2.74616 9.41212 4.08583 9.31846C5.24076 9.23771 6.22061 9.61249 7.0002 10.2587V4.5C7.0002 3.11929 8.11949 2 9.5002 2C9.68522 2 9.86554 2.0201 10.0391 2.05823C10.2477 0.888227 11.2702 0 12.5002 0C13.5602 0 14.4661 0.659694 14.8298 1.59091C15.0431 1.53167 15.268 1.5 15.5002 1.5C16.8809 1.5 18.0002 2.61929 18.0002 4V4.55001C18.1618 4.51722 18.329 4.5 18.5002 4.5C19.8809 4.5 21.0002 5.61929 21.0002 7V15.5002Z">
                                                                    </path>
                                                                </svg> | Ban
                                                            </a>

                                                        </li>
                                                       
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
            </div>
        </div>

    </div>

    @include('dashboards.admin.pages.report.group.member.suspend-ban-modal')
    @include('dashboards.admin.pages.report.group.member.actions-modal')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
@endsection
