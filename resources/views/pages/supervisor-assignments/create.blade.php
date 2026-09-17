@extends('layouts.app')

@section('title', 'New Supervisor Assignment')

@section('content')
    <x-ui.page-header title="New supervisor assignment"
                      description="Assign a supervisor to a branch and territory." />

    @include('pages.supervisor-assignments._form')
@endsection
