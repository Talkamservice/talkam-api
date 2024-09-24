@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Feedbacks</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Feedbacks</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
                <div class="">
                </div>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Start::row-1 -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header d-flex justify-content-between">
                    <form action="{{ url()->current() }}" method="get" class="d-flex justify-content-between">
                        <div class="form-group me-2">
                            <label for="">Search(Type, Status, or Platform)</label>
                            <input class="form-control" type="text" placeholder="Search...." name="search">
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
                                    <th scope="col">Name</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Platform</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Content</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($feedbacks as $feedback)
                                    <tr>
                                        <td>{{ $feedback->name }}</td>
                                        <td>{{ $feedback->email }}</td>
                                        <td>{{ $feedback->platform }}</td>
                                        <td>{{ $feedback->feedback_type ?? "N/A" }}</td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                                data-bs-target="#feedbackContent_{{ $feedback->id }}">
                                                View
                                            </button>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($feedback->status) }}-transparent">
                                                {{ $feedback->status }}
                                            </span>
                                        </td>
                                        <td>{{ $feedback->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                    role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Show Actions">
                                                    Action
                                                </a>
                                                <ul class="dropdown-menu">
                                                    @if ($feedback->status !== 'Resolved')
                                                        <li>
                                                            <a class="dropdown-item text-success" href="#"
                                                                onclick="openActionsModal('resolve', '{{ route('admin.feedback.update-status', $feedback->id) }}')"
                                                                data-bs-toggle="tooltip"
                                                                title="Mark this report as resolved">
                                                                <i class="ri-check-line"></i> | Mark As Resolved
                                                            </a>
                                                        </li>
                                                    @endif
                                                    <li>
                                                        <a class="dropdown-item text-primary" href="#"
                                                            onclick="openModal('{{ $feedback->id }}')"
                                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                                            title="Delete Feedback">
                                                            <i class="ri-alert-line"></i> | Respond
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#"
                                                            onclick="openDeleteModal('{{ route('admin.feedbacks.destroy', $feedback->id) }}')"
                                                            data-bs-toggle="tooltip" title="Mark this report as resolved">
                                                            <i class="ri-delete-bin-line"></i> | Delete
                                                        </a>
                                                    </li>
                                            </div>
                                        </td>
                                    </tr>
                                    @include(
                                        'dashboards.admin.pages.bulk-messages.modal.notification_content_modal',
                                        [
                                            'modalKey' => "feedbackContent_$feedback->id",
                                            'modalContent' => $feedback->content,
                                        ]
                                    )
                                @empty
                                    <div class="alert alert-info text-center">
                                        No record found
                                    </div>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            Showing {{ $feedbacks->firstItem() }} to {{ $feedbacks->lastItem() }} of
                            {{ $feedbacks->total() }} entries
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                {{ $feedbacks->links('pagination::bootstrap-4') }}
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

    </div>
    @include('dashboards.admin.pages.feedback.respond-modal')
    @include('dashboards.admin.pages.feedback.actions-modal')
    @include('dashboards.admin.pages.delete-modal')

    <script>
        // Initialize Bootstrap tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        })
    </script>
@endsection
