@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Post Report</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item active" aria-current="page">Index</li>
                    </ol>
                </nav>
                <div class=""></div>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Start::row-1 -->
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header">
                    <form action="{{ url()->current() }}" method="get" class="row g-3">
                        <div class="col-12 col-md-5 col-xl-5 col-lg-5">
                            <div class="form-group">
                                <label for="search">Search (status or reason)</label>
                                <input class="form-control" type="text" value="{{ request()->search }}"
                                    placeholder="Search..." name="search">
                            </div>
                        </div>

                        <div class="col-12 col-md-5 col-xl-5 col-lg-5">
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input class="form-control" type="date" value="{{ request()->date }}" name="date">
                            </div>
                        </div>

                        <div class="col-12 col-md-2 col-xl-2 col-lg-2 pt-4">
                            <div class="form-group d-flex align-items-end">
                                <button class="btn btn-sm btn-success w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card-body">
                    <div class="table-responsive" style="min-height: 250px">
                        <table class="table text-nowrap table-hover border table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">Post Author</th>
                                    <th scope="col">Post</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($post_report_lists->groupBy('post_id') as $post_id => $post_reports)
                                    @php
                                        $first_report = $post_reports->first();
                                    @endphp
                                    @if ($first_report->post)
                                    @php
                                    $hasRecords = true;
                                @endphp
                                        <tr>
                                            <td>
                                                <a class="text-primary"
                                                    href="{{ route('admin.users.show', $first_report->user_id) }}">
                                                    <div class="d-flex align-items-center fw-semibold">
                                                        <span class="avatar avatar-sm me-2 avatar-rounded">
                                                            <img src="{{ $first_report->user->avatarUrl() }}"
                                                                alt="img">
                                                        </span>{{ $first_report->post->user->username ?? 'N/A' }}
                                                    </div>
                                                </a>
                                            </td>

                                            <td>
                                                @if ($first_report->post)
                                                    <a class="text-primary"
                                                        href="{{ url('https://web.talkam.prodevs.io/comment/' . $first_report->post->id) }}"
                                                        target="_blank" rel="noopener noreferrer">
                                                        {{ Str::limit($first_report->post->title, 30) }}
                                                    </a>
                                                @else
                                                    <span>N/A</span>
                                                @endif
                                            </td>

                                            <td>
                                                <span class="badge bg-{{ pillClasses($first_report->status) }}-transparent">
                                                    {{ $first_report->status }}
                                                </span>
                                            </td>
                                            <td>{{ $first_report->created_at->format('Y-m-d h:i A') }}</td>
                                            <td>
                                                <div class="dropdown">
                                                    <a class="btn btn-outline-primary dropdown-toggle" href="#"
                                                        role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="Show actions">
                                                        Action
                                                    </a>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item"
                                                                href="{{ route('admin.reports.post.show', $first_report->id) }}"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                title="View Report">
                                                                <i class="ri-eye-line"></i> | View
                                                            </a>
                                                        </li>

                                                        @if ($first_report->status !== 'Resolved')
                                                            <li>
                                                                <form id="updateStatus_{{ $first_report->id }}"
                                                                    action="{{ route('admin.reports.post.update-status', $first_report->id) }}"
                                                                    method="POST"
                                                                    onsubmit="return confirm('Are you sure of this action?')">
                                                                    @csrf
                                                                    <input type="hidden" name="action" value="Resolved">
                                                                    <a class="dropdown-item text-success"
                                                                        onclick="event.preventDefault(); document.getElementById('updateStatus_{{ $first_report->id }}').submit()"
                                                                        href="#" data-bs-toggle="tooltip"
                                                                        data-bs-placement="right" title="Mark As Resolved">
                                                                        <i class="ri-check-line"></i> | Mark As Resolved
                                                                    </a>
                                                                </form>
                                                            </li>
                                                        @endif

                                                        @if ($first_report->status !== 'Resolved' && $first_report->post && $first_report->post->user)
                                                            <li>
                                                                <form id="suspendUser_{{ $first_report->post->user->id }}"
                                                                    action="{{ route('admin.users.suspend', $first_report->post->user->id) }}"
                                                                    method="POST"
                                                                    onsubmit="return confirm('Are you sure of this action?')">
                                                                    @csrf
                                                                    @if ($first_report->post->user->status === 'Active')
                                                                        <input type="hidden" name="status"
                                                                            value="Inactive">
                                                                        <a class="dropdown-item text-danger"
                                                                            onclick="event.preventDefault(); document.getElementById('suspendUser_{{ $first_report->post->user->id }}').submit()"
                                                                            href="#" data-bs-toggle="tooltip"
                                                                            data-bs-placement="right" title="Suspend User">
                                                                            <i class="ri-close-line"></i> | Suspend User
                                                                        </a>
                                                                    @elseif ($first_report->post->user->status === 'Inactive')
                                                                        <input type="hidden" name="status" value="Active">
                                                                        <a class="dropdown-item text-success"
                                                                            onclick="event.preventDefault(); document.getElementById('suspendUser_{{ $first_report->post->user->id }}').submit()"
                                                                            href="#" data-bs-toggle="tooltip"
                                                                            data-bs-placement="right"
                                                                            title="Activate User">
                                                                            <i class="ri-check-line"></i> | Activate User
                                                                        </a>
                                                                    @endif
                                                                </form>
                                                            </li>

                                                            <li>
                                                                <form id="strikeUser_{{ $first_report->post->user->id }}"
                                                                    action="{{ route('admin.users.strike', $first_report->post->user->id) }}"
                                                                    method="POST"
                                                                    onsubmit="return confirm('Are you sure of this action?')">
                                                                    @csrf
                                                                    <a class="dropdown-item text-warning"
                                                                        onclick="event.preventDefault(); document.getElementById('strikeUser_{{ $first_report->post->user->id }}').submit()"
                                                                        href="#" data-bs-toggle="tooltip"
                                                                        data-bs-placement="right"
                                                                        title="Issue a warning to the User">
                                                                        <i class="ri-warning-line"></i> | Strike User
                                                                    </a>
                                                                </form>
                                                            </li>
                                                        @endif

                                                        <li>
                                                            <form id="deletePost_{{ $first_report->id }}"
                                                                action="{{ route('admin.reports.post.delete', $first_report->id) }}"
                                                                method="POST"
                                                                onsubmit="return confirm('Are you sure of this action?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <a class="dropdown-item text-danger"
                                                                    onclick="event.preventDefault(); document.getElementById('deletePost_{{ $first_report->id }}').submit()"
                                                                    href="#" data-bs-toggle="tooltip"
                                                                    data-bs-placement="right" title="Delete Post">
                                                                    <i class="ri-delete-bin-line"></i> | Delete Post
                                                                </a>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                   
                                    @endif
                                @empty
                               
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <div class="alert alert-info">
                                            No record found.
                                        </div>
                                    </td>
                                </tr>
                          
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- End::row-1 -->
    </div>
@endsection
