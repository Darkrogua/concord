<?php namespace Zen\Act\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Acts extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['zen.act.manage_acts'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Zen.Act', 'act', 'acts');
    }

    public function listExtendQuery($query): void
    {
        $query->orderByDesc('updated_at')->orderBy('name');
    }
}
