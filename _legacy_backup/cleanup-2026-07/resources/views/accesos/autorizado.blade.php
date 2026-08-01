@extends('layouts.app')
    @section('content')
    <div class="container" >
    <div class="row  justify-content-center align-items-center" style="height: 30vh;">
        <div class="col-12 d-flex align-items-center justify-content-center" style="height: 30vh;">
            <div class="card">
              <div class="card-content">
                  <h4 class="center">{{__('messages.auth')}} ✅ !</h4>
                  <span>{{__('messages.contentauth')}}</span>     
              </div>
              </div>
            </div>
            <a class="btn btn-outline-success" href="/home">{{__('messages.btnexit')}}</a>
          </div>   
        </div>
      </div>
    @endsection