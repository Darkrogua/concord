<?php namespace Zen\Chub\Classes\Automations;

use Illuminate\Support\Facades\Cache;
use Zen\Chub\Classes\Connectors\RocketChatBot;
use Zen\Chub\Classes\Connectors\TelegramBot;
use Zen\Chub\Classes\Connectors\Uon;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\BasesApp;
use Zen\Chub\Classes\System\StatesApp;
use Zen\Chub\Classes\Support\Strings;

class UonPaymentsAutomation
{
    private string $locks_dir;

    public function __construct()
    {
        $this->locks_dir = storage_path('chub/locks');
    }

    public static function make(): self
    {
        return new self();
    }

    # Dotpath: Zen.Chub.Classes.Automations.UonPaymentsAutomation.handle
    public function handle()
    {
        $this->withLock('uon-payments-handle.lock', function () {
            $uids = array_values(array_unique($this->getDayPayments()));

            $primary_index = $this->getTodayPrimaryCount();

            foreach ($uids as $uid) {
                $this->withLock("uon-payments-uid-{$uid}.lock", function () use ($uid, &$primary_index) {
                    $record = $this->getRecord($uid);

                    if ($record) {
                        return;
                    }

                    $send_data = $this->handlePayment($uid);

                    if (!$send_data) {
                        return;
                    }

                    $is_inserted = $this->addRecord($send_data, $uid);
                    if ($is_inserted) {
                        $notification_primary_index = null;
                        if ($send_data['payment_status'] === 'Первичный') {
                            $primary_index++;
                            $notification_primary_index = $primary_index;
                        }
                        $this->sendNotification($send_data, $notification_primary_index, $uid);
                    }
                });
            }
        });
    }

    private function getRecord(string $uid): ?object
    {
        return BasesApp::connect('uon-payments')->query()->where('uid', $uid)->first();
    }

    private function addRecord(array $send_data, string $uid): bool
    {
        $order_id = $send_data['request_data']['id_internal'];
        $record = $this->getRecord($uid);

        if ($record) {
            return false;
        }

        BasesApp::connect('uon-payments')->query()->insert([
            'uid' => $uid,
            'order_id' => $order_id,
            'request_data' => Transformers::make()->toJson($send_data['request_data']),
            'created_at' => now()->toDateTimeString(),
            'is_primary' => $send_data['payment_status'] === 'Первичный' ? 1 : 0
        ]);

        return true;
    }

    private function handlePayment(string $uid): ?array
    {
        $ids = explode('-', $uid);
        $request_id = intval($ids[0]);
        $payment_id = intval($ids[1]);

        $request = $this->getUonRequest($request_id);

        $request_payments = $request['payments'];

        $this->filterPayments($request_payments);
        $this->sortPayments($request_payments);

        $payment_index = 0;
        $payment = null;

        foreach ($request_payments as $record) {
            if (intval($record['id']) === $payment_id) {
                $payment = $record;
                break;
            }
            $payment_index++;
        }

        if (!$payment) {
            return null;
        }

        $date_create = $payment['date_create'];
        $date_create = Transformers::make()->carbon($date_create)->format('d.m.Y');

        $payment_status = !$payment_index ? 'Первичный' : 'Доплата';
        $cio_name = $payment['cio_name']; # Тип операции

        if ($cio_name === 'Расход') {
            $cio_name = '🔴 Возврат';
            $payment_status = '';
        } else {
            $cio_name = "🟢 $cio_name";
        }

        $price = $payment['price'];
        $price = Strings::make()->priceFormatter($price);
        $client_id = $payment['client_id'];

        return [
            'request_data' => $request,
            'payment_status' => $payment_status,
            'cio_name_formated' => $cio_name,
            'cio_name' => $payment['cio_name'],
            'price' => $price,
            'client_id' => $client_id,
            'date_create' => $date_create,
        ];
    }

    private function filterPayments(array &$payments)
    {
        $payments = collect($payments)
            ->filter(function ($item) {
                return ($item['type_name'] ?? '') === 'Расчеты с клиентом';
            })
            ->values()
            ->toArray();
    }

    private function sortPayments(&$payments)
    {
        $payments = collect($payments)
            ->sortBy(function ($payment) {
                $date_create = trim((string) ($payment['date_create'] ?? ''));
                return $date_create ? strtotime($date_create) : 0;
            })
            ->values()->toArray();
    }

    private function getDatesRange(): object
    {
        $lookback_days = intval(env('CHUB_UON_PAYMENTS_LOOKBACK_DAYS', 14));
        if ($lookback_days < 0) {
            $lookback_days = 0;
        }

        $now = now();
        return (object) [
            'date_from' => $now->copy()->subDays($lookback_days)->format('Y-m-d'),
            'date_to' => $now->copy()->addDay()->format('Y-m-d'),
        ];
    }

    private function getDayPayments()
    {
        $dates = $this->getDatesRange();
        $all_payments = [];
        $page = 1;

        while (true) {
            $response = Uon::make()->queryPayments(
                $dates->date_from,
                $dates->date_to,
                $page
            );

            $payments = $response['payments'] ?? [];
            if (empty($payments)) {
                break;
            }

            $all_payments = array_merge($all_payments, $payments);
            $page++;
        }

        return $this->extractingIdentifiers($all_payments);
    }

    private function makeUid(int $request_id, int $pyment_id): string
    {
        return "$request_id-$pyment_id";
    }

    private function extractingIdentifiers(array $payments): array
    {
        $data = [];
        foreach ($payments as $payment) {

            if (!$payment['r_id']) {
                continue;
            }

            if ($payment['type_name'] !== 'Расчеты с клиентом') {
                continue;
            }

            $uid = $this->makeUid($payment['r_id'], $payment['id']);
            $data[] = $uid;
        }
        return $data;
    }

       /**
     * Запрос деталей заявки по номеру заявки
     * @param int $request_id - Номер заявки
     * @return array
     */
    private function getUonRequest(int $request_id): array
    {
        $requests = Uon::make()->queryRequest($request_id);
        $request = $requests['request'][0];
        return $request;
    }

    private function sendNotification(
        array $send_data,
        ?int $primary_index,
        string $uid
    ) {
        $request_id = $send_data['request_data']['id'];
        $request_data = $send_data['request_data'];
        $cio_name = $send_data['cio_name_formated'];
        $price = $send_data['price'];
        $payment_status = $send_data['payment_status'];
        $uon_link = "https://id23393.u-on.ru/request_edit.php?r_id=$request_id";
        $id_internal = $request_data['id_internal'];
        $client_name = $request_data['client_name'];
        $client_sname = $request_data['client_sname'];
        $client_surname = $request_data['client_surname'];
        $manager_surname = $request_data['manager_surname'];
        $notes = $request_data['notes'];
        $amo_link = trim(str_replace('Ссылка на сделку в amoCRM:', '', $notes));
        $date_create = $send_data['date_create'];

        if ($payment_status === 'Первичный' && $primary_index !== null) {
            $payment_status = "<b>Первичный от $date_create (№$primary_index)</b>";
        } else {
            $payment_status = $payment_status . " от $date_create";
        }

        # Формируем сообщение
        $message = "$cio_name\n<b>$price руб</b> $payment_status\n<a href='$uon_link'>№$id_internal</a>" .
        " - $client_surname $client_name $client_sname\n🤵‍♀️ $manager_surname";

        $hash_payload = [
            'uid' => $uid,
            'message' => $message,
        ];
        $message_hash = md5(Transformers::make()->toJson($hash_payload, false, true));
        $cache_key = "uon-payments:tg:{$message_hash}";
        $ttl_seconds = intval(env('CHUB_TG_DEDUP_TTL_SECONDS', 86400));
        if ($ttl_seconds < 60) {
            $ttl_seconds = 60;
        }

        $this->withLock("uon-payments-tg-{$message_hash}.lock", function () use ($cache_key, $ttl_seconds, $message) {
            $is_new_message = Cache::add($cache_key, 1, now()->addSeconds($ttl_seconds));
            if (!$is_new_message) {
                return;
            }

            if (boolval(StatesApp::getSetting('notify-settings.atm_rocket_chat_bot_enabled'))) {
                RocketChatBot::make()->sendMessage($message);
            }

            if (boolval(StatesApp::getSetting('notify-settings.atm_telegram_bot_enabled'))) {
                TelegramBot::make()->sendMessage($message);
            }
        });

        # Пока не используется
        $amo_data = [
            'request_id' => $request_id,
            'id_internal' => $id_internal,
            'type_name' => 'Расчеты с клиентом',
            'cio_name' => $send_data['cio_name'] === 'Расход' ? 'Возврат' : $send_data['cio_name'],
            'price' => $price,
            'client_id' => $send_data['client_id'],
            'client_surname' => $client_surname,
            'client_name' => $client_name,
            'client_sname' => $client_sname,
            'manager_surname' => $manager_surname,
            'amo_link' => $amo_link,
            'dat_request' => $request_data['dat_request'],
        ];
    }

    private function getTodayPrimaryCount(): int
    {
        $date_from = now()->startOfDay()->toDateTimeString();
        $date_to = now()->endOfDay()->toDateTimeString();

        return BasesApp::connect('uon-payments')->query()
            ->where('is_primary', 1)
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            ->count();
    }

    private function withLock(string $lock_file_name, callable $callback): void
    {
        if (!is_dir($this->locks_dir)) {
            @mkdir($this->locks_dir, 0775, true);
        }

        $lock_path = $this->locks_dir . '/' . $lock_file_name;
        $handle = @fopen($lock_path, 'c');
        if (!$handle) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                return;
            }
            $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}