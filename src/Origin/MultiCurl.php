<?php

namespace Minororange\AsyncSocket\Origin;

class MultiCurl
{

    private array $urls;

    private $mh;

    private array $chs = [];


    private array $startTimes = [];

    public function __construct(array $urls)
    {
        $this->urls = $urls;
    }

    public function request()
    {
        foreach ($this->urls as $url) {
            $this->createCh($url);

        }
        $this->exec();
        $contents = $this->getContents();
        $this->close();
        return $contents;
    }

    private function removeCh(\CurlHandle $ch)
    {
        $mh = $this->getMh();

        curl_multi_remove_handle($mh, $ch);
    }


    private function exec()
    {
        $this->execCurl($active, $mrc);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($this->getMh()) != -1) {
                $this->execCurl($active, $mrc);
            }
        }
    }

    private function close()
    {
        curl_multi_close($this->getMh());
    }

    private function getContents()
    {
        $contents = [];
        foreach ($this->chs as $key => $ch) {
            $start = $this->startTimes[$key];
            echo "curl start at:[$start]\n";
            $contents[] = curl_multi_getcontent($ch);

            $spend = microtime(true) - $start;
            echo "curl complete in [$spend]s\n";
            $this->removeCh($ch);
        }

        return $contents;
    }

    /**
     * @return mixed
     */
    public function getMh()
    {
        if (is_null($this->mh)) {
            $this->mh = curl_multi_init();
        }

        return $this->mh;
    }

    /**
     * @param $url
     * @return false|\CurlHandle
     *
     */
    private function createCh($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $this->chs[] = $ch;
        $this->startTimes[] = microtime(true);
        curl_multi_add_handle($this->getMh(), $ch);

        return $ch;
    }

    /**
     * @param $active
     */
    private function execCurl(&$active, &$mrc)
    {
        do {
            $mrc = curl_multi_exec($this->getMh(), $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    }


}