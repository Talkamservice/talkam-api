@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hi,</p>
        <p class="detailCont-p">
            Help us secure your account as you verify it is authentic.
        </p>

        <p class="detailCont-p">
            <b>CODE</b>: {{ $pin->code }} <i>(Expires in {{ $expires_at }})<i><br><br>
        </p>

        <p class="detailCont-p">
            If you did not request for a password reset, ignore this message, no further action is required.
        </p>

        <p class="detailCont-p">
            Regards, <br>TalkAM Team
        </p>
    </div>
@endsection
