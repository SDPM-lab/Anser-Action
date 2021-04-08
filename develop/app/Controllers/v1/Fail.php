<?php namespace App\Controllers\V1;

use CodeIgniter\RESTful\ResourceController;

class Fail extends ResourceController
{
    
    protected $format    = 'json';

    public function awayls429()
    {
        return $this->fail("Too Many Requests", 429);
    }

    public function awayls500($num)
    {
        return $this->fail("Internal Server Error", 500);
    }

    public function maySuccess()
    {
        //刻意創造失敗，測試案例必須儲存初次請求的 SESSION ID
        $session = \CodeIgniter\Config\Services::session();
        if ($requestNum = $session->get("requestNum")) {
            if ($requestNum < 1) {
                return $this->fail("Too Many Requests", 429);
            } else {
                $session->set("requestNum", $requestNum++);
            }
        } else {
            $session->set("requestNum", 1);
        }
        //$jsonData = $this->request->getJson(true);
        return $this->respond([
            "id" => 3,
            "msg" => "created"
        ], 201);
    }

}