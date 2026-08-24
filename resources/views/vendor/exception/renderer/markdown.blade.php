# {{ $exception->class() }} - {!! $exception->title() !!}

{!! $exception->message() !!}

PHP {{ PHP_VERSION }}
Laravel {{ app()->version() }}
{{ $exception->request()->httpHost() }}

## Stack Trace

@foreach($exception->frames() as $index => $frame)
{{ $index }} - {{ $frame->file() }}:{{ $frame->line() }}
@endforeach

@if ($exception->previousExceptions()->isNotEmpty())
## Previous {{ \Illuminate\Support\Str::plural('exception', $exception->previousExceptions()->count()) }}
@foreach ($exception->previousExceptions() as $index => $previous)

### {{ $index + 1 }}. {{ $previous->class() }}

{!! $previous->message() !!}

@foreach($previous->frames() as $index => $frame)
{{ $index }} - {{ $frame->file() }}:{{ $frame->line() }}
@endforeach
@endforeach
@endif

## Request

{{ $exception->request()->method() }} {{ \Illuminate\Support\Str::start($exception->request()->path(), '/') }}

## Headers

@php
$headers = $exception->requestHeaders();
$emptyHeaders = empty($headers);
@endphp
@if (!$emptyHeaders)
@foreach ($headers as $key => $value)
* **{{ $key }}**: {!! $value !!}
@endforeach
@else
No header data available.
@endif

## Route Context

@php
$routeContext = $exception->applicationRouteContext();
$emptyRouteContext = empty($routeContext);
@endphp
@if (!$emptyRouteContext)
@foreach ($routeContext as $name => $value)
{{ $name }}: {!! $value !!}
@endforeach
@else
No routing data available.
@endif

## Route Parameters

@if ($routeParametersContext = $exception->applicationRouteParametersContext())
{!! $routeParametersContext !!}
@else
No route parameter data available.
@endif

## Database Queries

@php
$queries = $exception->applicationQueries();
$emptyQueries = empty($queries);
@endphp
@if (!$emptyQueries)
@foreach ($queries as $query)
* {{ $query['connectionName'] }} - {!! $query['sql'] !!} ({{ $query['time'] }} ms)
@endforeach
@else
No database queries detected.
@endif