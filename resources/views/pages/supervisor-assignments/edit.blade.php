@extends('layouts.app')

@section('title', 'Edit Supervisor Assignment')

@section('content')
    <x-ui.page-header title="Edit supervisor assignment"
                      :description="$assignment->supervisor ? trim($assignment->supervisor->first_name.' '.$assignment->supervisor->last_name).' ('.$assignment->supervisor->employee_code.')' : 'Assignment details'" />

    @include('pages.supervisor-assignments._form')
@endsection
