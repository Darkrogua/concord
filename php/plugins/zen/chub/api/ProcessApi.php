<?php namespace Zen\Chub\Api;

use Zen\Chub\Models\Flow;
use Zen\Chub\Classes\System\ProcessApp;

class ProcessApi extends Api
{
    # http://axis/chub.api/ProcessApi:dev
    public function dev()
    {
        dd('test');
    }

    # http://axis/chub.api/ProcessApi:getFlowData?flow_id=1
    public function getFlowData()
    {
        $flow = Flow::find(input('flow_id'));
        $process_app = ProcessApp::make($flow);
        return [
            'flow' => $process_app->getFlowData()
        ];
    }

    # http://axis/chub.api/ProcessApi:getFlowStates?flow_id=1
    public function getFlowStates()
    {
        $flow = Flow::find(input('flow_id'));
        $process_app = ProcessApp::make($flow);
        return [
            'states' => $process_app->getFlowStates()
        ];
    }

    # http://axis/chub.api/ProcessApi:createNewProcess?flow_id=1
    public function createNewProcess()
    {
        $this->requireCsrf();
        $flow = Flow::find(input('flow_id'));
        $process_app = ProcessApp::make($flow);
        $process_app->createProcess();
        return [
            'success' => true
        ];
    }

    # http://axis/chub.api/ProcessApi:deleteProcess?flow_id=1&process_index=3
    public function deleteProcess()
    {
        $this->requireCsrf();
        $flow = Flow::find(input('flow_id'));
        $process_app = ProcessApp::make($flow);
        $process_app->deleteProcess(input('process_index'));
        return [
            'success' => true
        ];
    }

    # http://axis/chub.api/ProcessApi:runProcess?flow_id=1&process_uid=test.process
    # Запустить конкретный процесс по flow_id и process_uid;
    # Фактически это происходит только при нажатии на кнопку play микропанели управления потоком;
    # Повторное нажатие останавливает поток (только если он пакетный);
    # Повторное нажатие на завершённый поток, удаляет его состояние и запускает заново (только когда вручную, через этот метод);
    public function runProcess()
    {
        $this->requireCsrf();
        $flow = Flow::find(input('flow_id'));
        $process_app = ProcessApp::make($flow);
        $process_app->runProcess(input('process_uid'));
        return [
            'success' => true
        ];
    }

    # http://axis/chub.api/ProcessApi:killProcess?flow_id=1&process_uid=xxx
    public function killProcess()
    {
        $this->requireCsrf();
        $flow = Flow::find(input('flow_id'));
        $process_app = ProcessApp::make($flow);
        $process_app->killProcess(input('process_uid'));
        return [
            'success' => true
        ];
    }

    # http://axis/chub.api/ProcessApi:clearProcess?flow_id=1&process_uid=xxx
    public function clearProcess()
    {
        $this->requireCsrf();
        $flow = Flow::find(input('flow_id'));
        $process_app = ProcessApp::make($flow);
        $process_app->killProcess(input('process_uid'), true);
        return [
            'success' => true
        ];
    }

    # http://axis/chub.api/ProcessApi:moveProcess?flow_id=1&process_uid=xxx&direction=up
    public function moveProcess()
    {
        $this->requireCsrf();
        $flow = Flow::find(input('flow_id'));
        $process_app = ProcessApp::make($flow);
        $process_app->moveProcess(input('process_uid'), input('direction'));
        return [
            'success' => true
        ];
    }
}