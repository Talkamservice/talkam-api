<!DOCTYPE html>
<html lang="en">
@include('emails.layouts.fragments.head')

<body>
    <table class="container" style="width: 100%;">
        <tr>
            <td class="logoCont">
                <img src="{{ asset('email/assets/png/talkam_logo.png') }}" />
            </td>
        </tr>

        <tr>
            <td class="detailCont">
                @yield('content')
            </td>
        </tr>

        <tr>
            <td class="detailCont" style="padding-bottom:0%; padding-top:0%" align="center">
                <hr style="border: 1px solid #000;" class="horzontal" />
            </td>
        </tr>

        <tr>
            <td class="detailCont" style="padding-bottom:0%; padding-top:0%" align="center">
                <p class="getTalkam-h">Get the TalkAM app!</p>
            </td>
        </tr>

        <tr>
            <td class="detailCont" style="padding-bottom:3%; padding-top:0%" align="center">
                <p class="getTalkam-d">
                    Get the most of TalkAM by installing the mobile app. You can log in
                    by using your existing emails address and password.
                </p>
            </td>
        </tr>

        <tr>
            <td class="detailCont" style="padding-bottom:3%; padding-top:0%" align="center">
                <table cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="center" style="padding-right: 0px;"> <!-- Reduced padding from 10px to 5px -->
                            <a href="https://apps.apple.com/us/app/talkam-tech/id6740508182" target="_blank" style="text-decoration: none;">
                                <img class="appleIcon" src="{{ asset('email/assets/png/Download_on_App_Store-removebg-preview.png') }}" alt="Download on the App Store" style="display: block;" />
                            </a>
                        </td>
                        <td align="center" style="padding-left: 0px;"> <!-- Optional: Added padding to the left of the second image -->
                            <a href="https://play.google.com/store/apps/details?id=com.talkamtech.app" target="_blank" style="text-decoration: none;">
                                <img class="playstoreIcon" src="{{ asset('email/assets/png/Google_Play-removebg-preview.png') }}" alt="Get it on Google Play" style="display: block;" />
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        @include('emails.layouts.fragments.footer')
    </table>
</body>
