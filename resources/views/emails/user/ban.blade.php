@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Dear {{ $recipient_name }},</p>

        <p class="detailCont-p">
            {{ $message }}
        </p>
        <p class="detailCont-p">
            If you believe this ban was issued in error or you have taken steps to address the violation, you may appeal this decision by submitting a request to our support team. [<span><a class="link" href="{{ config("app.web_url") . "/help&info/feedback" }}">Appeal here</a></span>].
        </p>
        <p class="detailCont-p">
            Please note that appeals are reviewed carefully, and not all appeals will be granted.
        </p>
        <p class="detailCont-p">
            Thank you for your understanding.
        </p>
        <p class="detailCont-p">
            Sincerely, The TalkAM Team
        </p>
    </div>
@endsection
