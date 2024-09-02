@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <h1 class="page-title fw-semibold fs-18 mb-0">Notifications</h1>
            </div>
            <div class="ms-md-1 ms-0 d-flex align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Notifications</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>

            </div>
        </div>

        <!-- Page Header Close -->
        <!-- Start::row-1 -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header d-flex justify-content-end">
                    <a href="{{ route('admin.notifications.send-bulk-notification.create') }}" class="btn btn-primary"><i class="fe fe-plus"></i> <span class="ml-3">Create</span></a>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="min-height: 250px">
                        @if ($notifications->isNotEmpty())
                            <table class="table text-nowrap table-hover border table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">S/N</th>
                                        <th scope="col">Title</th>
                                        <th scope="col">Message</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Scheduled Date</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($notifications as $notification)
                                        <tr>
                                            <td>
                                                {{ $sn++ }}
                                            </td>
                                            <td>
                                                <span title="{{ $notification->title }}">{{ Str::limit($notification->title, 50) }}</span>
                                            </td>
                                            <td>
                                                <button type="button" data-bs-toggle="modal" data-bs-target="#notificationContent_{{ $notification->id }}" class="btn btn-primary btn-sm show-body">
                                                    View
                                                </button>
                                            </td>

                                            <td>{{ $notification->type }}</td>
                                            <td>
                                                <span class="badge bg-{{ pillClasses($notification->status) }}-transparent">
                                                    {{ $notification->status }}
                                                </span>
                                            </td>
                                            <td>{{ $notification->schedule_date }}</td>
                                            <td>
                                                <div class="dropdown">
                                                    <a class="btn btn-outline-primary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Actions
                                                    </a>
                                                    <ul class="dropdown-menu">

                                                        @if ($notification->status !== $Sent)
                                                            <li>
                                                                <a class="dropdown-item" href="{{ route('admin.notifications.send-bulk-notification.edit', $notification->id) }}">
                                                                    <i class="ri-edit-2-line"></i>| Edit
                                                                </a>
                                                            </li>
                                                        @endif

                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" onclick="$('#deleteNotificationForm_{{ $notification->id }}').submit()" onsubmit="return confim('Are you sure of this action?')">
                                                                <i class="ri-delete-bin-line"></i> | Delete
                                                            </a>
                                                            <form id="deleteNotificationForm_{{ $notification->id }}').submit()" action="{{ route('admin.notifications.send-bulk-notification.destroy', $notification->id) }}" method="POST" style="display: none;">
                                                                @csrf
                                                                @method('DELETE')
                                                            </form>
                                                        </li>

                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Hidden Delete Form -->
                                        @include('dashboards.admin.pages.bulk-messages.modal.notification_content_modal', [
                                            'modalKey' => "notificationContent_$notification->id",
                                        ])
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            @include('general.components.no_content')
                        @endif
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            {{ $notifications->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this notification?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="bodyModal" tabindex="-1" aria-labelledby="bodyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bodyModalLabel">Notification Body</h5>
                </div>
                <div class="modal-body">
                    <!-- Body content will be dynamically inserted here -->
                    <div id="modalBodyContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDeletion(notificationId) {
            // Show the modal
            $('#deleteConfirmationModal').modal('show');

            // Set the form action to the correct notification ID when the Delete button is clicked
            $('#confirmDeleteBtn').off('click').on('click', function() {
                var form = document.getElementById('deleteNotificationForm_' + notificationId);
                if (form) {
                    form.submit();
                } else {
                    console.error('Delete form not found for notification ID: ' + notificationId);
                }
            });
        }
    </script>
@endsection
