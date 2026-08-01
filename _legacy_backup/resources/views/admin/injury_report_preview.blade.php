@extends('layouts.app')

@section('content')

<style>
        body {
            font-family: monospace;
            background: #f9f9f9;
            padding: 20px;
        }
        h1 {
            font-size: 1.5em;
        }
        pre {
            background: #eee;
            padding: 10px;
            overflow-x: auto;
        }
    </style>

    <h1>Datos recibidos del formulario</h1>
    <h2>Inputs generales:</h2>
    <pre>{{ print_r($dataForDb, true) }}</pre>

    @if (!empty($files))
        <h2>Archivos subidos:</h2>
        <pre>{{ print_r($files, true) }}</pre>
    @endif

    <a href="{{ url()->previous() }}">← Volver al formulario</a>