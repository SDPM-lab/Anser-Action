<?php

namespace App\Controllers\V1;

use CodeIgniter\RESTful\ResourceController;

class User extends ResourceController
{

    protected $format    = 'json';

    public function index()
    {
        return $this->respond([
            "msg" => "success",
            "data" => [
                [
                    "id" => 1,
                    "name" => "amos",
                    "age" => 24
                ],
                [
                    "id" => 2,
                    "name" => "andy",
                    "age" => 25
                ]
            ]
        ]);
    }

    public function show($id = 1)
    {
        $isLogin = $this->request->getHeaderLine("X-User-Islgoin") === "true" ? true : false;
        if (!$isLogin) return $this->failUnauthorized();
        $userID = $this->request->getHeaderLine("X-User-Key");
        if ($userID != $id) return $this->failForbidden();
        return $this->respond([
            "X-User-Islgoin" => $isLogin,
            "X-User-Key" => $userID,
            "data" => [
                "id" => 1,
                "name" => "amos",
                "age" => 24
            ],
            "msg" => "success"
        ]);
    }

}
