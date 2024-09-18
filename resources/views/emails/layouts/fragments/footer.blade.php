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
        <span> <img class="flogo" src="{{ asset('email/assets/talkamlogo.svg') . "?v=1" }}" /></span>
        <span class="iconsCont">
            <img class="icon1" src="{{ asset("email/assets/twitterIconB.svg") . "?v=1" }}" />
            <img class="icon1" src="{{ asset("email/assets/facebookIconB.svg") . "?v=1" }}" />
            <img src="{{ asset("email/assets/instagramIcon.svg") . "?v=1" }}" />
        </span>
    </td>
</tr>