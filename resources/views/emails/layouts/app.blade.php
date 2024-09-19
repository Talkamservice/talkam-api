<!DOCTYPE html>
<html lang="en">
@include('emails.layouts.fragments.head')

<body>
    <table class="container">
        <tr>
            <td class="logoCont">
                <img src="{{ asset('email/assets/talkamlogo1.svg') . "?v=2" }}" />
            </td>
        </tr>

        <tr>
            <td class="detailCont">
                @yield('content')

                <hr class="horzontal" />

                <p class="getTalkam-h">Get the TalkAM app!</p>

                <p class="getTalkam-d">
                    Get the most of TalkAM by installing the mobile app. You can log in
                    by using your existing emails address and password.
                </p>

                <tr>
                    <td class="btnCont">
                        <button class="apple">
                            <img class="appleIcon" src="{{ asset('email/assets/appleIcon.svg') . "?v=1" }}" />
                        </button>
        
                        <button class="playStore">
                            <img class="playstoreIcon" src="{{ asset('email/assets/playstoreIcon.svg') . "?v=1" }}" />
                        </button>
                    </td>
                </tr>
            </td>
        </tr>
        @include('emails.layouts.fragments.footer')
    </table>
</body>

</html>
