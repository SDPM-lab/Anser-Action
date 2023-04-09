<?php
namespace App\Anser\Config;

use SDPMlab\Anser\Service\ServiceList;

ServiceList::setDiscoverConfig([
    "discoverMode" => "fabio",           // Anser load balance provides "none"、"default" and "fabio" options to implement service discovery.
    "default" => [
        'HttpClient' => ServiceList::getHttpClient(),
        'Address'    => '140.127.74.171:8500',     // [required]
        'Scheme'     => 'http',                    // [optional] defaults to "http"  [option: HTTP | HTTPS]
        // 'Datacenter' => 'name of datacenter',   // [optional]
        // 'HttpAuth' => 'user:pass',              // [optional]
        // 'WaitTime' => '0s',                     // [optional] amount of time to wait on certain blockable endpoints.  go time duration string format. 
        // 'Token' => 'auth token',                // [optional] default auth token to use
        // 'TokenFile' => 'file with auth token',  // [optional] file containing auth token string
        // 'InsecureSkipVerify' => false,          // [optional] if set to true, ignores all SSL validation
        // 'CAFile' => '',                         // [optional] path to ca cert file, see http://docs.guzzlephp.org/en/latest/request-options.html#verify
        // 'CertFile' => '',                       // [optional] path to client public key.  if set, requires KeyFile also be set
        // 'KeyFile' => '',                        // [optional] path to client private key.  if set, requires CertFile also be set
        // 'JSONEncodeOpts'=> 0,                   // [optional] php json encode opt value to use when serializing requests
    ],
    "fabio" => [
        'fabioRouteService' => '140.127.74.171:9998',
        'fabioProxyService' => '140.127.74.171:9999',
    ]
]);




?>