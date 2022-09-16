<?php

namespace Minororange\AsyncSocket\Socket;

class Http extends SocketClient
{


    public function request(array $query)
    {
        $query = http_build_query($query);
        $packets = "GET /?{$query} HTTP/1.1\r\n";
        $packets .= "Host: {$this->serverEntity->address}\r\n";
        $packets .= "User-Agent: AsyncSocket/1.0.0\r\n\r\n";

        $this->send($packets, strlen($packets));
    }


    public function getResponse()
    {
        $receive = $this->recv();

        return new Response($receive);
    }
}