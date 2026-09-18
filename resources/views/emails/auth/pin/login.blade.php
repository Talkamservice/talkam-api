@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hi,</p>
        <p class="detailCont-p">
            Use this code to complete your sign in.
        </p>

        <p class="detailCont-p">
            <b>CODE</b>: {{ $pin->code }} <i>(Expires in {{ $expires_at }})<i><br><br>
        </p>

        <p class="detailCont-p">
            If you did not attempt to sign in, please change your password immediately.
        </p>

        <p class="detailCont-p">
            Regards, <br>TalkAM Team
        </p>
    </div>
@endsection
