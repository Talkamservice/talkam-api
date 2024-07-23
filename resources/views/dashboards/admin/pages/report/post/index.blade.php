@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Post Report</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Post Report</a></li>
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

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">User</th>
                                    <th scope="col">Post</th>
                                    <th scope="col">Reason</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($post_report_lists as $post_report_list)
                                    <tr>
                                        <td>{{ $post_report_list->user->full_name }}</td>
                                        <td>{{ str_limit($post_report_list->post->title, 50) }}</td>
                                        <td>{{ str_limit($post_report_list->reason, 60) }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($post_report_list->status) }}-transparent">
                                                {{ $post_report_list->status }}
                                            </span>
                                        </td>
                                        <td>{{ $post_report_list->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </a>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('admin.users.index', $post_report_list->user->id) }}?highlight_user_id={{ $post_report_list->user->id }}">
                                                            <i class="ri-eye-line"></i> | View
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('status-form-{{ $post_report_list->id }}-approved').submit();">
                                                            <i class="ri-check-line"></i> | Approve
                                                        </a>
                                                        <form id="status-form-{{ $post_report_list->id }}-approved" action="{{ route('admin.reports.post.update-status', $post_report_list->id) }}" method="POST" style="display: none;">
                                                            @csrf
                                                            @method('PUT')
                                                            <input type="hidden" name="status" value="Approved">
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('status-form-{{ $post_report_list->id }}-suspended').submit();">
                                                            <i class="ri-close-line"></i> | Suspend
                                                        </a>
                                                        <form id="status-form-{{ $post_report_list->id }}-suspended" action="{{ route('admin.reports.post.update-status', $post_report_list->id) }}" method="POST" style="display: none;">
                                                            @csrf
                                                            @method('PUT')
                                                            <input type="hidden" name="status" value="Suspended">
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form id="deleteUser_{{ $post_report_list->id }}" action="{{ route('admin.reports.post.delete', $post_report_list->id) }}" method="POST" onsubmit="return confirm('Are you sure of this action?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <a class="dropdown-item text-danger" onclick="event.preventDefault(); document.getElementById('deleteUser_{{ $post_report_list->id }}').submit()" href="#">
                                                                <i class="ri-delete-bin-line"></i> | Delete
                                                            </a>
                                                        </form>
                                                    </li>
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
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            {{ $post_report_lists->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  
@endsection
