@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Waitlist</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Waitlist</a></li>
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
                <div class="card-header d-flex justify-content-end">
                    <a href="{{ route('admin.waitlists.export') }}" class="btn btn-sm btn-success p-2">
                        <i class="bx bx-export mr-2"></i> Export
                    </a>
                </div>
                
                
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($waitlists as $waitlist)
                                    <tr>
                                        <td>{{ $waitlist->name }}</td>
                                        <td>{{ $waitlist->email }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($waitlist->status) }}-transparent">
                                                {{ $waitlist->status }}
                                            </span>
                                        </td>
                                        <td>{{ $waitlist->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                    role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Show Actions">
                                                    Action
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#"
                                                            onclick="openDeleteModal('{{ route('admin.waitlists.destroy', $waitlist->id) }}')"
                                                            data-bs-toggle="tooltip" title="Delete this waitlist">
                                                            <i class="ri-delete-bin-line"></i> | Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    @include(
                                        'dashboards.admin.pages.bulk-messages.modal.notification_content_modal',
                                        [
                                            'modalKey' => "waitlistContent_$waitlist->id",
                                            'modalContent' => $waitlist->content,
                                        ]
                                    )
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <div class="alert alert-info">
                                                No record found
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            Showing {{ $waitlists->firstItem() }} to {{ $waitlists->lastItem() }} of
                            {{ $waitlists->total() }} entries
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                {{ $waitlists->links('pagination::bootstrap-4') }}
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

    </div>
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
