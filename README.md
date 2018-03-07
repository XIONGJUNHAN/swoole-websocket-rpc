# swoole-websocket-rpc
基于Swoole的Websocket RPC类

## Demo

```
<?php
include 'WebsocketRPC.php';
$http_rpc_port = 9998;
$websocket_port = 9999;
$rpc = new WebsocketRPC($http_rpc_port, $websocket_port, [
	'host' => "127.0.0.1",
	'port' => 6379,
	'prefix' => "WSRPC_",
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
```

## Frontend Demo
```
<script type="text/javascript">
var wsUri = "ws://127.0.0.1:9999/" + user_key;  //A Unique primary key
var ws = new WebSocket(wsUri);
ws.onopen = function(evt) {
};
ws.onclose = function(evt) {
};
ws.onmessage = function(evt) {
    console.log("Receive: " + evt.data);
};
ws.onerror = function(evt) {
    console.log("ERROR: " + evt.data);
};
</script>
```

## RPC API

Params | Type | Description |Method
--- | --- | --- | ---
act | String | ```stats``` / ```list``` / ```push``` | GET
type| String | ```single``` / ```multi``` / ```broadcast``` | GET
data | String | ```Some Text...``` | POST
user_key_list | String | ```[{"key":"md5(user_key)","msg":"owo"}]``` | POST
user_key | String | ```md5(user_key)``` | POST

### Demo Response

#### GET ```http://127.0.0.1:9998/?act=stats```
```
{
    "status": 200,
    "result": {
        "start_time": 1520444332,
        "connection_num": 5,
        "accept_count": 9,
        "close_count": 4,
        "tasking_num": 0,
        "request_count": 8,
        "worker_request_count": 6
    }
}
```

---

#### GET ```http://127.0.0.1:9998/?act=list```
```
{
	"status": 200,
	"result": [
	{
		"websocket_status": 3,
		"server_port": 9999,
		"server_fd": 4,
		"socket_type": 1,
		"remote_port": 51892,
		"remote_ip": "127.0.0.1",
		"from_id": 7,
		"connect_time": 1520444333,
		"last_time": 1520444336,
		"close_errno": 0,
		"user_key_raw": "7"
	}
]}
```

---

#### POST ```http://127.0.0.1:9998/?act=push&type=single&user_key={md5(user_key)}```
Params | Content
--- | --- |
data | owo

```
{
	"status":200
}
```

---

#### POST ```http://127.0.0.1:9998/?act=push&type=multi```
Params | Content
--- | --- |
user_key_list | ```[{"key":"md5(user_key)","msg":"owo"}]```

```
{
	"status":200
}
```

---

#### POST ```http://127.0.0.1:9998/?act=push&type=broadcast```
Params | Content
--- | --- |
data | owo

```
{
	"status":200
}
```