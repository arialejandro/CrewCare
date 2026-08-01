@extends('layouts.app')
    @section('content')
    <div class="container bg-danger p-4" >
    <div class="row  justify-content-center align-items-center" style="height: 60vh;">
        <div class="col-12 d-flex align-items-center justify-content-center" style="height: 60vh;">
            <div class="card bg-danger p-3">
              <div class="card-content">
                  <h4 class="display-3 center text-white">{{__('messages.unauth')}} ⛔ !</h4>
                  <span class="text-white">{{__('messages.contentunauth')}}</span>     
              </div>
            </div>
          </div>  
          <a class="btn btn-warning" href="/home">{{__('messages.btnexit')}}</a> 
        </div>
      </div>
    @endsection
