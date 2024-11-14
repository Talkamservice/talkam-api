@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hello,</p>

        <p class="detailCont-p">
            {{ $message }}
        </p>

        <a href="{{ $action_url }}" class="clickBtn">{{ $action_text }}</a>

        <p class="detailCont-p">
            Regards, <br>TalkAM Team
        </p>
    </div>
@endsection
