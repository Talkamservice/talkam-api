@component('mail::message')

Hello <b>{{$user->name}}!</b><br>

You requested for a password reset on your account.<br>

<b>CODE</b>: {{$pin->code}} <i>(Expires in {{ $expires_at }})<i><br><br>

If you did not request for a password reset, ignore this message, no further action is required.<br>
Regards.
@endcomponent
