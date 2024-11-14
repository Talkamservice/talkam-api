@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Profile</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Profile</li>
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
                            <div>
                                <span class="avatar avatar-xxl avatar-rounded online me-3">
                                    <img src="{{ $user->avatarUrl() }}" alt="">
                                </span>
                            </div>
                            <div class="flex-fill main-profile-info">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="fw-semibold mb-1 text-fixed-white">{{ $user->name }}</h6>
                                </div>
                                <p class="mb-1 text-muted text-fixed-white op-7">{{ $user->role }}</p>
                            </div>
                        </div>
                        <div class="p-4 border-bottom border-block-end-dashed">
                            <p class="fs-15 mb-2 me-4 fw-semibold">Contact Information :</p>
                            <div class="text-muted">
                                <p class="mb-2">
                                    <span class="avatar avatar-sm avatar-rounded me-2 bg-light text-muted">
                                        <i class="ri-mail-line align-middle fs-14"></i>
                                    </span>
                                    {{ $user->email }}
                                </p>
                                <p class="mb-2">
                                    <span class="avatar avatar-sm avatar-rounded me-2 bg-light text-muted">
                                        <i class="ri-git-repository-private-line fs-14"></i>
                                    </span>
                                    {{ $user->role }}
                                </p>
                                <p class="mb-0">
                                    <span class="avatar avatar-sm avatar-rounded me-2 bg-light text-muted">
                                        <i class="ri-focus-2-fill align-middle fs-14"></i>
                                    </span>
                                    {{ $user->status }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-8 col-xl-12">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card custom-card">
                            <div class="card-body p-0">
                                <div class="p-3 border-bottom border-block-end-dashed d-flex align-items-center justify-content-between">
                                    <div>
                                        <ul class="nav nav-tabs mb-0 tab-style-6 justify-content-start" id="myTab" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active" id="posts-tab" data-bs-toggle="tab" data-bs-target="#posts-tab-pane" type="button" role="tab" aria-controls="posts-tab-pane" aria-selected="false"><i
                                                        class="ri-bill-line me-1 align-middle d-inline-block"></i>Password</button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <div class="tab-content" id="myTabContent">
                                        <div class="tab-pane show active fade p-0 border-0" id="posts-tab-pane" role="tabpanel" aria-labelledby="posts-tab" tabindex="0">
                                            <form action="{{ route("admin.profile.update-password") }}" method="POST"> @csrf
                                                <div class="form-group mt-2">
                                                    <label for="exampleInputPassword1">Old Password</label>
                                                    <input type="password" class="form-control mt-2" name="old_password" id="exampleInputPassword1" placeholder="Password">
                                                </div>
                                                <div class="form-group mt-3">
                                                    <label for="exampleInputPassword1">New Password</label>
                                                    <input type="password" class="form-control mt-2" name="new_password" id="exampleInputPassword1" placeholder="Password">
                                                </div>
                                                <div class="form-group mt-3">
                                                    <label for="exampleInputPassword1">Confirm Password</label>
                                                    <input type="password" class="form-control mt-2" name="password_confirmation" id="exampleInputPassword1" placeholder="Password">
                                                </div>
                                                <div class="mt-4">
                                                    <button type="submit" class="btn btn-primary">Submit</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
