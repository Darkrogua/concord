<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;

class GradeAliases extends Controller
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

    public function listExtendSortColumn($query, $sort_column, $sort_direction)
    {
        $requested_column = post('sortColumn') ?: $this->getListSortColumnFromSession();

        if ($requested_column === 'provider_id') {
            $query->reorder();
            $query->leftJoin('zen_chub_providers', 'zen_chub_grade_aliases.provider_id', '=', 'zen_chub_providers.id')
                ->orderBy('zen_chub_providers.name', $sort_direction)
                ->select('zen_chub_grade_aliases.*');
        }
        elseif ($requested_column === 'target_id') {
            $query->reorder();
            $query->leftJoin('zen_chub_grades', 'zen_chub_grade_aliases.target_id', '=', 'zen_chub_grades.id')
                ->orderBy('zen_chub_grades.name', $sort_direction)
                ->select('zen_chub_grade_aliases.*');
        }
        elseif ($requested_column === 'ship_name') {
            $query->reorder();
            $query->leftJoin('zen_chub_grades', 'zen_chub_grade_aliases.target_id', '=', 'zen_chub_grades.id')
                ->leftJoin('zen_chub_ships', 'zen_chub_grades.ship_id', '=', 'zen_chub_ships.id')
                ->orderBy('zen_chub_ships.name', $sort_direction)
                ->select('zen_chub_grade_aliases.*');
        }
        elseif ($sort_column === 'name') {
            // Fallback: sortColumn='name' из сессии (target_id/provider_id/ship_name → valueFrom)
            $query->reorder();
            $query->leftJoin('zen_chub_grades', 'zen_chub_grade_aliases.target_id', '=', 'zen_chub_grades.id')
                ->leftJoin('zen_chub_ships', 'zen_chub_grades.ship_id', '=', 'zen_chub_ships.id')
                ->leftJoin('zen_chub_providers', 'zen_chub_grade_aliases.provider_id', '=', 'zen_chub_providers.id')
                ->orderByRaw('COALESCE(zen_chub_ships.name, zen_chub_grades.name, zen_chub_providers.name) ' . $sort_direction)
                ->select('zen_chub_grade_aliases.*');
        }
    }

    private function getListSortColumnFromSession(): ?string
    {
        $list_widget = $this->listGetWidget();
        if (!$list_widget) {
            return null;
        }
        $sort_options = $list_widget->getSession('sort');
        return $sort_options['column'] ?? null;
    }
}
