@extends('emails.layout')

@section('title', 'Your ' . $course->title . ' brochure')
@section('preheader', 'The ' . $course->title . ' brochure you asked for.')
@section('reason', "You're receiving this because you requested the " . $course->title . ' brochure on giseafrica.com.')

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $name }},</p>
    <p style="margin:0 0 16px;">Thanks for your interest in <strong>{{ $course->title }}</strong>.</p>
    @if ($course->tagline)
        <p style="margin:0 0 16px;color:#44524b;">{{ $course->tagline }}</p>
    @endif

    @if ($brochureUrl)
        @include('emails.partials.button', ['url' => $brochureUrl, 'label' => 'Download the brochure'])
        <p style="margin:20px 0 0;color:#44524b;">You can also see upcoming cohorts, fees and locations on the <a href="{{ $courseUrl }}" style="color:#0c6b44;">course page</a>.</p>
    @else
        <p style="margin:0 0 4px;color:#44524b;">The full brochure is being finalised, and we'll send it as soon as it is ready.</p>
        @include('emails.partials.button', ['url' => $courseUrl, 'label' => 'See cohorts, fees and locations'])
    @endif

    <p style="margin:24px 0 0;color:#44524b;">Reply to this email if you have any questions.</p>
@endsection
