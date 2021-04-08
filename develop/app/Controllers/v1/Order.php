<?php namespace App\Controllers\V1;

use CodeIgniter\RESTful\ResourceController;

class Order extends ResourceController
{
    
    protected $format    = 'json';

    public function show($orderID = null){
        return $this->respond([
            "data" => [
                "products_id" => [1,2,3,4],
                "created_time" => "2021-04-05 10:15:55",
                "total_price" =>2156
            ],
            "msg" => "show method successful."
        ]);
    }

    public function create(){
        $data = $this->request->getJSON(true);
        if($data == null){
            return $this->failValidationError("data not found",400);
        }
        return $this->respondCreated([
            "status" => true,
            "data" => $data,
            "msg" => "create method successful."
        ]);
    }

    public function update($id = null){
        $data = $this->request->getJSON(true);
        return $this->respond([
            "status" => true,
            "id" => $id,
            "data" => $data,
            "msg" => "update method successful."
        ]);
    }

    public function new(){
        return "newView";
    }

    public function edit($id = null){
        return $id."editView";
    }

    public function delete($id = null){
        return $this->respondDeleted([
            "status" => true,
            "id" => $id,
            "msg" => "delede method successful."
        ]);
    }

}