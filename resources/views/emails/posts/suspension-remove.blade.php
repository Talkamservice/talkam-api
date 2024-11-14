@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Dear {{ $recipient_name }},</p>

        <p class="detailCont-p">
            {{ $message }}
        </p>

        <p class="detailCont-ps">
            Kindly ensure to review our <span><a class="link" href="{{ route('web.index') }}">site policy</a></span> to avoid any future issues.
        </p>

        <p class="detailCont-p">
            Regards, <br>TalkAM Team
        </p>
    </div>
@endsection
