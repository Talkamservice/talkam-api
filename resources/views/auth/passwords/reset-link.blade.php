@extends('auth.layouts.app')

@section('content')
    <div class="card-body p-5 mt-4">
        <p class="h5 fw-semibold mb-2 text-center">Reset your password</p>
        <p class="mb-4 text-muted op-7 fw-normal text-center">{{ $email }}</p>
        <div class="mb-2">
            @include("general.notifications.flash_messages")
        </div>
        <form class="row gy-3" method="POST" action="{{ route('password.reset.submit') }}">
            @csrf
            <input type="hidden" name="email" value="{{ old('email', $email) }}">
            <input type="hidden" name="code" value="{{ old('code', $code) }}">
            <div class="col-xl-12">
                <label class="form-label text-default">New password</label>
                <input type="password" class="form-control form-control-l @error('password') is-invalid @enderror"
                    name="password" required autocomplete="new-password" autofocus>
                @error('password')
                    <div class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></div>
                @enderror
            </div>
            <div class="col-xl-12 mb-3">
                <label class="form-label text-default">Confirm new password</label>
                <input type="password" class="form-control form-control-l" name="password_confirmation" required autocomplete="new-password">
            </div>
            <div class="col-xl-12 d-grid mt-2">
                <button type="submit" class="btn btn-lg btn-primary">Reset password</button>
            </div>
        </form>
    </div>
@endsection
