<?php

namespace SDPMlab\Anser\Discovery;

use CodeIgniter\Test\CIUnitTestCase;
use SDPMlab\Anser\Discovery\DiscoverAdapter;
use SDPMlab\Anser\Exception\DiscoverException;
use SDPMlab\Anser\Discovery\FabioDiscover;
use SDPMlab\Anser\Service\ServiceList;
use SDPMlab\Anser\Service\ServiceSettings;
use stdClass;

class ServiceListTest extends CIUnitTestCase
{
    public $fabioRouteService = '';
    public $fabioProxyService = '';
    public $consulService = '';

    protected function setUp(): void
    {
        parent::setUp();
        ServiceList::setDiscoverConfig([
            "discoverMode" => "fabio",           // Anser load balance provides "none"、"default" and "fabio" options to implement service discovery.
            "default" => [
                'HttpClient' => ServiceList::getHttpClient(),
                'Address'    => $this->consulService,     // [required]
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
                'fabioRouteService' => $this->fabioRouteService,
                'fabioProxyService' => $this->fabioProxyService,
            ]
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $serviceClass = ServiceList::getDiscover();
        unset($serviceClass);
    }

    public function testSetDiscoverConfigByFabio()
    {
        $discoverDriver = ServiceList::getDiscover();
        $this->assertInstanceOf(DiscoverAdapter::getDiscover()::class,$discoverDriver);
    }

    public function testGetDiscoverService()
    {
        $serviceName = "CI5";
        $service = ServiceList::getDiscoverService($serviceName);
        $this->assertInstanceOf(ServiceSettings::class,$service);
    }

    public function testClearAndUpdateDiscoverList()
    {
        $serviceName = "CI5";

        ServiceList::clearDiscoverServicesList();
        $this->assertEquals(ServiceList::getDiscoverServiceList(),[]);

        ServiceList::updateDiscoverServicesList();
        $this->assertIsArray(ServiceList::getDiscoverServiceList());
        $this->assertInstanceOf(ServiceSettings::class, ServiceList::getDiscoverServiceList()[$serviceName]);
    }

    public function testGetBaseUrl()
    {
        ServiceList::addLocalService(
            "testService",
            "127.0.0.1",
            "8443",
            true
        );
        $testServiceData = ServiceList::getServiceData("testService");
        $baseUrl = $testServiceData->getBaseUrl();
        $this->assertEquals($baseUrl,"https://127.0.0.1:8443/");

        $serviceInfo = new stdClass();
        $serviceInfo->service = "CI5";
        $serviceInfo->fabioProxyHost = $this->fabioProxyService;
     
        ServiceList::getDiscover()::$discoveryList[$serviceInfo->service] = new ServiceSettings(
            $serviceInfo->service,
            parse_url($serviceInfo->fabioProxyHost, PHP_URL_HOST),
            parse_url($serviceInfo->fabioProxyHost, PHP_URL_PORT),
            parse_url($serviceInfo->fabioProxyHost, PHP_URL_SCHEME) == 'https' ? true : false,
            $serviceInfo->service
        );

        $serviceBaseUrl = ServiceList::getDiscoverService($serviceInfo->service)->getBaseUrl();
        $this->assertEquals($serviceBaseUrl,$this->fabioProxyService."CI5/");
    }

    public function testServiceNameNotExist()
    {
        $notExistServiceName = "Noh";
        $this->expectException(DiscoverException::class);
        ServiceList::getDiscoverService($notExistServiceName);
    }

    public function testDiscoverSettingError()
    {
        // discoverMode 未設定
        $this->expectException(DiscoverException::class);
        ServiceList::setDiscoverConfig([
            // "discoverMode" => "fabio",           // Anser load balance provides "none"、"default" and "fabio" options to implement service discovery.
            "default" => [
                'HttpClient' => ServiceList::getHttpClient(),
                'Address'    => $this->consulService,     // [required]
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
                'fabioRouteService' => $this->fabioRouteService,
                'fabioProxyService' => $this->fabioProxyService,
            ]
        ]);

        // consul 未設定
        $this->expectException(DiscoverException::class);
        ServiceList::setDiscoverConfig([
            "discoverMode" => "fabio",           // Anser load balance provides "none"、"default" and "fabio" options to implement service discovery.
            // "default" => [
            //     'HttpClient' => ServiceList::getHttpClient(),
                // 'Address'    => $this->consulService,     // [required]
            //     'Scheme'     => 'http',                    // [optional] defaults to "http"  [option: HTTP | HTTPS]
            //     // 'Datacenter' => 'name of datacenter',   // [optional]
            //     // 'HttpAuth' => 'user:pass',              // [optional]
            //     // 'WaitTime' => '0s',                     // [optional] amount of time to wait on certain blockable endpoints.  go time duration string format. 
            //     // 'Token' => 'auth token',                // [optional] default auth token to use
            //     // 'TokenFile' => 'file with auth token',  // [optional] file containing auth token string
            //     // 'InsecureSkipVerify' => false,          // [optional] if set to true, ignores all SSL validation
            //     // 'CAFile' => '',                         // [optional] path to ca cert file, see http://docs.guzzlephp.org/en/latest/request-options.html#verify
            //     // 'CertFile' => '',                       // [optional] path to client public key.  if set, requires KeyFile also be set
            //     // 'KeyFile' => '',                        // [optional] path to client private key.  if set, requires CertFile also be set
            //     // 'JSONEncodeOpts'=> 0,                   // [optional] php json encode opt value to use when serializing requests
            // ],
            "fabio" => [
                'fabioRouteService' => $this->fabioRouteService,
                'fabioProxyService' => $this->fabioProxyService,
            ]
        ]);

        // fabio 未設定
        $this->expectException(DiscoverException::class);
        ServiceList::setDiscoverConfig([
            "discoverMode" => "fabio",           // Anser load balance provides "none"、"default" and "fabio" options to implement service discovery.
            "default" => [
                'HttpClient' => ServiceList::getHttpClient(),
                'Address'    => $this->consulService,     // [required]
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
            ]
        ]);
    }
}
