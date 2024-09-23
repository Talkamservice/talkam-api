@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Dear {{ $recipient_name }},</p>

        <p class="detailCont-p">
            {{ $message }}
        </p>

        <p class="detailCont-ps">
            To appeal, visit <span><a class="link" href="{{ config("app.web_url") . "/help&info/feedback" }}"></a></span>.
        </p>

        <p class="detailCont-p">
            Sincerely, <br>Talkam Team
        </p>
    </div>
@endsection
