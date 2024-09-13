@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Post Report Information</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.reports.post.lists') }}">Index</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Post Report Information</li>
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
                            @if ($post_report && $post_report->post)
                                <div class="d-sm-flex align-items-top p-4 border-bottom-0 main-profile-cover">
                                    <div>
                                        <span class="avatar avatar-xxl avatar-rounded">
                                            <img src="{{ $post_report->post->cover }}" alt="Post Cover">
                                        </span>
                                    </div>
                                    <div class="flex-fill main-profile-info">
                                        <div class="d-flex align-items-center justify-content-end">
                                            <button
                                                class="btn bg-white btn-outline-{{ pillClasses($post_report->status) }} btn-sm btn-wave">
                                                {{ $post_report->status }}
                                            </button>
                                        </div>
                                        <div class="d-flex mb-0">
                                            <div class="me-4">
                                                <p class="fw-bold fs-23 text-fixed-white text-shadow mb-0">
                                                    {{ $reasons_count }}</p>
                                                <p class="mb-0 fs-14 text-fixed-white">Reports</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-4 border-bottom border-block-end-dashed">
                                    <p class="fs-15 mb-2 me-4 fw-semibold">Post Information :</p>
                                    <div class="text-muted">
                                        @if ($post_report->post->type == 'Poll')
                                            <p class="mb-2">
                                                <b>Title:</b> {{ $post_report->post->title ?? 'N/A' }}
                                            </p>
                                            <p class="mb-2">
                                                <b>Poll Options:</b>
                                                @if (isset($polls) && $polls->isNotEmpty())
                                                    @foreach ($polls as $pollResource)
                                                        @php
                                                            $poll = $pollResource->toArray(request());
                                                        @endphp
                                                        @if ($poll['type'] === 'Image')
                                                            <div>
                                                                <img src="{{ $poll['option'] }}" alt="Poll Image"
                                                                    style="max-width: 100%; height: auto;">
                                                                <p>{{ $poll['percentage'] ?? '0' }}%</p>
                                                            </div>
                                                        @elseif ($poll['type'] === 'Text')
                                                            <p>{{ $poll['option'] }} ({{ $poll['percentage'] ?? '0' }}%)</p>
                                                        @else
                                                            <p>Unknown poll type</p>
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <p>No poll options available.</p>
                                                @endif
                                            </p>
                                        @elseif ($post_report->post->type == 'Text')
                                            <p class="mb-2">
                                                <b>Title:</b> {{ $post_report->post->title ?? 'N/A' }}
                                            </p>
                                            <p class="mb-2">
                                                <b>Body:</b> {{ $post_report->post->body ?? 'N/A' }}
                                            </p>
                                        @elseif ($post_report->post->type == 'File')
                                            <p class="mb-2">
                                                <b>Title:</b> {{ $post_report->post->title ?? 'N/A' }}
                                            </p>
                                            <p class="mb-2">
                                                <b>Attachments:</b>
                                                @if ($post_report->post->attachments && $post_report->post->attachments->isNotEmpty())
                                                    @foreach ($post_report->post->attachments as $attachment)
                                                        <div>
                                                            <a href="{{ $attachment->url }}" target="_blank">
                                                                <img src="{{ $attachment->url }}" alt="Attachment Image"
                                                                    style="max-width: 100%; height: auto;">
                                                            </a>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <p>No attachments available.</p>
                                                @endif
                                            </p>
                                        @else
                                            <p class="mb-2">
                                                <b>Title:</b> {{ $post_report->post->title ?? 'N/A' }}
                                            </p>
                                            <p class="mb-2">
                                                <b>Description:</b> {{ $post_report->post->body ?? 'N/A' }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-danger" role="alert">
                                    Post report or post data not found.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-8 col-xl-8">
                <div class="card custom-card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="dropdown ms-auto me-auto">
                            <a class="btn btn-outline-primary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                aria-expanded="false" data-bs-toggle="tooltip" data-bs-placement="top" title="Show Actions">
                                Action
                            </a>
                            @if ($post_report->post)
                                <ul class="dropdown-menu">
                                    <!-- Mark As Resolved (only show if not resolved) -->
                                    @if ($post_report->status !== 'Resolved')
                                        <li>
                                            <form id="deleteUser_{{ $post_report->id }}"
                                                action="{{ route('admin.reports.post.update-status', $post_report->id) }}" method="POST"
                                                onsubmit="return confirm('Are you sure of this action?')">
                                                @csrf
                                                <input type="hidden" name="action" value="Resolved">
                                                <a class="dropdown-item text-success"
                                                    onclick="event.preventDefault(); document.getElementById('deleteUser_{{$post_report->id }}').submit()"
                                                    href="#" data-bs-toggle="tooltip" data-bs-placement="right" title="Mark As Resolved">
                                                    <i class="ri-check-line"></i> | Mark As Resolved
                                                </a>
                                            </form>
                                        </li>
                                    @endif
                        
                                    <!-- Suspend/Activate User (only show if not resolved) -->
                                    @if ($post_report->status !== 'Resolved')
                                        <li>
                                            <form id="suspendUser_{{ $post_report->post->user->id }}"
                                                action="{{ route('admin.users.suspend', $post_report->post->user->id) }}" method="post"
                                                onsubmit="return confirm('Are you sure of this action?')">
                                                @csrf
                                                @if ($post_report->post->user->status == 'Active')
                                                    <input type="hidden" name="status" value="Inactive">
                                                    <a class="dropdown-item text-danger" href="#"
                                                        onclick="event.preventDefault(); document.getElementById('suspendUser_{{ $post_report->post->user->id }}').submit()"
                                                        data-bs-toggle="tooltip" data-bs-placement="right" title="Suspend User">
                                                        <i class="ri-close-line"></i> | Suspend User
                                                    </a>
                                                @else
                                                    <input type="hidden" name="status" value="Active">
                                                    <a class="dropdown-item text-success" href="#"
                                                        onclick="event.preventDefault(); document.getElementById('suspendUser_{{ $post_report->post->user->id }}').submit()"
                                                        data-bs-toggle="tooltip" data-bs-placement="right" title="Activate User">
                                                        <i class="ri-check-line"></i> | Activate User
                                                    </a>
                                                @endif
                                            </form>
                                        </li>
                        
                                        <!-- Strike User (only show if not resolved) -->
                                        <li>
                                            <form id="strikeUser_{{ $post_report->post->user->id }}"
                                                action="{{ route('admin.users.strike', $post_report->post->user->id) }}" method="post"
                                                onsubmit="return confirm('Are you sure of this action?')">
                                                @csrf
                                                <a class="dropdown-item text-warning" href="#"
                                                    onclick="event.preventDefault(); document.getElementById('strikeUser_{{ $post_report->post->user->id }}').submit()"
                                                    data-bs-toggle="tooltip" data-bs-placement="right" title="Issue a warning to the User">
                                                    <i class="ri-warning-line"></i> | Strike User
                                                </a>
                                            </form>
                                        </li>
                                    @endif
                        
                                    <!-- Delete Post -->
                                    <li>
                                        <form id="deletePost_{{ $post_report->id }}"
                                            action="{{ route('admin.reports.post.delete', $post_report->id) }}" method="POST"
                                            onsubmit="return confirm('Are you sure of this action?')">
                                            @csrf
                                            @method('delete')
                                            <a class="dropdown-item text-danger" href="#"
                                                onclick="event.preventDefault(); document.getElementById('deletePost_{{ $post_report->id }}').submit()"
                                                data-bs-toggle="tooltip" data-bs-placement="right" title="Delete Post">
                                                <i class="ri-delete-bin-line"></i> | Delete Post
                                            </a>
                                        </form>
                                    </li>
                                </ul>
                            @endif
                        </div>
                        
                        
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            @if ($post_report->post)
                                @if ($post_report_lists->isNotEmpty())
                                    <table class="table text-nowrap table-hover border table-bordered">
                                        <thead>
                                            <tr>
                                                <th scope="col">Reporter</th>
                                                <th scope="col">Reason</th>
                                                <th scope="col">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($post_report_lists as $report)
                                                <tr>
                                                    <td>
                                                        @if ($report->user)
                                                            <a href="{{ route('admin.users.show', $report->user_id) }}">
                                                                <div class="d-flex align-items-center fw-semibold">
                                                                    <span class="avatar avatar-sm me-2 avatar-rounded">
                                                                        <img src="{{ $report->user->avatarUrl() }}"
                                                                            alt="img">
                                                                    </span>{{ $report->user->full_name }}
                                                                </div>
                                                            </a>
                                                        @else
                                                            <p>User not found</p>
                                                        @endif
                                                    </td>
                                                    <td>{{ $report->reason }}</td>
                                                    <td>{{ $report->created_at->format('Y-m-d h:i A') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <p class="text-center">No records found for this post.</p>
                                @endif
                            @else
                                <p class="text-center">The reported post is not available.</p>
                            @endif
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
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});

    </script>
@endsection
