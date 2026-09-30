@extends('emails.layout')

@section('title', "You're registered")
@section('preheader', "Your place on {$course->title} is confirmed.")
@section('reason', "You're receiving this because you registered for a course on giseafrica.com.")

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $name }},</p>
    <p style="margin:0 0 20px;">You're registered for <strong>{{ $course->title }}</strong>. Here are your details:</p>
    @include('emails.partials.details', ['rows' => $rows])
    <p style="margin:20px 0 0;color:#44524b;">Your course is now in your dashboard. We'll tell you if anything about the cohort changes.</p>
    @include('emails.partials.button', ['url' => $url, 'label' => 'Go to my courses'])
@endsection
