@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Comment Report Information</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.reports.post.lists') }}">List</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Comment Report Information</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->

        <div class="row">
            <div class="col-xxl-4 col-xl-4">
                <div class="col-xxl-12 col-xl-12">
                    <div class="card custom-card overflow-hidden">
                        <div class="card-body p-0">
                            @if($comment_report)
                                <div class="d-sm-flex align-items-top p-4 border-bottom-0 main-profile-cover">
                                    <div>
                                        <span class="avatar avatar-xxl avatar-rounded online me-3">
                                            <img src="{{ asset($comment_report->comment->cover) }}" alt="Cover Image">
                                        </span>
                                    </div>
                                    <div class="flex-fill main-profile-info">
                                        <div class="d-flex align-items-center justify-content-end">
                                            <button
                                                class="btn bg-white btn-outline-{{ pillClasses($comment_report->status) }} btn-sm btn-wave">
                                                {{ $comment_report->status }}
                                            </button>
                                        </div>
                                        <div class="d-flex mb-0">
                                            <div class="me-4">
                                                <p class="fw-bold fs-23 text-fixed-white text-shadow mb-0">{{ $reasons_count ?? 0 }}</p>
                                                <p class="mb-0 fs-14 text-fixed-white">Reports</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-4 border-bottom border-block-end-dashed">
                                    <p class="fs-15 mb-2 me-4 fw-semibold">Comment Information :</p>
                                    <div class="text-muted">
                                        <p class="mb-2"><b>Name:</b> {{ $comment_report->comment->comment }}</p>
                                        <p class="mb-2">
                                            <b>Attachment:</b>
                                            @if ($comment_report->comment->attachment)
                                                <img src="{{ $comment_report->comment->attachment }}" alt="Comment Attachment" style="max-width: 100%; height: auto;">
                                            @else
                                                N/A
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @else
                                <p class="p-4">No Comment Report Information available.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-8 col-xl-8">
                <div class="card custom-card">
                    <div class="card-header d-flex justify-content-between">
                        <form action="{{ url()->current() }}" method="get" class="d-flex justify-content-between">
                            <div class="form-group me-2">
                                <label for="">Search</label>
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
                                        <th scope="col">Reporter</th>
                                        <th scope="col">Reason</th>
                                        <th scope="col">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($comment_report_lists as $report)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.users.show', $report->user_id) }}">
                                                    <div class="d-flex align-items-center fw-semibold">
                                                        <span class="avatar avatar-sm me-2 avatar-rounded">
                                                            <img src="{{ $report->user->avatarUrl() }}" alt="Avatar">
                                                        </span>{{ $report->post->user->username ?? 'Unknown' }}
                                                    </div>
                                                </a>
                                            </td>
                                            <td>{{ $report->reason ?? 'No reason provided' }}</td>
                                            <td>{{ $report->created_at ? $report->created_at->format('Y-m-d h:i A') : 'N/A' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center">No record found</td>
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
    </div>
@endsection
