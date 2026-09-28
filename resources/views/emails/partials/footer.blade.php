{{--
  Shared footer for the TalkAM Design email templates (resources/views/emails/business
  and resources/views/emails/mobile). Mirrors the copy/links of the existing
  emails.layouts.fragments.footer partial (app-store badges, "sent to you by",
  address, logo + social icons) restyled with this template family's own tokens
  (Nunito stack, #9299A8 muted text, #017FC8 links) so it sits naturally inside the
  rounded #F5F7FB footer panel it replaces.
--}}
<tr>
  <td align="center" style="font-size:0px;padding:0 0 18px;word-break:break-word;">
    <table cellpadding="0" cellspacing="0" border="0">
      <tr>
        <td align="center" style="padding-right:6px;">
          <a href="https://apps.apple.com/us/app/talkam-tech/id6740508182" target="_blank" style="text-decoration:none;">
            <img src="{{ asset('email/assets/png/Download_on_App_Store-removebg-preview.png') }}" alt="Download on the App Store" width="120" style="display:block;border:0;height:auto;" />
          </a>
        </td>
        <td align="center" style="padding-left:6px;">
          <a href="https://play.google.com/store/apps/details?id=com.talkamtech.app" target="_blank" style="text-decoration:none;">
            <img src="{{ asset('email/assets/png/Google_Play-removebg-preview.png') }}" alt="Get it on Google Play" width="120" style="display:block;border:0;height:auto;" />
          </a>
        </td>
      </tr>
    </table>
  </td>
</tr>
<tr>
  <td align="left" style="font-size:0px;padding:0 0 10px;word-break:break-word;">
    <div style="font-family:'Nunito', 'Helvetica Neue', Helvetica, Arial, sans-serif;font-size:11.5px;line-height:1.7;text-align:left;color:#9299A8;">
      This email was sent to you by
      <a class="foot-link" href="mailto:{{ config('mail.from.address') }}" style="color:#017FC8;text-decoration:none;font-weight:700;">{{ config('mail.from.address') }}</a>
      If you'd rather not receive this kind of email, you can
      <a class="foot-link" href="{{ config('app.web_url') . '/settings/profile-notifications' }}" style="color:#017FC8;text-decoration:none;font-weight:700;">manage your email preferences.</a>
    </div>
  </td>
</tr>
<tr>
  <td align="left" style="font-size:0px;padding:0 0 16px;word-break:break-word;">
    <div style="font-family:'Nunito', 'Helvetica Neue', Helvetica, Arial, sans-serif;font-size:11.5px;line-height:1.7;text-align:left;color:#9299A8;">
      TalkAM Technologies, 18 Obagi Street GRA, Portharcourt
    </div>
  </td>
</tr>
<tr>
  <td style="font-size:0px;word-break:break-word;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%">
      <tr>
        <td align="left" style="padding:0;">
          <img src="{{ asset('email/assets/png/talkam-logo.png') }}" alt="TalkAM" width="69" height="22" style="display:block;border:0;height:22px;width:69px;" />
        </td>
        <td align="right" style="padding:0;">
          <img src="{{ asset('email/assets/png/Twitter-removebg-preview.png') }}" alt="Twitter" width="20" style="display:inline-block;border:0;margin-left:10px;" />
          <img src="{{ asset('email/assets/png/Facebookremovebg-preview.png') }}" alt="Facebook" width="20" style="display:inline-block;border:0;margin-left:10px;" />
        </td>
      </tr>
    </table>
  </td>
</tr>
