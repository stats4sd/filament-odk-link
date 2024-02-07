@component('mail::message')

{{ $invite->inviter->name }} ({{ $invite->inviter->email }}) has invited you to join the {{ config('app.name') }} with the user role {{ $invite->role->name }}

Click the link below to register on the platform. If you use this email address, you will be automatically assigned the {{ $invite->role->name }} role after registration.

@component('mail::button', ['url' => route('register').'?token='.$invite->token])
    Register to join {{ config('app.name') }}
@endcomponent

If you do not wish to register, or you have been sent this email by mistake, please ignore this message.

Best regards,
Site Admin,
{{ config('app.name') }}

@endcomponent
