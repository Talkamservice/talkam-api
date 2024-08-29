@extends('dashboards.admin.layout.app')

@section('content')
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-name fw-semibold fs-18 mb-0">{{ isset($announcement) ? 'Edit' : 'Create' }} Announcement</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.announcements.index') }}">Announcements</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            {{ isset($announcement) ? 'Edit' : 'Create' }}
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- Start:: row-1 -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <form
                            action="{{ isset($announcement) ? route('admin.announcements.update', $announcement->id) : route('admin.announcements.store') }}"
                            method="POST" enctype="multipart/form-data">
                            @csrf
                            @isset($announcement)
                                @method('patch')
                            @endisset

                            <div class="gy-4 mb-4">
                                <!-- Title Field -->
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="title" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Title</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="text" class="form-control" name="title" id="title"
                                            value="{{ old('title') ?? ($announcement->title ?? '') }}"
                                            placeholder="Enter title">
                                    </div>
                                </div>

                                <!-- Body Field -->
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="body" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Body</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <textarea name="body" class="form-control" id="body" cols="30" rows="5">{{ old('body') ?? ($announcement->body ?? '') }}</textarea>
                                    </div>
                                </div>

                                {{-- <!-- Audience Field -->
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="audience"
                                        class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Audience</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="audience" id="audience" class="form-control">
                                            <option value="" disabled selected>Select Audience</option>
                                            <option value="users"
                                                {{ (old('audience') ?? ($announcement->audience ?? '')) == 'users' ? 'selected' : '' }}>
                                                User
                                            </option>
                                            <option value="public"
                                                {{ (old('audience') ?? ($announcement->audience ?? '')) == 'public' ? 'selected' : '' }}>
                                                Public
                                            </option>
                                        </select>
                                    </div>
                                </div> --}}

                                <!-- Banner Image Field -->
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="banner_image"
                                        class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Banner Image</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="file" class="form-control" name="banner_image" id="banner_image">
                                        @if(isset($announcement->banner_image))
                                            <img src="{{ asset('storage/' . $announcement->banner_image) }}" alt="Banner Image" class="img-fluid mt-2">
                                        @endif
                                    </div>
                                </div>

                                <!-- Status Field -->
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="status" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Status</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <select name="status" id="status" class="form-control">
                                            <option value="" disabled selected>Select Status</option>
                                            @foreach ($statusOptions as $statusOption)
                                                <option value="{{ $statusOption }}"
                                                    {{ (old('status') ?? ($announcement->status ?? '')) == $statusOption ? 'selected' : '' }}>
                                                    {{ $statusOption }}
                                                </option>
                                            @endforeach
                                        </select>
                                        
                                    </div>
                                </div>

                                <!-- Published At Field -->
                                <div class="row col-xl-9 col-sm-12 mb-3">
                                    <label for="published_at" class="form-label col-xl-2 col-lg-2 col-md-2 col-sm-2">Publish Date</label>
                                    <div class="col-xl-8 col-lg-8 col-md-8 col-sm-12">
                                        <input type="datetime-local" class="form-control" name="published_at" id="published_at"
                                            value="{{ old('published_at') ?? (isset($announcement) && $announcement->published_at ? $announcement->published_at->format('Y-m-d\TH:i') : '') }}">
                                    </div>
                                </div>
                                
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-success">Submit</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer d-none border-top-0"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Select2 JavaScript and CSS for better multi-select functionality -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#audience').select2({
                placeholder: "Select Audience",
                allowClear: true
            });
        });
    </script>

@section('script')
    <script>
        classicEditorInit('#body');
    </script>
@endsection
@endsection
