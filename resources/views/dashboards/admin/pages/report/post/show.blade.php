@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

      <!-- Page Header -->
      <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
        <h1 class="page-title fw-semibold fs-18 mb-0">Post Report Information</h1>
        <div class="ms-md-1 ms-0">
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.reports.post.lists') }}">List</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Post Report Information</li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header Close -->

        <!-- Start::row-1 -->
        <div class="row">
            <div class="col-xxl-4 col-xl-12">
                <div class="card custom-card overflow-hidden">
                    <div class="card-body p-0">
                        <div class="d-sm-flex align-items-top p-4 border-bottom-0 main-profile-cover">
                          
                           
                        </div>
                      
                    </div>
                </div>
            </div>
            <div class="col-xxl-8 col-xl-12">
                <div class="card custom-card">
                    
                    <div class="card-body p-4">
                        <div class="form-group mt-2">
                            <label for="postTitle">Post Title</label>
                            <input type="text" class="form-control mt-2" id="postTitle" value="{{ $post_report->post->title }}" disabled>
                        </div>
                        <div class="form-group mt-3">
                            <label for="reasonForReport">Reason For Report</label>
                            <textarea class="form-control mt-2" id="reasonForReport" disabled>{{ $post_report->reason }}</textarea>
                        </div>
                        <div class="mt-4 d-flex justify-content-end">
                            <div class="dropdown">
                                <a class="btn btn-outline-primary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Action
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('admin.users.index', $post_report->user->id) }}?highlight_user_id={{ $post_report->user->id }}">
                                            <i class="ri-eye-line"></i> | View User
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('status-form-{{ $post_report->id }}-approved').submit();">
                                            <i class="ri-check-line"></i> | Approve
                                        </a>
                                        <form id="status-form-{{ $post_report->id }}-approved" action="{{ route('admin.reports.post.update-status', $post_report->id) }}" method="POST" style="display: none;">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="status" value="Approved">
                                        </form>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('status-form-{{ $post_report->id }}-suspended').submit();">
                                            <i class="ri-close-line"></i> | Suspend
                                        </a>
                                        <form id="status-form-{{ $post_report->id }}-suspended" action="{{ route('admin.reports.post.update-status', $post_report->id) }}" method="POST" style="display: none;">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="status" value="Suspended">
                                        </form>
                                    </li>
                                    <li>
                                        <form id="deleteUser_{{ $post_report->id }}" action="{{ route('admin.reports.post.delete', $post_report->id) }}" method="POST" onsubmit="return confirm('Are you sure of this action?')">
                                            @csrf
                                            @method('DELETE')
                                            <a class="dropdown-item text-danger" onclick="event.preventDefault(); document.getElementById('deleteUser_{{ $post_report->id }}').submit()" href="#">
                                                <i class="ri-delete-bin-line"></i> | Delete
                                            </a>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--End::row-1 -->
    </div>
@endsection





{{-- @extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">

       

        <!-- Start::row-1 -->
        <div class="row justify-content-center">
            <div class="col-xxl-8 col-xl-10">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Post Report Details
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mt-2">
                            <label for="postTitle">Post Title</label>
                            <input type="text" class="form-control mt-2" id="postTitle" value="{{ $post_report->post->title }}" disabled>
                        </div>
                        <div class="form-group mt-3">
                            <label for="reasonForReport">Reason For Report</label>
                            <textarea class="form-control mt-2" id="reasonForReport" disabled>{{ $post_report->reason }}</textarea>
                        </div>
                        <div class="mt-4 d-flex justify-content-end">
                            <div class="dropdown">
                                <a class="btn btn-outline-primary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Action
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('admin.users.index', $post_report->user->id) }}?highlight_user_id={{ $post_report->user->id }}">
                                            <i class="ri-eye-line"></i> | View User
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('status-form-{{ $post_report->id }}-approved').submit();">
                                            <i class="ri-check-line"></i> | Approve
                                        </a>
                                        <form id="status-form-{{ $post_report->id }}-approved" action="{{ route('admin.reports.post.update-status', $post_report->id) }}" method="POST" style="display: none;">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="status" value="Approved">
                                        </form>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('status-form-{{ $post_report->id }}-suspended').submit();">
                                            <i class="ri-close-line"></i> | Suspend
                                        </a>
                                        <form id="status-form-{{ $post_report->id }}-suspended" action="{{ route('admin.reports.post.update-status', $post_report->id) }}" method="POST" style="display: none;">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="status" value="Suspended">
                                        </form>
                                    </li>
                                    <li>
                                        <form id="deleteUser_{{ $post_report->id }}" action="{{ route('admin.reports.post.delete', $post_report->id) }}" method="POST" onsubmit="return confirm('Are you sure of this action?')">
                                            @csrf
                                            @method('DELETE')
                                            <a class="dropdown-item text-danger" onclick="event.preventDefault(); document.getElementById('deleteUser_{{ $post_report->id }}').submit()" href="#">
                                                <i class="ri-delete-bin-line"></i> | Delete
                                            </a>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--End::row-1 -->
    </div>
@endsection --}}
