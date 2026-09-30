@extends('emails.layout')

@section('title', "You're approved to mentor")
@section('preheader', "You've been approved to mentor {$course->title}.")
@section('reason', "You're receiving this because you applied to mentor a cohort on giseafrica.com.")

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $name }},</p>
    <p style="margin:0 0 20px;">Good news: your application to mentor <strong>{{ $course->title }}</strong> has been approved.</p>
    @include('emails.partials.details', ['rows' => $rows])
    <p style="margin:20px 0 0;color:#44524b;">You'll see the cohort, its learners and the course materials in your mentor dashboard.</p>
    @include('emails.partials.button', ['url' => $url, 'label' => 'Open my cohorts'])
@endsection
