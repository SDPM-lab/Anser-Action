<?php namespace App\Controllers\V1;

use CodeIgniter\RESTful\ResourceController;

class Payment extends ResourceController
{
    
    protected $format    = 'json';

    public function index(){
        return $this->respond([
            "status" => true,
            "msg" => "index method successful."
        ]);
    }

    public function show($orderID = null){
        return $this->respond([
            "data" => [
                "status" => "success",
                "handling_fee" => "15",
                "mode" => "atm",
                "provide" => "ECPpay",
                "pay_time" => "2021-04-06 10:15:55"
            ],
            "msg" => "show method successful."
        ]);
    }

}