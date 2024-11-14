@extends('auth.layouts.app')

@section('content')
    <div class="card custom-card">
        <div class="card-body p-5">
            @include('general.notifications.flash_messages')
            <p class="h5 fw-semibold mb-2 text-center">Reset Password</p>
            <form class="row gy-3" method="POST" action="{{ route('password.update') }}">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ request()->email }}">

                @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="col-xl-12">
                    <label for="password" class="form-label text-default">Password</label>
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">

                    @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="col-xl-12">
                    <label for="password-confirm" class="form-label text-default">Confirm Password</label>
                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">

                    @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="col-xl-12 d-grid mt-4">
                    <button type="submit" class="btn btn-lg sign-in-btn">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
@endsection
