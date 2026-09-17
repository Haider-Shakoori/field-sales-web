@extends('layouts.app')

@section('title', 'Edit Salesman Assignment')

@section('content')
    <x-ui.page-header title="Edit salesman assignment"
                      :description="$assignment->salesman ? trim($assignment->salesman->first_name.' '.$assignment->salesman->last_name).' ('.$assignment->salesman->employee_code.')' : 'Assignment details'" />

    @include('pages.salesman-assignments._form')
@endsection
