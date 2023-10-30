@extends('layouts.app')

@section('content')
    <script>
        let bc = new BroadcastChannel('infusionSoft_channel');
        const urlParams = new URLSearchParams(window.location.search);

        bc.postMessage({
            type: 'sendInfusionSoftToken',
            data: {'code': urlParams.get('code')},
        });

        window.close();
    </script>
@endsection
