@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Comment Report</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#">Comment Report</a></li>
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
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Commented User</th>
                                    <th scope="col">Comment</th>
                                    <th scope="col">Attachment</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($comment_report_lists->groupBy('comment_id') as $comment_id => $comment_reports)
                                    @php
                                        $first_report = $comment_reports->first();
                                    @endphp
                                    <tr>
                                        <td>

                                            <a href="{{ route('admin.users.show', $first_report->comment->user->id) }}">
                                                <div class="d-flex align-items-center fw-semibold">
                                                    <span class="avatar avatar-sm me-2 avatar-rounded">
                                                        <img src="{{ $first_report->comment->user->avatarUrl() }}"
                                                            alt="img">
                                                    </span>{{ $first_report->comment->user->username }}
                                                </div>
                                            </a>

                                        </td>
                                        <td title="{{ $first_report->comment->comment }}">
                                            <a href="{{ url('https://web.talkam.prodevs.io/comment/' . $first_report->comment->id) }}" target="_blank" rel="noopener noreferrer">
                                                {{ Str::limit($first_report->comment->comment ?? 'N/A', 30) }}
                                            </a>
                                        </td>
                                        
                                        <td title="{{ $first_report->comment->attachment }}">
                                            {{ Str::limit($first_report->comment->attachment ?? 'N/A', 30) }}</td>
                                        <td>
                                            <span class="badge bg-{{ pillClasses($first_report->status) }}-transparent">
                                                {{ $first_report->status }}
                                            </span>
                                        </td>
                                        <td>{{ $first_report->created_at->format('Y-m-d h:i A') }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                    role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item"
                                                            href="{{ route('admin.reports.comment.show', $first_report->id) }}">
                                                            <i class="ri-eye-line"></i> | View
                                                        </a>
                                                    </li>


                                                    {{-- <li>
                                                    <form id="deleteUser_{{ $first_report->id }}" action="{{ route('admin.reports.comment.delete', $first_report->id) }}" method="POST" onsubmit="return confirm('Are you sure of this action?')">
                                                        @csrf
                                                        @method('delete')
                                                        <a class="dropdown-item text-danger" href="#" onclick="$('#deleteUser_{{ $first_report->id }}').submit()">
                                                            <i class="ri-delete-bin-line"></i> | Delete
                                                        </a>
                                                    </form>
                                                </li> --}}
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No record found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex align-items-center">
                        <div>
                            {{ $comment_report_lists->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection>
