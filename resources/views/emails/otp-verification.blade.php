@extends('emails.layout')

@section('title', 'Verify your email')

@section('content')
    <p style="margin:0 0 16px;">Use this code to verify {{ $email }}:</p>
    <p style="margin:0 0 16px;font-size:30px;font-weight:700;letter-spacing:8px;color:#0b1310;">{{ $otp }}</p>
    <p style="margin:0 0 16px;color:#44524b;">It expires in {{ $validityMinutes }} minutes.</p>
    <p style="margin:0;color:#44524b;">If you didn't create a GISE Africa account, you can ignore this email.</p>
@endsection
