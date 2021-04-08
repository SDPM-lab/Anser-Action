<?php

namespace SDPMlab\Anser\Service;

use CodeIgniter\Test\CIUnitTestCase;
use SDPMlab\Anser\Service\ServiceList;
use SDPMlab\Anser\Service\ServiceSettings;

class ServiceListTest extends CIUnitTestCase
{

    public $testService1 = [
        "name" => "testService1",
        "address" => "localhost",
        "port" => 8080,
        "isHttps" => false
    ];

    public $testService2 = [
        "name" => "testService2",
        "address" => "localhost",
        "port" => 8081,
        "isHttps" => true
    ];

    protected function setUp(): void
    {
        parent::setUp();
        ServiceList::cleanServiceList();
    }

    public function testSetLocalServices()
    {
        ServiceList::setLocalServices([
            $this->testService1, $this->testService2
        ]);
        $this->assertIsArray(ServiceList::getServiceList());
        $testServiceData = ServiceList::getServiceData("testService1");
        $testService2Data = ServiceList::getServiceData("testService2");
        $this->assertInstanceOf(ServiceSettings::class, $testServiceData);
        $this->assertInstanceOf(ServiceSettings::class, $testService2Data);
        $this->assertEquals((array)$testServiceData, $this->testService1);
        $this->assertEquals((array)$testService2Data, $this->testService2);
    }

    public function testAddLocalServices()
    {
        ServiceList::addLocalService(
            $this->testService1["name"],
            $this->testService1["address"],
            $this->testService1["port"],
            $this->testService1["isHttps"]
        );
        $testServiceData = ServiceList::getServiceData($this->testService1["name"]);
        $this->assertInstanceOf(ServiceSettings::class, $testServiceData);
        $this->assertEquals((array)$testServiceData, $this->testService1);
    }

    public function testRemoveService()
    {
        ServiceList::addLocalService(
            $this->testService1["name"],
            $this->testService1["address"],
            $this->testService1["port"],
            $this->testService1["isHttps"]
        );
        $testServiceData = ServiceList::getServiceData($this->testService1["name"]);
        $this->assertInstanceOf(ServiceSettings::class, $testServiceData);
        $this->assertEquals((array)$testServiceData, $this->testService1);
        ServiceList::removeService($this->testService1["name"]);
        $testServiceData = ServiceList::getServiceData($this->testService1["name"]);
        $this->assertNull($testServiceData);
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
    }
}
