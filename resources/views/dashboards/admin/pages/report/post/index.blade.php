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
                    <div class="table-responsive" style="min-height: 250px">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Post Author</th>
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
                                        <td>
                                            <a href="{{ route('admin.users.show', $post_report_list->user_id) }}">
                                                <div class="d-flex align-items-center fw-semibold">
                                                    <span class="avatar avatar-sm me-2 avatar-rounded">
                                                        <img src="{{ $post_report_list->user->avatarUrl() }}" alt="img">
                                                    </span>{{ $post_report_list->user->username }}
                                                </div>
                                            </a>
                                        </td>
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
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                    role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item"
                                                            href="{{ route('admin.reports.post.show', $post_report_list->id) }}">
                                                            <i class="ri-eye-line"></i> | View
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <form id="deleteUser_{{ $post_report_list->id }}" action="{{ route('admin.reports.post.delete', $post_report_list->id) }}" method="POST" onsubmit="return confirm('Are you sure of this action?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <a class="dropdown-item text-success" onclick="event.preventDefault(); document.getElementById('deleteUser_{{ $post_report_list->id }}').submit()" href="#">
                                                                <i class="ri-check-line"></i> | Mark As Resolved
                                                            </a>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form id="suspendUser_{{ $post_report_list->id }}" action="{{ route('admin.users.suspend', $post_report_list->id) }}" method="post" onsubmit="return confirm('Are you sure of this action?')"> @csrf
                                                            @if ($post_report_list->status == 'Active')
                                                                <input type="hidden" name="status" value="Inactive">
                                                                <a class="dropdown-item text-warning" href="#" onclick="$('#suspendUser_{{ $post_report_list->id }}').submit()"><i class="ri-close-line"></i> | Suspend </a>
                                                            @else
                                                                <input type="hidden" name="status" value="Active">
                                                                <a class="dropdown-item text-warning" href="#" onclick="$('#suspendUser_{{ $post_report_list->id }}').submit()"><i class="ri-check-line"></i> | Activate</a>
                                                            @endif
                                                        </form>
                                                    </li>
                                                    {{-- <li>
                                                        <form id="deleteUser_{{ $post_report_list->id }}" action="{{ route('admin.reports.post.delete', $post_report_list->id) }}" method="POST" onsubmit="return confirm('Are you sure of this action?')">
                                                            @csrf
                                                            @method('delete')
                                                            <a class="dropdown-item text-danger" href="#"
                                                                onclick="$('#deleteUser_{{ $post_report_list->id }}').submit()">
                                                                <i class="ri-delete-bin-line"></i> | Delete
                                                            </a>
                                                        </form>
                                                    </li> --}}
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
