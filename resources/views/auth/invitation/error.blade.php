@extends('auth.layouts.app')

@section('content')
<div class="card custom-card">
    <div class="card-body p-5">
        <div class="text-center text-md-center mb-4 mt-md-0">
            <span class="mb-3">
                <img src="{{ asset('admin_assets/images/invitation/invitation_error.png') }}" style="width: 200px; height:200px; object-fit:cover" class="img-fluid" alt="">
            </span>
            <p class="h5 fw-semibold mb-2 mt-3 text-center">{{ $title }}</p>
            <p class="mb-4 op-7 fw-normal text-center">{{ $message }}</p>
        </div>
        <div class="row mb-0">
            <div class="d-flex justify-content-center">
                <a class="btn btn-gray-800" href="{{ route('login') }}">
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
