@extends('auth.layouts.app')

@section('content')
    <div class="card custom-card">
        <div class="card-body p-5 mt-4">
            @include("general.notifications.flash_messages")
            <p class="h5 fw-semibold mb-2 text-center">Enter your Profile Information</p>
            <p class="mb-4 text-muted op-7 fw-normal text-center">Fill the form</p>
            <form class="row gy-3" method="POST" action="{{ route("web.admin.invite.complete-onboarding-submit", slugify($invite->source)) }}">
                @csrf
                <input type="hidden" name="invite_id" value="{{ $invite->id }}">
                <div class="col-xl-12">
                    <label for="signin-username" class="form-label text-default">Name</label>
                    <input type="text" class="form-control form-control-l @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" placeholder="Enter name" required autocomplete="name" autofocus>
                    @error('name')
                        <div class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </div>
                    @enderror
                </div>
                <div class="col-xl-12">
                    <label for="signin-password" class="form-label text-default">Password</label>
                    <input type="password" class="form-control form-control-l @error('name') is-invalid @enderror" name="password" value="{{ old('password') }}" placeholder="Enter password" required>
                    @error('password')
                        <div class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </div>
                    @enderror
                </div>
                <div class="col-xl-12 d-grid mt-4">
                    <button type="submit" class="btn btn-lg btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
@endsection
