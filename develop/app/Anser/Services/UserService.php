<?php

namespace App\Anser\Services;

use SDPMlab\Anser\Service\SimpleService;
use SDPMlab\Anser\Service\ActionInterface;
use app\Anser\Filters\UserAuthFilters;

class UserService extends SimpleService
{
    protected $serviceName = "userService";
    protected $filters = [
        "before" => [
            UserAuthFilters::class
        ],
        "after" => [
            UserAuthFilters::class
        ]
    ];
    protected $retry = 1;
    protected $retryDelay = 0.5;
    protected $timeout = 3.0;
    
    /**
     * 取得使用者清單
     *
     * @return void
     */
    public function getUserList()
    {
        $action = $this->getAction("GET","/api/v1/user")
            ->setMeaningDataHandler(function(ActionInterface $runtimeAction){
                $data = json_decode($runtimeAction->getResponse()->getBody()->getContents(),true);
                $meaningData = $data["data"];
                return $meaningData;
            }
        );
        return $action;
    }

    /**
     * 取得使用者資訊
     *
     * @param integer $id userID
     * @return ActionInterface
     */
    public function getUserData(int $id): ActionInterface
    {
        $action = $this->getAction("GET","/api/v1/user/{$id}")
            ->setMeaningDataHandler(function(ActionInterface $runtimeAction){
                $data = json_decode($runtimeAction->getResponse()->getBody()->getContents(),true);
                $meaningData = $data["data"];
                return $meaningData;
            }
        );
        return $action;
    }

}