<?php

namespace SDPMlab\Anser\Service;

use CodeIgniter\Test\CIUnitTestCase;
use App\Anser\Services\UserService;

class ServiceTest extends CIUnitTestCase
{

    public $userService = [
        "name" => "userService",
        "address" => "localhost",
        "port" => 8080,
        "isHttps" => false
    ];

    public $orderService = [
        "name" => "orderService",
        "address" => "localhost",
        "port" => 8081,
        "isHttps" => true
    ];

    public $paymentService = [
        "name" => "paymentService",
        "address" => "localhost",
        "port" => 8082,
        "isHttps" => true
    ];

    protected function setUp(): void
    {
        parent::setUp();
        ServiceList::cleanServiceList();
        ServiceList::setLocalServices([
            $this->userService, $this->orderService, $this->paymentService
        ]);
    }

    public function testService()
    {
        $userService = new UserService;
        $userData = $userService->getUserData(1)->do()->getMeaningData();
        $this->assertIsArray($userData);
        $this->assertArrayHasKey("id",$userData);
        $this->assertArrayHasKey("name",$userData);
        $this->assertArrayHasKey("age",$userData);
    }

}
