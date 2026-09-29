@extends('emails.layout')

@section('title', 'Welcome to GISE Africa')

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $name }},</p>
    <p style="margin:0 0 16px;">Welcome to the Global Institute For Skills And Excellence Africa. Your account is ready.</p>
    <p style="margin:0 0 24px;">Browse O-Level, A-Level and professional courses, pick a physical or virtual cohort, and register when you're ready.</p>
    <p style="margin:0;">
        <a href="{{ rtrim(config('app.frontend_url'), '/') }}/courses"
           style="display:inline-block;background:#0c6b44;color:#ffffff;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:999px;">
            Browse courses
        </a>
    </p>
@endsection
