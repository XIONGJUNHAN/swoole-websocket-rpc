<?php
include 'WebsocketRPC.php';
$rpc = new WebsocketRPC(9998, 9999, [
    'host' => "127.0.0.1",
    'port' => 6379,
    'prefix' => "WSRPC_",
], [
    'daemonize' => false,
    'log_file' => '/tmp/ws.log',
]);
$rpc->WebsocketOpen(function ($user_key) {
    print_r($user_key);
});
$rpc->WebsocketMessage(function ($data) use ($rpc) {
    $rpc->WebsocketSend($data['user_key'], "Recv:" . $data['data']);
    print_r($data);
});
$rpc->WebSocketClose(function ($user_key) {
    print_r($user_key);
});
$rpc->HttpServerStart();
$rpc->WebsocketStart();
