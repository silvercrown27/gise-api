@extends('emails.layout')

@section('title', $approved ? 'Your mentor account is approved' : 'Update on your mentor application')
@section('preheader', $approved ? 'You can now apply to mentor cohorts.' : 'The outcome of your mentor application review.')
@section('reason', "You're receiving this because you signed up as a mentor on giseafrica.com.")

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $name }},</p>
    @if ($approved)
        <p style="margin:0 0 16px;">Welcome aboard! Our team has reviewed your profile and documents, and your mentor account is <strong>approved</strong>.</p>
        <p style="margin:0;color:#44524b;">You can now browse upcoming cohorts and apply to mentor the ones that fit your expertise. Each application is reviewed separately.</p>
        @include('emails.partials.button', ['url' => $url, 'label' => 'Find cohorts to mentor'])
    @else
        <p style="margin:0 0 16px;">Thank you for applying to mentor with GISE Africa. After reviewing your profile and documents, we're not able to approve your mentor account at this time.</p>
        <p style="margin:0;color:#44524b;">If you believe this is a mistake, or you'd like to know more, please reply to this email or contact us at <a href="mailto:info@giseafrica.com" style="color:#0c6b44;">info@giseafrica.com</a>.</p>
    @endif
@endsection
