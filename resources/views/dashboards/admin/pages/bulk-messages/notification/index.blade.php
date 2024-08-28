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
                    <li class="breadcrumb-item active" aria-current="page">Index</li>
                </ol>
            </nav>
            <a href="{{ route('admin.notifications.send-bulk-notification.create') }}" class="btn btn-primary ms-3">Create</a>
        </div>
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
                                <th scope="col">Title</th>
                                <th scope="col">Body</th>
                                <th scope="col">Type</th>
                                <th scope="col">Status</th>
                                <th scope="col">Scheduled Date</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($notifications as $notification)
                                <tr>
                                    <td>{{ $notification->title }}</td>
                                    <td>{{ Str::limit($notification->message, 60) }}</td>
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
                                                {{-- <li>
                                                    <a class="dropdown-item" href="{{ route('admin.notifications.send-bulk-notification.show', $notification->id) }}">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </li> --}}
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.notifications.send-bulk-notification.edit', $notification->id) }}">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="confirmDeletion({{ $notification->id }})">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </li>
                                                
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <img class="no-data-image" src="{{ asset('admin_assets/images/empty/no-data-concept-illustration.jpg') }}" alt="No data available">
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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

<script>
    function confirmDeletion(notificationId) {
        // Show the modal
        $('#deleteConfirmationModal').modal('show');

        // Set the form action to the correct notification ID when the Delete button is clicked
        $('#confirmDeleteBtn').off('click').on('click', function() {
            document.getElementById('deleteNotificationForm_' + notificationId).submit();
        });
    }
</script>
@endsection
