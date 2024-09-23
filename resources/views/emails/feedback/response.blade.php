@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Dear {{ $recipientName }},</p>

        <p class="detailCont-p">
            {{ $messageContent }}
        </p>

        <p class="detailCont-ps">
            Click on this <span><a class="link" href="{{ config('app.web_url') . '/help&info/rules' }}">link</a></span> to
            view TalkAM rules and privacy policies.
        </p>

        <p class="detailCont-p">
            Regards, <br>Talkam Team
        </p>
    </div>
@endsection
