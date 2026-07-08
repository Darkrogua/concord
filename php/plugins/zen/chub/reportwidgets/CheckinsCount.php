<?php namespace Zen\Chub\ReportWidgets;

use Backend;
use Backend\Classes\ReportWidgetBase;
use Exception;
use Zen\Chub\Models\Checkin;

/**
 * Виджет Dashboard: число заездов (записей в zen_chub_checkins).
 */
class CheckinsCount extends ReportWidgetBase
{
    protected $defaultAlias = 'checkins_count';

    protected function loadAssets()
    {
        $this->addCss('css/checkins_count.css');
    }

    public function render()
    {
        $this->loadAssets();

        try {
            $this->vars['checkins_count'] = Checkin::count();
            $this->vars['list_url'] = Backend::url('zen/chub/checkins');
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
                'default' => 'Заезды в базе',
                'type' => 'string',
                'validationPattern' => '^.+$',
                'validationMessage' => 'backend::lang.dashboard.widget_title_error',
            ],
        ];
    }
}
