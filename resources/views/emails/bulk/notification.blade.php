@extends('emails.layouts.app')

@section('content')
    <div style="background:#ffffff;background-color:#ffffff;margin:0px auto;max-width:600px;">
        <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;background-color:#ffffff;width:100%;">
            <tbody>
                <tr>
                    <td style="direction:ltr;font-size:0px;padding:0px;text-align:center;">
                        <div style="margin:0px auto;max-width:600px;">
                            <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;">
                                <tbody>
                                    <tr>
                                        <td style="direction:ltr;font-size:0px;padding:20px 0;padding-bottom:0px;text-align:center;">
                                            <div class="mj-column-per-100 mj-outlook-group-fix" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;">
                                                <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="vertical-align:top;" width="100%">
                                                    <tbody>
                                                        <tr>
                                                            <td align="left" style="font-size:0px;padding:10px 25px;word-break:break-word;">
                                                                <div style="font-family:Muli, Arial, sans-serif;font-size:20px;font-weight:400;line-height:30px;text-align:left;color:#333333;">
                                                                    <h1 style="margin: 0; font-size: 24px; line-height: normal; font-weight: bold;">Hello{{ !empty($recipient_name) ? " $recipient_name" : '' }},</h1>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="font-size:0px;padding:10px 25px;word-break:break-word;">
                                                                <p style="border-top:solid 1px #F4F5FB;font-size:1px;margin:0px auto;width:100%;">
                                                                </p>
                                                            </td>
                                                        </tr>

                                                        <tr>
                                                            <td align="left" style="font-size:0px;padding:10px 25px;word-break:break-word;">
                                                                <div style="font-family:Muli, Arial, sans-serif;font-size:14px;font-weight:400;line-height:20px;text-align:left;color:#333333;">{{ $title }}</div>
                                                            </td>
                                                        </tr>

                                                        <tr>
                                                            <td align="left" style="font-size:0px;padding:10px 25px;word-break:break-word;">
                                                                <div style="font-family:Muli, Arial, sans-serif;font-size:14px;font-weight:400;line-height:20px;text-align:left;color:#333333;">{{ $message }}</div>
                                                            </td>
                                                        </tr>


                                                        <tr>
                                                            <td align="left" style="font-size:0px;padding:10px 25px;word-break:break-word;">
                                                                <div style="font-family:Muli, Arial, sans-serif;font-size:14px;font-weight:400;line-height:20px;text-align:left;color:#333333;">Regards, <br>Talkam Team</div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div style="margin:0px auto;max-width:600px;">
        <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;">
            <tbody>
                <tr>
                    <td style="direction:ltr;font-size:0px;padding:20px 0;text-align:center;">
                        <div class="mj-column-per-100 mj-outlook-group-fix" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;">
                            <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="vertical-align:top;" width="100%">
                                <tbody>
                                    <tr>
                                        <td style="font-size:0px;word-break:break-word;">
                                            <div style="height:1px;">   </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
@endsection
