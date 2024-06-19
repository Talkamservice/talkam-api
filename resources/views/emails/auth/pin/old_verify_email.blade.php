@component('mail::message')

Hello <br>

Help us secure your account as you verify it is authentic.<br>

<b>CODE</b>: {{$pin->code}} <i>(Expires in {{ $expires_at }})<i><br><br>

If you did not request for a password reset, ignore this message, no further action is required.<br>
Regards.
@endcomponent
