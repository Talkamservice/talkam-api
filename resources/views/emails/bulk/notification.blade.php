@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hello {{ $recipient_name }},</p>

        <p class="detailCont-p">
            {!! $title !!}
        </p>

        <p class="detailCont-p">
            {!! $message !!}
        </p>

        <p class="detailCont-p">
            Regards, <br>TalkAM Team
        </p>
    </div>
@endsection
