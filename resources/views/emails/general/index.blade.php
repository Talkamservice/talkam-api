@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hello {{ $recipient_name }},</p>

        <p class="detailCont-p">
            {{ $message }}
        </p>

        @if (isset($action_url))
            <p class="detailCont-ps">
                Click on this <span><a class="link" href="{{ $action_url }}">link</a></span> to view the details.
            </p>
        @else
            <p class="detailCont-ps">
                Click on this <span><a class="link" href="{{ config('app.web_url') . '/help&info/rules' }}">link</a></span> to view TalkAM rules and privacy policies.
            </p>
        @endif

        <p class="detailCont-p">
            Regards, <br>TalkAM Team
        </p>
    </div>
@endsection
