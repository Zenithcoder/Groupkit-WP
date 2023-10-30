@extends('layouts.app')

@section('content')
    <script>
        var bc = new BroadcastChannel('groupkit_channel');
        var message={}
        message.type="sendGmailToken"
        message.data={!! json_encode($response) !!}
        console.log(message)
        bc.postMessage(message);
        window.close()
    </script>
@endsection
