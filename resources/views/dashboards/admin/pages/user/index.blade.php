@extends('dashboards.admin.layout.app')

@section('content')
    <style>
        .highlighted-column {
            background-color: rgba(179, 5, 86, 0.959);
            font-weight: bold;
            padding: 10px;
            border-radius: 10px;
        }

        /* Ensuring the highlighted column is always visible */
        @media (max-width: 767.98px) {
            .highlighted-column {
                padding: 5px;
                font-size: 12px;
            }
        }

        @media (min-width: 768px) and (max-width: 991.98px) {
            .highlighted-column {
                padding: 8px;
                font-size: 14px;
            }
        }

        @media (min-width: 992px) {
            .highlighted-column {
                padding: 10px;
                font-size: 16px;
            }
        }
    </style>

    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Users</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Users</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
                <div class=""></div>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Start::row-1 -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header">
                    <form action="{{ url()->current() }}" method="get" class="d-flex justify-content-between">
                        <div class="form-group me-2">
                            <label for="">Search</label>
                            <input class="form-control" type="text" value="{{ request()->search }}"
                                placeholder="Search...." name="search">
                        </div>
                        <div class="form-group me-2">
                            <label for="">Status</label>
                            <select name="status" class="form-control">
                                <option value="">Select Option</option>
                                @foreach ($statuses as $key => $status)
                                    <option value="{{ $key }}" {{ request()->status == $key ? 'selected' : '' }}>
                                        {{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group me-2">
                            <label for="">From</label>
                            <input class="form-control" type="date" value="{{ request()->from }}"
                                placeholder="Search...." name="from">
                        </div>
                        <div class="form-group me-2">
                            <label for="">To</label>
                            <input class="form-control" type="date" value="{{ request()->to }}" placeholder="Search...."
                                name="to">
                        </div>
                        <div class="form-group me-2" style="margin-top: 20px;">
                            <button class="btn btn-sm btn-success p-2">Filter</button>
                        </div>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">S/N</th>
                                    <th scope="col">Username</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    <tr class="highlight-user-{{ $user->id }}">
                                        <td>{{ $sn++ }}</td>
                                        <td>
                                            <div class="d-flex align-items-center fw-semibold">
                                                <span class="avatar avatar-sm me-2 avatar-rounded">
                                                    <img src="{{ $user->avatarUrl() }}" alt="img">
                                                </span>{{ $user->username }}
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
                                            <div class="dropdown">
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                    role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </a>
                                                <ul class="dropdown-menu">
                                                    @can(slugPermission('read user'))
                                                        <li>
                                                            <a class="dropdown-item"
                                                                href="{{ route('admin.users.show', $user->id) }}">
                                                                <i class="ri-eye-line"></i> | View
                                                            </a>
                                                        </li>
                                                    @endcan
                                                    @canAny(slugPermission('suspend a user'), slugPermission('unsuspend a
                                                        user'))
                                                        <li>
                                                            @if ($user->status == 'Active')
                                                                @can(slugPermission('suspend a user'))
                                                                    <a class="dropdown-item text-warning" href="#"
                                                                        onclick="openSuspendUserModal('{{ $user->id }}')">
                                                                        <i class="ri-close-line"></i> | Suspend
                                                                    </a>
                                                                @endcan
                                                            @else
                                                                @can(slugPermission('unsuspend a user'))
                                                                    <a class="dropdown-item text-primary" href="#"
                                                                        onclick="openActionsModal('activate', '{{ route('admin.users.suspend', $user->id) }}')">
                                                                        <i class="ri-check-line"></i> | Activate
                                                                    </a>
                                                                @endcan
                                                            @endif
                                                        </li>
                                                    @endcanAny

                                                    @can(slugPermission('strike a user'))
                                                        <li>
                                                            <a class="dropdown-item text-warning" href="#"
                                                                onclick="openActionsModal('strike', '{{ route('admin.users.strike', $user->id) }}')">
                                                                <!-- SVG icon here -->
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                    width="13" height="13" fill="currentColor">
                                                                    <path
                                                                        d="M12.5002 2C12.2241 2 12.0002 2.22386 12.0002 2.5V12H10.0002V4.5C10.0002 4.22386 9.77634 4 9.5002 4C9.22405 4 9.0002 4.22386 9.0002 4.5V14C8.64653 14 7.00024 14 7.00024 14C6.61911 12.3792 5.64236 11.4407 4.5954 11.3216C4.87926 12.0664 5.36117 13.2592 6.16634 15.0995C7.02511 17.0622 7.89128 18.5218 9.00374 19.4986C10.0783 20.442 11.4586 21 13.5002 21C16.5378 21 19.0002 18.5377 19.0002 15.5002V7C19.0002 6.72386 18.7763 6.5 18.5002 6.5C18.2241 6.5 18.0002 6.72386 18.0002 7V12H16.0002V4C16.0002 3.72386 15.7763 3.5 15.5002 3.5C15.2241 3.5 15.0002 3.72386 15.0002 4V12H13.0002V2.5C13.0002 2.22386 12.7763 2 12.5002 2ZM21.0002 15.5002C21.0002 19.6424 17.6423 23 13.5002 23C11.0417 23 9.17214 22.308 7.68416 21.0015C6.23411 19.7283 5.22528 17.9381 4.33405 15.9012C3.40393 13.7753 2.89004 12.4804 2.60991 11.7235C2.25318 10.7597 2.74616 9.41212 4.08583 9.31846C5.24076 9.23771 6.22061 9.61249 7.0002 10.2587V4.5C7.0002 3.11929 8.11949 2 9.5002 2C9.68522 2 9.86554 2.0201 10.0391 2.05823C10.2477 0.888227 11.2702 0 12.5002 0C13.5602 0 14.4661 0.659694 14.8298 1.59091C15.0431 1.53167 15.268 1.5 15.5002 1.5C16.8809 1.5 18.0002 2.61929 18.0002 4V4.55001C18.1618 4.51722 18.329 4.5 18.5002 4.5C19.8809 4.5 21.0002 5.61929 21.0002 7V15.5002Z" />
                                                                </svg> | Strike
                                                            </a>
                                                        </li>
                                                    @endcan

                                                    @can(slugPermission('ban a user'))
                                                        @if ($user->status !== 'Banned')
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="#"
                                                                    onclick="openBanUserModal('{{ $user->id }}')">
                                                                    <!-- SVG icon here -->
                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                        width="13" height="13" fill="currentColor">
                                                                        <path
                                                                            d="M12.5002 2C12.2241 2 12.0002 2.22386 12.0002 2.5V12H10.0002V4.5C10.0002 4.22386 9.77634 4 9.5002 4C9.22405 4 9.0002 4.22386 9.0002 4.5V14C8.64653 14 7.00024 14 7.00024 14C6.61911 12.3792 5.64236 11.4407 4.5954 11.3216C4.87926 12.0664 5.36117 13.2592 6.16634 15.0995C7.02511 17.0622 7.89128 18.5218 9.00374 19.4986C10.0783 20.442 11.4586 21 13.5002 21C16.5378 21 19.0002 18.5377 19.0002 15.5002V7C19.0002 6.72386 18.7763 6.5 18.5002 6.5C18.2241 6.5 18.0002 6.72386 18.0002 7V12H16.0002V4C16.0002 3.72386 15.7763 3.5 15.5002 3.5C15.2241 3.5 15.0002 3.72386 15.0002 4V12H13.0002V2.5C13.0002 2.22386 12.7763 2 12.5002 2ZM21.0002 15.5002C21.0002 19.6424 17.6423 23 13.5002 23C11.0417 23 9.17214 22.308 7.68416 21.0015C6.23411 19.7283 5.22528 17.9381 4.33405 15.9012C3.40393 13.7753 2.89004 12.4804 2.60991 11.7235C2.25318 10.7597 2.74616 9.41212 4.08583 9.31846C5.24076 9.23771 6.22061 9.61249 7.0002 10.2587V4.5C7.0002 3.11929 8.11949 2 9.5002 2C9.68522 2 9.86554 2.0201 10.0391 2.05823C10.2477 0.888227 11.2702 0 12.5002 0C13.5602 0 14.4661 0.659694 14.8298 1.59091C15.0431 1.53167 15.268 1.5 15.5002 1.5C16.8809 1.5 18.0002 2.61929 18.0002 4V4.55001C18.1618 4.51722 18.329 4.5 18.5002 4.5C19.8809 4.5 21.0002 5.61929 21.0002 7V15.5002Z" />
                                                                    </svg> | Ban
                                                                </a>
                                                            </li>
                                                        @endif
                                                    @endcan

                                                    @can(slugPermission('delete user'))
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="openActionsModal('delete', '{{ route('admin.users.destroy', $user->id) }}')">
                                                                <i class="ri-delete-bin-line"></i> | Delete
                                                            </a>
                                                        </li>
                                                    @endcan

                                                    @if ($user->posts()->onlyTrashed()->count())
                                                        <!-- Show Unhide option if there are trashed posts -->
                                                        @can(slugPermission('restore all posts of a user'))
                                                            <li>
                                                                <a href="#"
                                                                    class="dropdown-item text-warning restore-post"
                                                                    data-bs-toggle="modal"
                                                                    data-restore-url="{{ route('admin.users.restore-posts', $user->id) }}"
                                                                    data-bs-target="#unhidePostModal">
                                                                    <i class="ri-eye-line"></i> | Unhide All Posts
                                                                </a>
                                                            </li>
                                                        @endcan
                                                    @else
                                                        <!-- Show Hide option if there are no trashed posts -->
                                                        @can(slugPermission('hide all posts of a user'))
                                                            <li>
                                                                <a href="#" class="dropdown-item text-warning hide-post"
                                                                    data-bs-toggle="modal"
                                                                    data-hide-url="{{ route('admin.users.hide-posts', $user->id) }}"
                                                                    data-delete-url="{{ route('admin.users.remove-posts', $user->id) }}"
                                                                    data-bs-target="#actionModal">
                                                                    <i class="ri-eye-off-line"></i> | Hide All Posts
                                                                </a>
                                                            </li>
                                                        @endcan
                                                    @endif
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            {{ $users->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('dashboards.admin.pages.user.actions-modal')
    @include('dashboards.admin.pages.user.ban-user-modal')
    @include('dashboards.admin.pages.user.suspend-user-modal')
    <script>
        // Function to get URL parameters by name
        function getUrlParameter(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            var results = regex.exec(location.search);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Get the user ID to highlight from the URL parameter
            var highlightUserId = getUrlParameter('highlight_user_id');

            // If a user ID is provided, apply highlighting and scroll to the specific user's row
            if (highlightUserId) {
                // Add highlighting to the specific user's row
                var userRowToHighlight = document.querySelector('.highlight-user-' + highlightUserId);
                if (userRowToHighlight) {
                    userRowToHighlight.classList.add('highlighted-column');

                    // Scroll to the highlighted row
                    userRowToHighlight.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            var actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
            var unhidePostModal = new bootstrap.Modal(document.getElementById('unhidePostModal'));
            var actionForm = document.getElementById('actionForm');
            var unhideForm = document.getElementById('unhideForm');
            var actionTextElement = document.getElementById('actionText');
            var currentActionUrls = {};

            // Trigger modals with one button
            document.querySelectorAll('[data-bs-toggle="modal"]').forEach(function(element) {
                element.addEventListener('click', function() {
                    // Store action URLs in data attributes
                    var hideUrl = this.getAttribute('data-hide-url');
                    var restoreUrl = this.getAttribute('data-restore-url');
                    var deleteUrl = this.getAttribute('data-delete-url'); // For permanent delete

                    // Determine if the button is for hiding, restoring, or deleting
                    if (this.classList.contains('hide-post')) {
                        currentActionUrls = {
                            hide: hideUrl,
                            delete: deleteUrl // Set delete URL
                        };
                        actionTextElement.textContent =
                            'Are you sure you want to perform this action?';
                        actionModal.show();
                    } else if (this.classList.contains('restore-post')) {
                        document.getElementById('unhideActionUrl').value = restoreUrl;
                        unhidePostModal.show();
                    }
                });
            });

            // Handle Hide action
            document.getElementById('hideButton').addEventListener('click', function() {
                if (confirm('Are you sure you want to hide this user post(s)?')) {
                    actionForm.action = currentActionUrls.hide;
                    actionForm.submit();
                }
            });

            // Handle Permanently Remove action
            document.getElementById('removeButton').addEventListener('click', function() {
                if (confirm('Are you sure you want to permanently remove this user post(s)?')) {
                    actionForm.action = currentActionUrls.delete;
                    document.getElementById('formMethod').value = 'DELETE'; // Use DELETE for removing
                    actionForm.submit();
                }
            });

            // Handle Restore action
            document.getElementById('restoreButton').addEventListener('click', function() {
                if (confirm('Are you sure you want to restore this user Post(s)?')) {
                    unhideForm.action = document.getElementById('unhideActionUrl').value;
                    unhideForm.submit();
                }
            });
        });
    </script>

    @include('dashboards.admin.pages.user.hide-and-unhide-post-modal')
@endsection
