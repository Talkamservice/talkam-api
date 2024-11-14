@extends('emails.layouts.app')

@section('content')
    <div>
        <p>Hello {{ $recipient_name }},</p>

        <p class="detailCont-p">
            <strong>{{ $title }}</strong>
        </p>

        <p class="detailCont-p">
            {!! $message !!}
        </p>

        <p class="detailCont-p">
            <strong>Total Impressions:</strong> {{ $impressions }}
        </p>

        <p class="detailCont-p">
            Click <a href="#" target="_blank">here</a> to view the analytics of this ad
        </p>

        <p class="detailCont-p">
            Regards, <br>TalkAM Team
        </p>
    </div>
@endsection
