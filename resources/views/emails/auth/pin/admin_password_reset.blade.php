@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hello {{ $user->getName() }},</p>
        <p class="detailCont-p">
            You requested for a password reset on your account.
        </p>

        <a href="{{ $action_url }}" class="clickBtn">{{ $action_text }}</a>

        <p class="detailCont-p">
            This password reset link will expire in 60 minutes.
        </p>

        <p class="detailCont-p">
            If you did not request for a password reset, ignore this message, no further action is required.
        </p>

        <p class="detailCont-p">
            Regards, <br>TalkAM Team
        </p>
    </div>
@endsection
