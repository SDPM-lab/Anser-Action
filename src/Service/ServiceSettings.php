<?php

namespace SDPMlab\Anser\Service;

class ServiceSettings
{
    public $name;
    public $address;
    public $port;
    public $isHttps;
    public $serviceName;

    public function __construct(string $name, string $address, int $port = 80, bool $isHttps = false, string $serviceName = null)
    {
        $this->name = $name;
        $this->address = $address;
        $this->port = $port;
        $this->isHttps = $isHttps;
        $this->serviceName = $serviceName;
    }

    /**
     * 取得 URL 字串
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        $url = '';
        $url .= $this->isHttps ? 'https://' : 'http://';
        $url .= $this->address;
        if($this->port == 80 && $this->port == 443) {
            $url .= '/';
        } else {
            $url .= ':' . $this->port .'/';
        }

        if (!is_null($this->serviceName)) {
            $url .= $this->serviceName . '/';
        }
        return $url;
    }
}
