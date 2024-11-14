<tr>
    <td>
        <p class="detailCont-p">
            This email was sent to you by
            <span><a class="link">{{ config("mail.from.address") }}</a></span> If you'd rather
            not receive this kind of email, you can
            <span><a target="_blank" href="{{ config("app.web_url") . "/settings/profile-notifications" }}"> manage your email preferences.</a></span>
        </p>

        <p class="detailCont-ps">
            TalkAM Technologies, 18 Obagi Street GRA, Portharcourt
        </p>
    </td>
</tr>

<tr>
    <td class="footerlogo">
        <table cellpadding="0" cellspacing="0" border="0" width="100%">
            <tr>
                <td align="left" style="padding: 0;">
                    <span>
                        <img class="flogo" src="{{ asset('email/assets/png/talkam_logo.png') }}" alt="TalkAM Logo" style="display: block;" />
                    </span>
                </td>
                <td align="right" style="padding: 0;">
                    <span class="iconsCont" style="display: inline-block;">
                        <img class="icon1" src="{{ asset('email/assets/png/Twitter-removebg-preview.png') }}" alt="Twitter Icon" style="display: inline-block; margin-left: 10px;" />
                        <img class="icon1" src="{{ asset('email/assets/png/Facebookremovebg-preview.png') }}" alt="Facebook Icon" style="display: inline-block; margin-left: 10px;" />
                        {{-- <img src="{{ asset('email/assets/instagramIcon.jpg') }}" alt="Instagram Icon" style="display: inline-block; margin-left: 10px;" /> --}}
                    </span>
                </td>
            </tr>
        </table>
    </td>
</tr>
