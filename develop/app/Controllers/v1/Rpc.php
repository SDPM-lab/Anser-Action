<?php 
namespace App\Controllers\V1;
use App\Controllers\BaseController;
use SDPMlab\Anser\Service\Action;
use Psr\Http\Message\ResponseInterface;
use CodeIgniter\HTTP\RequestTrait;
use SDPMlab\Anser\Exception\ActionException;
use \SDPMlab\Anser\Service\ConcurrentAction;
use SDPMlab\Anser\Service\ServiceList;
class Rpc extends BaseController
{
    use RequestTrait;

    public function doSingleAction()
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

        $data = $action->do()->getMeaningData();
        return $this->response->setStatusCode(200)->setJSON($data);
    }   

    public function doConcurrentAction()
    {
        $action = (new Action(
            "http://140.127.74.161:9601",
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
}

?>