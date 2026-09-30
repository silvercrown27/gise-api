@extends('emails.layout')

@section('title', 'Your role has changed')
@section('preheader', 'Your role on GISE Africa was updated.')
@section('reason', "You're receiving this because a GISE Africa administrator changed your role.")

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $name }},</p>
    <p style="margin:0 0 20px;">A GISE Africa administrator has changed your role on the platform.</p>
    @include('emails.partials.details', ['rows' => $rows])
    @if ($meaning)
        <p style="margin:20px 0 0;color:#44524b;">{{ $meaning }}</p>
    @endif
    <p style="margin:16px 0 0;font-size:13px;color:#6b7a72;">If the menus in your dashboard look out of date, sign out and back in. Didn't expect this change? Reply to this email or contact <a href="mailto:info@giseafrica.com" style="color:#0c6b44;">info@giseafrica.com</a>.</p>
    @include('emails.partials.button', ['url' => $url, 'label' => 'Open my dashboard'])
@endsection
