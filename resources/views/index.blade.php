@extends('statamic::layout')
@section('title', __('Import'))

@section('content')
    <div class="flex items-center justify-between">
        <h1>{{ __('Import') }}</h1>
    </div>

    <import-wizard
        mappings-url="{{ $mappingsUrl }}"
        :collections='@json($collections)'
        :taxonomies='@json($taxonomies)'
    />
@stop
