@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hello {{ $recipient_name }},</p>

        <p class="detailCont-p">
            We wanted to inform you that your group "<strong>{{ $group_name }}</strong>" has been deleted.
        </p>

        <p class="detailCont-ps">
            Click on this <span><a class="link" href="{{ config("app.web_url") . "/help&info/rules" }}">link</a></span> to view TalkAM rules and privacy policies.
        </p>

        <p class="detailCont-p">
            Regards, <br>TalkAM Team
        </p>
    </div>
@endsection
