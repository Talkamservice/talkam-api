@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Post Report</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        {{-- <li class="breadcrumb-item"><a href="#">Post Report</a></li> --}}
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
                                    @if ($first_report && $first_report->post && $first_report->user)
                                        <tr>
                                            <td>
                                                <a class="text-primary"
                                                    href="{{ route('admin.users.show', $first_report->user_id) }}">
                                                    <div class="d-flex align-items-center fw-semibold">
                                                        <span class="avatar avatar-sm me-2 avatar-rounded">
                                                            <img src="{{ $first_report->user->avatarUrl() }}"
                                                                alt="img">
                                                        </span>{{ $first_report->post->user->username }}
                                                    </div>
                                                </a>
                                            </td>

                                            <td>
                                                <a class="text-primary"
                                                    href="{{ url('https://web.talkam.prodevs.io/comment/' . $first_report->post->id) }}"
                                                    target="_blank" rel="noopener noreferrer">
                                                    {{ Str::limit($first_report->post->title ?? 'N/A', 30) }}
                                                </a>
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
                                                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Action
                                                    </a>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item"
                                                                href="{{ route('admin.reports.post.show', $first_report->id) }}">
                                                                <i class="ri-eye-line"></i> | View
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <form id="deleteUser_{{ $first_report->id }}"
                                                                action="{{ route('admin.reports.post.update-status', $first_report->id) }}"
                                                                method="POST"
                                                                onsubmit="return confirm('Are you sure of this action?')">
                                                                @csrf
                                                                <input type="hidden" name="action" value="Resolved">
                                                                <a class="dropdown-item text-success"
                                                                    onclick="event.preventDefault(); document.getElementById('updateStatus_{{ $first_report->id }}').submit()"
                                                                    href="#">
                                                                    <i class="ri-check-line"></i> | Mark As Resolved
                                                                </a>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form id="suspendUser_{{ $first_report->post->user->id }}"
                                                                action="{{ route('admin.users.suspend', $first_report->post->user->id) }}"
                                                                method="post"
                                                                onsubmit="return confirm('Are you sure of this action?')">
                                                                @csrf
                                                                @if ($first_report->post->user->status === 'Active')
                                                                    <input type="hidden" name="status" value="Inactive">
                                                                    <a class="dropdown-item text-danger" href="#"
                                                                        onclick="$('#suspendUser_{{ $first_report->post->user->id }}').submit()"><i
                                                                            class="ri-close-line"></i> | Suspend User</a>
                                                                @endif

                                                                @if ($first_report->post->user->status === 'Inactive')
                                                                    <input type="hidden" name="status" value="Active">
                                                                    <a class="dropdown-item text-success" href="#"
                                                                        onclick="$('#suspendUser_{{ $first_report->post->user->id }}').submit()"><i
                                                                            class="ri-check-line"></i> | Activate User</a>
                                                                @endif
                                                            </form>
                                                        </li>

                                                        <li>
                                                            <form id="strikeUser_{{ $first_report->post->user->id }}"
                                                                action="{{ route('admin.users.strike', $first_report->post->user->id) }}"
                                                                method="post"
                                                                onsubmit="return confirm('Are you sure of this action?')">
                                                                @csrf
                                                                <a class="dropdown-item text-warning" href="#"
                                                                    onclick="$('#strikeUser_{{ $first_report->post->user->id }}').submit()"><svg
                                                                        xmlns="http://www.w3.org/2000/svg"
                                                                        viewBox="0 0 24 24" width="13" height="13"
                                                                        fill="currentColor">
                                                                        <path
                                                                            d="M12.5002 2C12.2241 2 12.0002 2.22386 12.0002 2.5V12H10.0002V4.5C10.0002 4.22386 9.77634 4 9.5002 4C9.22405 4 9.0002 4.22386 9.0002 4.5V14C8.64653 14 7.00024 14 7.00024 14C6.61911 12.3792 5.64236 11.4407 4.5954 11.3216C4.87926 12.0664 5.36117 13.2592 6.16634 15.0995C7.02511 17.0622 7.89128 18.5218 9.00374 19.4986C10.0783 20.442 11.4586 21 13.5002 21C16.5378 21 19.0002 18.5377 19.0002 15.5002V7C19.0002 6.72386 18.7763 6.5 18.5002 6.5C18.2241 6.5 18.0002 6.72386 18.0002 7V12H16.0002V4C16.0002 3.72386 15.7763 3.5 15.5002 3.5C15.2241 3.5 15.0002 3.72386 15.0002 4V12H13.0002V2.5C13.0002 2.22386 12.7763 2 12.5002 2ZM21.0002 15.5002C21.0002 19.6424 17.6423 23 13.5002 23C11.0417 23 9.17214 22.308 7.68416 21.0015C6.23411 19.7283 5.22528 17.9381 4.33405 15.9012C3.40393 13.7753 2.89004 12.4804 2.60991 11.7235C2.25318 10.7597 2.74616 9.41212 4.08583 9.31846C5.24076 9.23771 6.22061 9.61249 7.0002 10.2587V4.5C7.0002 3.11929 8.11949 2 9.5002 2C9.68522 2 9.86554 2.0201 10.0391 2.05823C10.2477 0.888227 11.2702 0 12.5002 0C13.5602 0 14.4661 0.659694 14.8298 1.59091C15.0431 1.53167 15.268 1.5 15.5002 1.5C16.8809 1.5 18.0002 2.61929 18.0002 4V4.55001C18.1618 4.51722 18.329 4.5 18.5002 4.5C19.8809 4.5 21.0002 5.61929 21.0002 7V15.5002Z">
                                                                        </path>
                                                                    </svg> | Strike user</a>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form id="deletePost_{{ $first_report->post->id }}"
                                                                action="{{ route('admin.reports.post.delete', $first_report->post->id) }}"
                                                                method="POST"
                                                                onsubmit="return confirm('Are you sure of this action?')">
                                                                @csrf
                                                                @method('delete')
                                                                <a class="dropdown-item text-danger" href="#"
                                                                    onclick="$('#deletePost_{{ $first_report->post->id }}').submit()">
                                                                    <i class="ri-delete-bin-line"></i> | Delete Post
                                                                </a>
                                                            </form>
                                                        </li>



                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td colspan="5" class="text-center"><img class="no-data-image"
                                                    src="{{ asset('admin_assets/images/empty/no-data-concept-illustration.jpg') }}"
                                                    alt=""></td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No record found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
