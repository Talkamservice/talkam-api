@extends('auth.layouts.app')

@section('content')
    <div class="card custom-card">
        <div class="mb-2">
            @include("general.notifications.flash_messages")
        </div>
        <div class="card-body p-5 mt-4">
            <p class="h5 fw-semibold mb-2 text-center">Account Deactivation Request</p>
            <p class="mb-4 text-muted op-7 fw-normal text-center">Fill the form</p>
            <form class="row gy-3" method="POST" action="{{ route('web.deactivate-account.submit') }}">
                @csrf
                <div class="col-xl-12">
                    <label for="signin-username" class="form-label text-default">Email</label>
                    <input type="text" class="form-control form-control-l @error('email') is-invalid @enderror"
                        name="email" value="{{ old('email') }}" placeholder="johndoe@mail.com" required autocomplete="email" autofocus>
                    @error('email')
                        <div class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </div>
                    @enderror
                </div>
                <div class="col-xl-12 mb-3">
                    <label for="signin-username" class="form-label text-default">Reason</label>
                    <textarea class="form-control form-control-l" name="reason" required id="" cols="30" rows="5"></textarea>
                    @error('reason')
                        <div class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </div>
                    @enderror
                </div>
                <div class="col-xl-12 d-grid mt-2">
                    <button type="submit" class="btn btn-lg btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
@endsection
