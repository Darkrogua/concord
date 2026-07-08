<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;

class Cabins extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Zen.Chub', 'chub');
    }

    public function listExtendSortColumn($query, $sort_column, $sort_direction): void
    {
        if ($sort_column === 'deck_name') {
            $query->reorder();
            $query->leftJoin('zen_chub_decks', 'zen_chub_cabins.deck_id', '=', 'zen_chub_decks.id')
                ->orderBy('zen_chub_decks.name', $sort_direction)
                ->select('zen_chub_cabins.*');
        }
        elseif ($sort_column === 'grade_name') {
            $query->reorder();
            $query->leftJoin('zen_chub_grades', 'zen_chub_cabins.grade_id', '=', 'zen_chub_grades.id')
                ->orderBy('zen_chub_grades.name', $sort_direction)
                ->select('zen_chub_cabins.*');
        }
        elseif ($sort_column === 'ship_name') {
            $query->reorder();
            $query->leftJoin('zen_chub_grades', 'zen_chub_cabins.grade_id', '=', 'zen_chub_grades.id')
                ->leftJoin('zen_chub_ships', 'zen_chub_grades.ship_id', '=', 'zen_chub_ships.id')
                ->orderBy('zen_chub_ships.name', $sort_direction)
                ->select('zen_chub_cabins.*');
        }
    }

}
