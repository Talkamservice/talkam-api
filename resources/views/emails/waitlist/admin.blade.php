@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hello Admin,</p>

        <p class="detailCont-p">
            Exciting news! A new user with this email <b style="text-decoration: none">{{ $email }}</b> has just joined your waitlist.
        </p>

        <p class="detailCont-p">
            The TalkAM Team
        </p>
    </div>
@endsection
