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
        {{-- <span> <img class="flogo" src="{{ imageUrlToBase64('email/assets/talkamlogo.svg') }}" /></span>
        <span class="iconsCont">
            <img class="icon1" src="{{ imageUrlToBase64("email/assets/twitterIconB.svg") }}" />
            <img class="icon1" src="{{ imageUrlToBase64("email/assets/facebookIconB.svg") }}" />
            <img src="{{ imageUrlToBase64("email/assets/instagramIcon.svg") }}" />
        </span> --}}
        <span> 
            <img class="flogo" src="https://admin.talkam.net/email/assets/talkamlogo.svg" alt="Logo" />
        </span>
        <span class="iconsCont">
            <img class="icon1" src="https://admin.talkam.net/email/assets/twitterIconB.svg" alt="Twitter Icon" />
            <img class="icon1" src="https://admin.talkam.net/email/assets/facebookIconB.svg" alt="Facebook Icon" />
            <img class="icon1" src="https://admin.talkam.net/email/assets/instagramIcon.svg" alt="Instagram Icon" />
        </span>
    </td>
</tr>