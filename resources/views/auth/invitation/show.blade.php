@extends('auth.layouts.app')

@section('content')
    <div class="card custom-card">
        <div class="mb-2">
            @include('general.notifications.flash_messages')
        </div>
        <div class="card-body p-5">
            <div class="text-center text-md-center mb-4 mt-md-0">
                <span class="mb-3">
                    <img src="{{ asset('admin_assets/images/invitation/invitation.png') }}"
                        style="width: 200px; height:200px; object-fit:cover" class="img-fluid" alt="">
                </span>
                <p class="h5 fw-semibold mb-2 mt-3 text-center">You got an invite from <b>{{ $invite->inviterName() }}</p>
                <p class="mb-4 op-7 fw-normal text-center">You have been invited by {{ $invite->inviter->name }} to access
                    their TalkAM account.</p>
            </div>
            <div class="row mb-0">
                <div class="d-flex justify-content-center">
                    <form method="POST" action="{{ route('web.admin.invite.response', slugify($invite->source)) }}">
                        @csrf
                        <input type="hidden" name="invite_id" value="{{ $invite->id }}">
                        <input type="hidden" name="response" value="accept">
                        <button type="submit"
                            class="btn btn-brand-02 btn-success d-inline-flex align-items-center text-white">
                            Accept
                        </button>
                    </form>
                    <div class="m-3"></div>
                    <form method="POST" action="{{ route('web.admin.invite.response', slugify($invite->source)) }}">
                        @csrf
                        <input type="hidden" name="invite_id" value="{{ $invite->id }}">
                        <input type="hidden" name="response" value="decline">
                        <button class="btn btn-danger d-inline-flex align-items-center mg-l-5">
                            Decline
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
