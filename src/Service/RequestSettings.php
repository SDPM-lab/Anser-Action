<?php

namespace SDPMlab\Anser\Service;

class RequestSettings
{
    public $method;
    public $url;
    public $path;
    public $options;

    public function __construct(string $method, string $url, string $path, array $options)
    {
        $this->method = $method;
        $this->url = $url;
        $this->path = $path;
        $this->options = $options;
    }

}
