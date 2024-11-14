@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <h1 class="page-title fw-semibold fs-18 mb-0">Activity Logs</h1>
            </div>
            <div class="ms-md-1 ms-0 d-flex align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Activity Logs</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Start::row-1 -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="table-responsive" style="min-height: 250px">
                        @if ($activity_logs->isNotEmpty())
                            <table class="table text-nowrap table-hover border table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">S/N</th>
                                        <th scope="col">Admin</th>
                                        <th scope="col">Title</th>
                                        <th scope="col">Channel</th>
                                        <th scope="col">Model</th>
                                        <th scope="col">Source</th>
                                        <th scope="col">Event</th>
                                        <th scope="col">Description</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Activity</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($activity_logs as $log)
                                        <tr>
                                            <td>{{ $sn++ }}</td>
                                            <td>{{ $log->user->email ?? 'N/A' }}</td>
                                            <td>{{ $log->title }}</td>
                                            <td>{{ $log->channel }}</td>
                                            <td>{{ $log->model }}</td>
                                            <td>{{ $log->source }}</td>
                                            <td>{{ $log->event }}</td>
                                            <td>{{ $log->description }}</td>
                                            <td>{{ $log->type }}</td>
                                            <td>{{ Str::limit($log->activity, 50) }}</td>
                                            <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <div class="dropdown">
                                                    <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Actions
                                                    </a>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="openDeleteModal('{{ route('admin.activity-logs.destroy', $log->id) }}')"
                                                                data-bs-toggle="tooltip" title="Delete this Log">
                                                                <i class="ri-delete-bin-line"></i> | Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Hidden Delete Form -->
                                        <form id="deleteLogForm_{{ $log->id }}"
                                            action="#"
                                            method="POST" style="display: none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            @include('general.components.no_content')
                        @endif
                    </div>
                </div>
                <div class="card-footer">
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            Showing {{$activity_logs->firstItem() }} to {{ $activity_logs->lastItem() }} of {{ $activity_logs->total() }} entries
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                {{ $activity_logs->links('pagination::bootstrap-4') }}
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('dashboards.admin.pages.delete-modal')
@endsection
