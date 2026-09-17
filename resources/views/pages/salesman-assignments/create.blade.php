@extends('layouts.app')

@section('title', 'New Salesman Assignment')

@section('content')
    <x-ui.page-header title="New salesman assignment"
                      description="Assign a salesman to a supervisor and operational area." />

    @include('pages.salesman-assignments._form')
@endsection
