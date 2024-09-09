@extends('emails.layouts.app')

@section('content')
    <div class="">
        <p>Hello {{ $recipient_name }},</p>

        <p class="detailCont-p">
            Thanks for signing up for TalkAM! We're excited to have you join our community.
        </p>

        <p class="detailCont-p">
            As we prepare for our launch, we're building a waitlist of users who are eager to connect and support one another. We'll let you know as soon as TalkAM is available for you to explore.
        </p>

        <p class="detailCont-p">
            In the meantime, feel free to follow us on
            <span><a class="link" href="https://www.instagram.com/talkamtechservices?igsh=MXJhdG9hcThpbTVlaw==">X</a></span>,
            <span><a class="link" href="https://www.facebook.com/profile.php?id=61565345395891&mibextid=ZbWKwL">Facebook</a></span>,
            <span><a class="link" href="https://x.com/TalkAM_?t=mjLzdDE8RfMkF2vFHKBc4A&s=09">Instagram</a></span>
            for updates and sneak peeks.
        </p>

        <p class="detailCont-p">
            Thanks again for your interest!
        </p>

        <p class="detailCont-p">
            The TalkAM Team
        </p>
    </div>
@endsection
