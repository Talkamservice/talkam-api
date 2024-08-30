@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <h1 class="page-title fw-semibold fs-18 mb-0">Announcements</h1>
            </div>
            <div class="ms-md-1 ms-0 d-flex align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Announcements</a></li>
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
                    <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary">
                        <i class="fe fe-plus"></i> <span class="ml-3">Create</span>
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="min-height: 250px">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Banner</th>
                                    <th scope="col">Title</th>
                                    <th scope="col">Message</th>
                                    <th scope="col">Audience</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Published At</th>
                                    <th scope="col">Action</th>

                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($announcements as $announcement)
                                    <tr>
                                        <td class="d-flex justify-content-center">
                                            <span>
                                                <img src="{{ $announcement->banner_image }}" alt=""
                                                    style="width: 50px; height:50px: border-radius:10px">
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                title="{{ $announcement->title }}">{{ Str::limit($announcement->title, 30) }}</span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-sm show-body"
                                                data-body="{!! $announcement->body !!}">
                                                View
                                            </button>
                                        </td>
                                        <td>{{ ucfirst($announcement->audience) }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($announcement->status) }}-transparent">
                                                {{ $announcement->status }}
                                            </span>
                                        </td>
                                        <td>{{ $announcement->published_at ?? 'N/A' }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                    role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Actions
                                                </a>
                                                <ul class="dropdown-menu">
                                                    @php
                                                    $statuses = [
                                                        'Active' => [
                                                            'icon' => 'ri-check-line',
                                                            'class' => 'text-success',
                                                            'label' => 'Mark As Active',
                                                        ],
                                                        'Inactive' => [
                                                            'icon' => 'ri-close-line',
                                                            'class' => 'text-danger',
                                                            'label' => 'Mark As Inactive',
                                                        ],
                                                    ];
                                                @endphp

                                                    <li>
                                                        @if ($announcement->status !== $Active)
                                                        <a class="dropdown-item"
                                                        href="{{ route('admin.announcements.edit', $announcement->id) }}">
                                                        <i class="ri-edit-2-line"></i> | Edit
                                                    </a>
                                                        @endif
                                                    </li>
                                                   
                                                    @foreach ($statuses as $status => $details)
                                                        <li>
                                                            <form
                                                                id="updateStatus_{{ $status }}_{{ $announcement->id }}"
                                                                action="{{ route('admin.announcements.update-status', $announcement->id) }}"
                                                                method="POST"
                                                                onsubmit="return confirm('Are you sure you want to {{ strtolower($details['label']) }}?')">
                                                                @csrf
                                                                <input type="hidden" name="status"
                                                                    value="{{ $status }}">
                                                                <a class="dropdown-item {{ $details['class'] }}"
                                                                    onclick="event.preventDefault(); document.getElementById('updateStatus_{{ $status }}_{{ $announcement->id }}').submit()"
                                                                    href="#">
                                                                    <i class="{{ $details['icon'] }}"></i> |
                                                                    {{ $details['label'] }}
                                                                </a>
                                                            </form>
                                                        </li>
                                                    @endforeach


                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#"
                                                            onclick="confirmDeletion({{ $announcement->id }})">
                                                            <i class="ri-delete-bin-line"></i> | Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Hidden Delete Form -->
                                    <form id="deleteannouncementForm_{{ $announcement->id }}"
                                        action="{{ route('admin.announcements.destroy', $announcement->id) }}"
                                        method="POST" style="display: none;">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <img class="no-data-image"
                                                src="{{ asset('admin_assets/images/empty/no-data-concept-illustration.jpg') }}"
                                                alt="No data available">
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
                            {{ $announcements->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" role="dialog"
        aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this announcement?
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Body content will be dynamically inserted here -->
                <div id="modalBodyContent"></div>
            </div>
        </div>
    </div>
</div>
    <script>
        function confirmDeletion(announcementId) {
            // Show the modal
            $('#deleteConfirmationModal').modal('show');

            // Set the form action to the correct announcement ID when the Delete button is clicked
            $('#confirmDeleteBtn').off('click').on('click', function() {
                var form = document.getElementById('deleteannouncementForm_' + announcementId);
                if (form) {
                    form.submit();
                } else {
                    console.error('Delete form not found for announcement ID: ' + announcementId);
                }
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.show-body').forEach(function(button) {
                button.addEventListener('click', function() {
                    // Get the body content from the data-body attribute
                    const bodyContent = this.getAttribute('data-body');
                    // Insert the body content as HTML into the modal
                    document.getElementById('modalBodyContent').innerHTML = bodyContent;
                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('bodyModal'));
                    modal.show();
                });
            });
        });
    </script>
@endsection
