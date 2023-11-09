<?php 
namespace App\Controllers\V1;
use App\Controllers\BaseController;
use SDPMlab\Anser\Service\Action;
use Psr\Http\Message\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use SDPMlab\Anser\Exception\ActionException;
use \SDPMlab\Anser\Service\ConcurrentAction;
use SDPMlab\Anser\Service\ServiceList;

class Rpc extends BaseController
{
    use ResponseTrait;

    public function rpcServer()
    {
        $data = $this->request->getBody();
        $server = new \Datto\JsonRpc\Server(new \App\Controllers\V1\RpcApi());
        $reply = $server->reply($data);
        return $this->response->setStatusCode(200)->setJSON($reply);
    }

    public function error429RpcServer()
    {
        $data = $this->request->getBody();
        $server = new \Datto\JsonRpc\Server(new \App\Controllers\V1\RpcApi());
        $reply = $server->reply($data);
        return $this->response->setStatusCode(429)->setJSON($reply);
    }

    public function error500RpcServer()
    {
        $data = $this->request->getBody();
        $server = new \Datto\JsonRpc\Server(new \App\Controllers\V1\RpcApi());
        $reply = $server->reply($data);
        return $this->response->setStatusCode(500)->setJSON($reply);
    }

    public function doSingleAction()
    {
        $action = (new Action(
            "http://localhost:8080",
            "POST",
            "/api/v1/rpcServer"
        ))
        ->setTimeout(5)
        ->setBatchRpcQuery([
            ["add",[1,2]],
            ["add",[1,2]],
        ])
        ->doneHandler(static function(
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $rpcResponse = $runtimeAction->getRpcResponse();
            $rpcResultArr = $runtimeAction->getRpcResult();
            $rpcIdArr = $runtimeAction->getRpcId();
            $runtimeAction->setMeaningData([
                "response" => $rpcResponse,
                "rpcResultArr" => $rpcResultArr,
                "rpcIdArr" => $rpcIdArr
            ]);
        })
        ->failHandler(function (
            ActionException $e
        ){
            if ($e->isRpcError()) {
                $errorResArr = $e->getErrorRpc();
                $successResArr = $e->getSuccessRpc();
                $result = [];
                foreach ($errorResArr as $errorRes) {
                    $result["error"][] = [
                        "Id" => $errorRes->getId(),
                        "msg" => $errorRes->getMessage(),
                        "code" => $errorRes->getCode(),
                        "data" => $errorRes->getData()
                    ];
                }
                foreach ($successResArr as $successRes) {
                    $result["success"][] = [
                        "Id" => $successRes->getId(),
                        "result" => $successRes->getValue(),
                    ];
                }
                $e->getAction()->setMeaningData([
                    "code" => 400,
                    "result" => $result
                ]);
            }
        });

        $data = $action->do()->getMeaningData();
        return $this->response->setStatusCode(200)->setJSON($data);
    }   

    public function doConcurrentAction()
    {
        $action = (new Action(
            env('serviceAddress'),
            "GET",
            "/"
        ))
        ->setRpcQuery("/producth/index",[])
        ->doneHandler(static function(
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $body = ServiceList::getRpcClient()->decode($response->getBody())[0]->getValue();
            $runtimeAction->setMeaningData($body);

        })
        ->failHandler(function (
            ActionException $e
        ){
            if ($e->isRpcMethodError()) {
                $e->getAction()->setMeaningData([
                    "code" => 400,
                    "msg" => $e->getRpcCode()
                ]);
            }
        });

        $action1 = (new Action(
            env('serviceAddress'),
            "POST",
            "/"
        ))
        ->setRpcQuery("/producth/index",[])
        ->doneHandler(static function(
            ResponseInterface $response,
            Action $runtimeAction
        ) {
            $body = ServiceList::getRpcClient()->decode($response->getBody())[0]->getValue();
            $runtimeAction->setMeaningData($body);

        })
        ->failHandler(function (
            ActionException $e
        ){
            if ($e->isRpcMethodError()) {
                $e->getAction()->setMeaningData([
                    "code" => 400,
                    "msg" => $e->getRpcCode()
                ]);
            }
        });

        $concurrent = new ConcurrentAction();
        $concurrent->setActions([
            "action0" => $action,
            "action1" => $action1,
        ])->send();
        $data = ($concurrent->getActionsMeaningData());
        return $this->response->setStatusCode(200)->setJSON($data);
    }

    public function doNativeAction()
    {
        $action = (new Action(
            "http://localhost:8080",
            "GET",
            "/api/v1/user"
        ))->doneHandler(function(
            ResponseInterface $response,
            Action $runtimeAction
        ){
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);
            $runtimeAction->setMeaningData($data);
        });
        
        $data = $action->do()->getMeaningData();
        return $this->response->setStatusCode(200)->setJSON($data);
    }
}

?>