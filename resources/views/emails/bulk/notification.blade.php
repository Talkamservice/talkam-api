@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hello {{ $recipient_name }},</p>

        <p class="detailCont-p">
            We noticed your promotion is still pending and wanted to give you a friendly reminder that it will expire soon. To activate it and make the most of your benefits, please complete your payment.
        </p>
        <p class="detailCont-p">
           <b>Please, note: </b> {!! $message !!}
        </p>
        <p class="detailCont-p">
            Warm regards,
        </p>
        <p class="detailCont-p">
            The TalkAM Team
        </p>
    </div>
@endsection
