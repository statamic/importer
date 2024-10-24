@extends('statamic::layout')
@section('title', __('Importer'))

@section('content')
    <header class="mb-6">
        @include('statamic::partials.breadcrumb', [
            'url' => cp_route('utilities.index'),
            'title' => __('Utilities')
        ])
        <h1>{{ __('Importer') }}</h1>
    </header>

    <create-import-form
        class="mb-10"
        action="{{ cp_route('utilities.importer.store') }}"
        :fields='@json($fields)'
        :initial-meta='@json($meta)'
        :initial-values='@json($values)'
    ></create-import-form>

    @if($imports->isNotEmpty())
        <div>
            <h2 class="mb-2">{{ __('Recent Imports') }}</h2>
            <imports-listing :initial-rows='@json($imports)'></imports-listing>
        </div>
    @endif
@stop
