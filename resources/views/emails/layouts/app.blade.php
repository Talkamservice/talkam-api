<!DOCTYPE html>
<html lang="en">
@include('emails.layouts.fragments.head')

<body>
    <table class="container">
        <tr>
            <td class="logoCont">
                <img src="{{ asset("email/assets/png/talkam_logo.png") }}" />
            </td>
        </tr>

        <tr>
            <td class="detailCont">
                @yield('content')

                <hr style="left: 35%; position: relative;" class="horzontal" />

                <p class="getTalkam-h">Get the TalkAM app!</p>

                <p class="getTalkam-d">
                    Get the most of TalkAM by installing the mobile app. You can log in
                    by using your existing emails address and password.
                </p>

                <tr>
                    <td class="btnCont">
                        <button class="apple" style="justify-content: end">
                            <img class="appleIcon" src="{{ asset("email/assets/png/Download_on_App_Store-removebg-preview.png") }}" />
                        </button>
        
                        <button class="playStore">
                            <img class="playstoreIcon" src="{{ asset("email/assets/png/Google_Play-removebg-preview.png") }}" />
                        </button>
                    </td>
                </tr>
            </td>
        </tr>
        @include('emails.layouts.fragments.footer')
    </table>
</body>

</html>
