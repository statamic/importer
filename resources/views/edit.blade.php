@use('Statamic\Support\Str')

@extends('statamic::layout')
@section('title', $import->name())
@section('wrapper_class', 'max-w-3xl')

@section('content')
    <edit-import-form
        action="{{ $import->updateUrl() }}"
        :breadcrumbs='@json($breadcrumbs)'
        title="{{ $import->name() }}"
        :initial-config='@json($import->config())'
        mappings-url="{{ cp_route('utilities.importer.mappings') }}"
        :batches-table-missing="{{ Str::bool($batchesTableMissing) }}"
    ></edit-import-form>
@stop
