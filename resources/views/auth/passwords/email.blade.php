@extends('auth.layouts.app')

@section('content')
    <div class="card custom-card">
        <div class="card-body p-5">
            @include('general.notifications.flash_messages')
            <p class="h5 fw-semibold mb-2 text-center">Reset Password</p>
            <form class="row gy-3" method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="col-xl-12">
                    <label for="signin-username" class="form-label text-default">Email</label>
                    <input type="text" class="form-control form-control-l @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                    @error('email')
                        <div class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </div>
                    @enderror
                </div>
                <div class="col-xl-12 d-grid mt-4">
                    <button type="submit" class="btn btn-lg sign-in-btn">Send Password Reset Link</button>
                </div>
            </form>
        </div>
    </div>
@endsection
