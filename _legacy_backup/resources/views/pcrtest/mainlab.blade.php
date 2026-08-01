@extends('layouts.app')
@section('content')

    <div class="container height-100 d-flex justify-content-center align-items-center">
        <div class="card card-lab">
            <div class="clip-path-lab">
                <h2 class="display-4">RESULTS</h2>
            </div>
            <div class="content content-lab text-center">
                <p>The time to send the results has come, we ask you to select the correct option. In this way we avoid confusion with the results.</p>
                <a class="box-lab" href="/negativepcr">All negative</a> <a class="box-lab" href="/listcrew"> Report Positive</a>
            </div>
        </div>
    </div>


@endsection