<?php namespace Zen\Chub\ReportWidgets;

use Backend\Classes\ReportWidgetBase;
use Exception;

/**
 * Deploy report widget — кнопка для запуска make deploy с Dashboard
 */
class Deploy extends ReportWidgetBase
{
    protected $defaultAlias = 'deploy';

    protected function loadAssets()
    {
        $this->addCss('css/deploy.css');
    }

    public function render()
    {
        $this->loadAssets();
        try {
            $this->vars['deployUrl'] = \Backend::url('zen/chub/deploy/ondeploy');
        } catch (Exception $ex) {
            $this->vars['error'] = $ex->getMessage();
        }

        return $this->makePartial('widget');
    }

    public function defineProperties()
    {
        return [
            'title' => [
                'title' => 'Заголовок',
                'default' => 'Деплой',
                'type' => 'string',
            ],
        ];
    }
}
