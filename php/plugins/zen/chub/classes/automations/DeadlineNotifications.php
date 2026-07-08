<?php namespace Zen\Chub\Classes\Automations;

use Zen\Chub\Classes\Connectors\Uon;
use Zen\Chub\Classes\System\Stream;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\Files;
use Zen\Chub\Classes\System\BasesApp;

class DeadlineNotifications
{
    public static function make()
    {
        return new self();
    }

    # uid: dl.queryRequestsList
    # Zen.Chub.Classes.Automations.DeadlineNotifications.getPages
    public function getPages()
    {
        BasesApp::connect('uon-requests')->clear();

        $range = $this->getDatesRange();
        $page1 = Uon::make()->queryRequestsList($range->date_from, $range->date_to);
        $pages_all = intval($page1['pages_all']); // Тут получено целое число

        $pages = [];
        for ($i = 0; $i<$pages_all; $i++) {
            $pages[] = [
                'page' => $i + 1
            ];
        }
        return $pages;
    }

    private function getDatesRange(): object
    {
        $now = now();
        return (object) [
            'date_to' => $now->format('Y-m-d'),
            'date_from' => $now->copy()->subDays(550)->format('Y-m-d')
        ];
    }

    # uid: dl.handlePage
    # Zen.Chub.Classes.Automations.DeadlineNotifications.handlePage
    public function handlePage(array $data)
    {
        $page_num = $data['page'];
        $range = $this->getDatesRange();
        $page = Uon::make()->queryRequestsList($range->date_from, $range->date_to, $page_num);
        foreach($page['requests'] as $request) {
            $this->addToBase($request);
        }
    }

    private function addToBase(array $request)
    {
        $base = BasesApp::connect('uon-requests');
        $base->query()->insert([
            'request_id' => $request['id'],
            'data' => Transformers::make()->toJson($request)
        ]);
    }

    public function handleRecords()
    {
        $base = BasesApp::connect('uon-requests');

        $records = $base->query()->get();
        foreach ($records as $record) {
            $record_data = Transformers::make()->fromJson($record->data);
            # Пример payment_deadline_client отличного от null: "2026-02-11 00:00"
            if ($record_data['payment_deadline_client']) {
                //$record_data['status_pay_name']
            }
        }
    }
}