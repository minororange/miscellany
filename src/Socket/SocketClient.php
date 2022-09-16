<?php

namespace Minororange\AsyncSocket\Socket;

use Minororange\AsyncSocket\Socket\Entity\ServerEntity;

class SocketClient
{
    /**
     * @var \Socket
     */
    public $socket = null;
    private $domain;
    private $type;
    private $protocol;

    private bool $connected = false;

    /**
     * @var false
     */
    private bool $blocking;
    public ServerEntity $serverEntity;

    public function __construct(ServerEntity $serverEntity, $domain, $type, $protocol, $blocking = false)
    {
        $this->domain = $domain;
        $this->type = $type;
        $this->protocol = $protocol;
        $this->blocking = $blocking;
        $this->serverEntity = $serverEntity;
        $this->connect();
    }

    public function connect()
    {
        if ($this->connected) {
            return;
        }
        $write = [$this->getSocket()];
        while (true) {
            if (socket_select($read, $write, $excepts, null) < 1) {
                continue;
            }

            socket_connect($this->getSocket(), $this->serverEntity->address, $this->serverEntity->port);
            $this->connected = true;
            break;
        }
    }

    public function send(string $message, int $length)
    {
        if (!$this->connected) {
            throw new SocketNotConnectedException();
        }

        $write = [$this->getSocket()];
        while (true) {
            if (socket_select($read, $write, $excepts, 0) === 1) {

                socket_write($this->getSocket(), $message, $length);
                break;
            }

        }
    }


    public function recv()
    {
        if (!$this->connected) {
            throw new SocketNotConnectedException();
        }

        $read = [$this->socket];
        $write = [];
        $excepts = [];
        while (true) {
            $socket_select = socket_select($read, $write, $excepts, null);
            if ($socket_select === 1) {
                $message = socket_read($this->getSocket(), 1024 * 5);

                if (!$message) {
                    continue;
                }
                if (empty($message)) {
                    continue;
                }

                return $message;
            }

        }
    }

    /**
     * @return \Socket
     */
    public function getSocket()
    {
        if (is_null($this->socket)) {
            $this->socket = socket_create($this->domain, $this->type, $this->protocol);
            $this->setBlocking();
        }

        return $this->socket;
    }


    public function getSocketLastError()
    {
        return socket_strerror(socket_last_error($this->getSocket()));
    }

    private function setBlocking()
    {
        if (!$this->blocking) {
            socket_set_nonblock($this->getSocket());
        }
    }

    public function close()
    {
        socket_close($this->getSocket());
    }
}