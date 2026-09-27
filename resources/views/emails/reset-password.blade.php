@extends('emails.layout')

@section('title', 'Reset your password')

@section('content')
    <p style="margin:0 0 16px;">We received a request to reset the password for {{ $email }}. Your reset code is:</p>
    <p style="margin:0 0 16px;font-size:30px;font-weight:700;letter-spacing:8px;color:#0b1310;">{{ $otp }}</p>
    <p style="margin:0 0 16px;color:#44524b;">It expires in {{ $validityMinutes }} minutes.</p>
    <p style="margin:0;color:#44524b;">If you didn't ask to reset your password, you can ignore this email - your password won't change.</p>
@endsection
