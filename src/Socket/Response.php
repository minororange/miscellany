<?php

namespace Minororange\AsyncSocket\Socket;

class Response
{

    private $raw;

    private $packets = [];

    private $headers = [];

    private $body;

    public function __construct($raw)
    {
        $this->raw = $raw;
        $this->packets = explode("\r\n", $raw);
        $this->resolvePackets();
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    private function resolvePackets()
    {
        foreach ($this->packets as $index => $packet) {
            if ($packet === "") {
                continue;
            }
            if (preg_match('/:\s/', $packet)) {
                $header = explode(':', $packet);

                $this->headers[$header[0]] = trim($header[1]);
            }
            if (is_numeric($packet) && $packet != 0) {
                $this->body = trim($this->packets[$index + 1]);
            }
        }
    }
}