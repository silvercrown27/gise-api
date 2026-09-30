@extends('emails.layout')

@section('title', 'New payment')
@section('preheader', 'A learner has paid for a course.')
@section('reason', "You're receiving this because you're a super admin on GISE Africa.")

@section('content')
    <p style="margin:0 0 20px;">A learner has just paid for a course:</p>
    @include('emails.partials.details', ['rows' => $rows])
    @include('emails.partials.button', ['url' => $url, 'label' => 'View the learner'])
@endsection
