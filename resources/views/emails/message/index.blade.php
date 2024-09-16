@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hello {{ $recipient_name }},</p>

        <p class="detailCont-p">
            {{ $message }}
        </p>

        <p class="detailCont-ps">
            Click on this <span><a class="link" href="{{ config('app.web_url') . "/home/new/?messages&u=$userId" }}">link</a></span> to view the message.
        </p>

        <p class="detailCont-p">
            Regards, <br>Talkam Team
        </p>
    </div>
@endsection
