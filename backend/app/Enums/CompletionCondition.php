<?php

namespace App\Enums;

enum CompletionCondition: string
{
    case All = 'all';
    case Majority = 'majority';
}
