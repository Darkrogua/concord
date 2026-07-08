<?php namespace Zen\Chub\Api;

use Zen\Chub\Classes\System\Stream;

class StreamApi extends Api
{
    # http://axis/chub.api/StreamApi:state?debug&uid=xxx
    public function state(): array | null
    {
        $uid = get('uid');
        $stream = Stream::connect($uid);
        $state_data = $stream->getStateData();

        if (!$state_data) {
            return null;
        }

        $state_data['in_process'] = $stream->inProcess();
        return $state_data;
    }

    # http://axis/chub.api/StreamApi:kill?uid=xxx
    public function kill()
    {
        $this->requireCsrf();
        $uid = get('uid');
        $stream = Stream::connect($uid);
        return [
            'success' => $stream->killStream()
        ];
    }
}