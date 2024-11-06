@use('Statamic\Support\Str')

@extends('statamic::layout')
@section('title', $import->name())
@section('wrapper_class', 'max-w-3xl')

@section('content')
    <edit-import-form
        action="{{ $import->updateUrl() }}"
        method="patch"
        publish-container="base"
        initial-title="{{ $title }}"
        :initial-fieldset="{{ json_encode($blueprint) }}"
        :initial-values="{{ json_encode($values) }}"
        :initial-meta="{{ json_encode($meta) }}"
        :breadcrumbs='@json($breadcrumbs)'
        :batches-table-missing="{{ Str::bool($batchesTableMissing) }}"
    ></edit-import-form>
@stop
