@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hello {{ $recipient_name }},</p>

        <p class="detailCont-p">
            {!! $title !!}
        </p>

        <p class="detailCont-p">
<<<<<<< HEAD
            Warm regards,
        </p>
        <p class="detailCont-p">
            The TalkAM Team
=======
            {!! $message !!}
        </p>

        <p class="detailCont-p">
            Regards, <br>TalkAM Team
>>>>>>> 81592a37d8eb28f3448bf4e1d48ce4b4b7211623
        </p>
    </div>
@endsection
