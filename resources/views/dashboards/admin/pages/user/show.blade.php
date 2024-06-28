@extends('dashboards.admin.layout.app')
@section('content')
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">User Information</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Users</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Information</li>
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
                                    <img src="{{ $user->avatarUrl("white") }}" alt="">
                                </span>
                            </div>
                            <div class="flex-fill main-profile-info">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="fw-semibold mb-1 text-fixed-white">{{ $user->username }}</h6>
                                    <button class="btn bg-white btn-outline-{{ pillClasses($user->status) }} btn-sm btn-wave">
                                        {{ $user->status }}</button>
                                </div>
                                <div class="d-flex mb-0">
                                    <div class="me-4">
                                        <p class="fw-bold fs-23 text-fixed-white text-shadow mb-0">{{ $user->posts?->count() }}</p>
                                        <p class="mb-0 fs-14 text-fixed-white">Posts</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 border-bottom border-block-end-dashed">
                            <p class="fs-15 mb-2 me-4 fw-semibold">Contact Information :</p>
                            <div class="text-muted">
                                <p class="mb-2">
                                    <span class="avatar avatar-sm avatar-rounded me-2 bg-light text-muted">
                                        <i class="ri-mail-line align-middle fs-14"></i>
                                    </span>
                                    Email: {{ $user->email ?? 'N/A' }}
                                </p>
                                {{-- <p class="mb-2">
                                    <span class="avatar avatar-sm avatar-rounded me-2 bg-light text-muted">
                                        <i class="ri-calendar-line align-middle fs-14"></i>
                                    </span>
                                    Birth Year: {{ $user->birth_year ?? 'N/A' }}
                                </p> --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-8 col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Additional Details
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3">
                            <form action="{{ route('admin.users.update', $user->id) }}" method="POST"> @csrf
                                @method('patch')
                                <div class="form-group mt-2">
                                    <label for="exampleInputPassword1">Username</label>
                                    <input type="text" class="form-control mt-2" name="username" id="exampleInputPassword1" value="{{ $user->username }}">
                                </div>
                                <div class="form-group mt-3">
                                    <label for="exampleInputPassword1">Email</label>
                                    <input type="email" disabled class="form-control mt-2" name="email" id="exampleInputPassword1" value="{{ $user->email }}">
                                </div>
                                <div class="form-group mt-3">
                                    <label for="avatar">Avatar</label>
                                    <select id="avatar" name="avatar_id" data-placeholder="Select Avatar" data-dynamic-select>
                                        @foreach ($avatars as $avatar)
                                            <option value="{{ $avatar->id }}" data-img="{{ $avatar->imageUrl() }}">{{ $avatar->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">Update</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--End::row-1 -->

    </div>
@endsection
