@extends('auth.layouts.app')

@section('content')
    <div class="card-body p-5 reduce_card_body">
        @include('general.notifications.flash_messages')
        <p class="h5 fw-semibold mb-2 text-center">Login</p>
        <p class="mb-4 text-muted op-7 fw-normal text-center">Welcome back</p>
        <form class="row gy-3" method="POST" action="{{ route('login') }}">
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
            <div class="col-xl-12 mb-2">
                <label for="signin-password" class="form-label text-default d-block">Password<a href="{{ route('password.request') }}" class="float-end text-danger">Forgot password?</a></label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control form-control-lg" id="signin-password" placeholder="password" required>
                    @error('password')
                        <div class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </div>
                    @enderror
                    <button class="btn btn-light" type="button" onclick="createpassword('signin-password',this)" id="button-addon2"><i class="ri-eye-off-line align-middle"></i></button>
                </div>
                <div class="mt-2">
                    <div class="form-check">
                        <input class="form-check-input" name="remember" type="checkbox" {{ old('remember') ? 'checked' : '' }} id="defaultCheck1">
                        <label class="form-check-label text-muted fw-normal" for="defaultCheck1">
                            Remember password?
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-xl-12 d-grid mt-2">
                <button type="submit" class="btn btn-lg sign-in-btn">Sign In</button>
            </div>
        </form>
    </div>
@endsection

<!-- @section('script')
    <script></script>
@endsection -->
