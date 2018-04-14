<?php
class WebsocketRPC
{
    public $redis;
    public $ws_server;
    public $http_server;
    public $redis_key_prefix;
    public function __construct($http_port = 9998, $websocket_port = 9999, $redis_config = [], $custom_config = [])
    {
        $redis = new Redis();
        $redis->connect($redis_config['host'], $redis_config['port']);
        if (array_key_exists("auth", $redis_config)) {
            $redis->auth($redis_config['auth']);
        }
        $redis->delete($redis->keys($redis_config['prefix'] . "*"));
        $this->redis_key_prefix = $redis_config['prefix'];

        $ws_server = new swoole_websocket_server('0.0.0.0', $websocket_port);
        $ws_server->set($custom_config);
        $http_server = $ws_server->addListener('0.0.0.0', $http_port, SWOOLE_SOCK_TCP);
        $http_server->set(array(
            'open_http_protocol' => true,
        ));
        $this->redis = $redis;
        $this->ws_server = $ws_server;
        $this->http_server = $http_server;
    }
    public function WebsocketOpen($callback)
    {
        $redis = $this->redis;
        $this->ws_server->on('open', function ($server, $req) use ($redis, $callback) {
            $user_key = explode("/", $req->server['path_info'])[1];
            $redis->set($this->redis_key_prefix . md5($user_key), $req->fd);
            $redis->set($this->redis_key_prefix . "socket_fd_" . $req->fd, $user_key);
            call_user_func($callback, ["user_key" => $user_key]);
            echo "connection open: {$req->fd}\n";
        });
    }
    public function WebsocketMessage($callback)
    {
        $redis = $this->redis;
        $this->ws_server->on('message', function ($server, $frame) use ($redis, $callback) {
            echo "received message: {$frame->data}\n";
            $user_key = $redis->get($this->redis_key_prefix . "socket_fd_" . $frame->fd);
            call_user_func($callback, ["user_key" => $user_key, "data" => $frame->data, "opcode" => $frame->opcode]);
        });
    }
    public function WebsocketSend($user_key, $data, $opcode = WEBSOCKET_OPCODE_TEXT)
    {
        $_fd = $this->redis->get($this->redis_key_prefix . md5($user_key));
        print_r($_fd);
        return $this->ws_server->push($_fd, $data, $opcode);
    }
    public function WebSocketClose($callback)
    {
        $redis = $this->redis;
        $this->ws_server->on('close', function ($server, $fd) use ($redis, $callback) {
            $user_key = $redis->get($this->redis_key_prefix . "socket_fd_" . $fd);
            $redis->del($this->redis_key_prefix . md5($user_key));
            $redis->del($this->redis_key_prefix . "socket_fd_" . $fd);

            call_user_func($callback, ["user_key" => $user_key]);
            echo "connection close: {$fd}\n";
        });
    }
    public function WebsocketStart()
    {
        $this->ws_server->start();
    }
    public function HttpServerStart()
    {
        $ws_server = $this->ws_server;
        $redis = $this->redis;
        $this->http_server->on('request', function ($request, $response) use ($ws_server, $redis) {
            $act = @$request->get['act'];
            if (!$act) {
                $response->end(json_encode([
                    'status' => 404,
                ]));
            }
            switch ($act) {
                case 'stats':
                    $response->end(json_encode([
                        'status' => 200,
                        'result' => $ws_server->stats(),
                    ]));
                    break;
                case 'list':
                    $list = [];
                    foreach ($ws_server->connections as $fd) {
                        if (array_key_exists("websocket_status", $result = $ws_server->connection_info($fd))) {
                            $result['user_key_raw'] = $redis->get($this->redis_key_prefix . "socket_fd_" . $fd);
                            $list[] = $result;
                        }
                    }
                    $response->end(json_encode([
                        'status' => 200,
                        'result' => $list,
                    ]));
                    break;
                case 'close':
                    $result = [];
                    $fd = $redis->get($this->redis_key_prefix . $request->get['user_key']);
                    if ($ws_server->close($fd) == true) {
                        $result['status'] = 200;
                    } else {
                        $result['status'] = 404;
                    }
                    break;
                case 'push':
                    $result = [];
                    switch (@$request->get['type']) {
                        case 'single':
                            $fd = $redis->get($this->redis_key_prefix . $request->get['user_key']);
                            if ($ws_server->push($fd, @$request->post['data']) == true) {
                                $result['status'] = 200;
                            } else {
                                $result['status'] = 404;
                            }
                            break;
                        case 'multi':
                            $fd_list = json_decode(@$request->post['user_key_list'], true);
                            $result = [];
                            $fail_count = 0;
                            $succ_count = 0;
                            foreach ($fd_list as $key => $value) {
                                $_fd = $redis->get($this->redis_key_prefix . $value['key']);
                                if ($ws_server->push($_fd, $value['msg']) == true) {
                                    $succ_count = $succ_count + 1;
                                } else {
                                    $fail_count = $fail_count + 1;
                                }
                            }
                            $result['status'] = 200;
                            $result['success_count'] = $succ_count;
                            $result['fail_count'] = $fail_count;
                            break;
                        case 'broadcast':
                            $result = [];
                            foreach ($ws_server->connections as $fd) {
                                $ws_server->push($fd, @$request->post['data']);
                            }
                            $result['status'] = 200;
                            break;
                    }
                    $response->end(json_encode($result));
                    break;
            }
        });
    }
}
