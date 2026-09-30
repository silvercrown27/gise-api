@extends('emails.layout')

@section('title', 'New brochure request')
@section('preheader', 'Someone has requested a course brochure and it needs your decision.')
@section('reason', "You're receiving this because you're a super admin on GISE Africa.")

@section('content')
    <p style="margin:0 0 20px;">Someone has requested a course brochure. It won't be sent until you approve it.</p>
    @include('emails.partials.details', ['rows' => $rows])
    @include('emails.partials.button', ['url' => $url, 'label' => 'Review the request'])
@endsection
