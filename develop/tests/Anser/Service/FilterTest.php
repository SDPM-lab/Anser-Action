<?php

namespace SDPMlab\Anser\Service;

use CodeIgniter\Test\CIUnitTestCase;
use SDPMlab\Anser\Service\ServiceList;
use SDPMlab\Anser\Service\Action;
use SDPMlab\Anser\Service\ActionFilter;
use SDPMlab\Anser\Service\ActionInterface;
use SDPMlab\Anser\Service\RequestSettings;
use App\Anser\Filters\UserAuthFilters;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\Utils;

class FilterTest extends CIUnitTestCase
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
        ServiceList::setLocalServices([
            $this->testService1, $this->testService2
        ]);
        ActionFilter::resetGlobalFilter();
        unset($GLOBALS["filter"]);
    }

    public function testGlobalsFilter()
    {
        ActionFilter::setGlobalFilter(UserAuthFilters::class);
        $action = new Action("testService1","GET","/api/v1/user/1");
        $action->do();
        $data = json_decode($action->getResponse()->getBody()->getContents(),true);
        $isLogin = $data["X-User-Islgoin"];
        $userID = $data["X-User-Key"];
        $this->assertEquals(true,$isLogin);
        $this->assertEquals("1",$userID);
        $this->assertEquals($GLOBALS["filter"],true);
    }

    public function testAddActionBeforeFilter()
    {
        $action = new Action("testService1","GET","/api/v1/user/1");
        $action->addBeforeFilter(UserAuthFilters::class)
               ->do();
        $data = json_decode($action->getResponse()->getBody()->getContents(),true);
        $isLogin = $data["X-User-Islgoin"];
        $userID = $data["X-User-Key"];
        $this->assertEquals(true,$isLogin);
        $this->assertEquals("1",$userID);
    }

    public function testAddActionAfterFilter()
    {
        $action = new Action("testService1","GET","/api/v1/user");
        $action->addAfterFilter(UserAuthFilters::class)
               ->do();
        $this->assertEquals($GLOBALS["filter"],true);
    }

    public function testSetActionFilter()
    {
        $action = new Action("testService1","GET","/api/v1/user/1");
        $action->setFilter(UserAuthFilters::class)->do();
        $data = json_decode($action->getResponse()->getBody()->getContents(),true);
        $isLogin = $data["X-User-Islgoin"];
        $userID = $data["X-User-Key"];
        $this->assertEquals(true,$isLogin);
        $this->assertEquals("1",$userID);
        $this->assertEquals($GLOBALS["filter"],true);
    }

}
