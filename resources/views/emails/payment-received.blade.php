@extends('emails.layout')

@section('title', 'Payment received')
@section('preheader', "We've received your payment for {$course?->title}. Your invoice is attached.")
@section('reason', "You're receiving this because you paid for a course on giseafrica.com.")

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $name }},</p>
    <p style="margin:0 0 20px;">Thank you, we've received your payment and you're registered for <strong>{{ $course?->title }}</strong>.</p>
    @include('emails.partials.details', ['rows' => $rows])
    <p style="margin:20px 0 0;color:#44524b;">Your invoice is attached to this email as a PDF. You can download it again any time from the <a href="{{ $invoicesUrl }}" style="color:#0c6b44;">Payments page</a>.</p>
    @include('emails.partials.button', ['url' => $url, 'label' => 'Go to my courses'])
    <p style="margin:24px 0 0;font-size:13px;color:#6b7a72;">Questions about your payment? Just reply to this email.</p>
@endsection
