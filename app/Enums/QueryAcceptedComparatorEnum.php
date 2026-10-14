<?php

namespace App\Enums;

enum QueryAcceptedComparatorEnum: string
{
    case EQUAL = '=';
    case NOTEQUAL = '!=';
    case LIKE = 'like';
    case ILIKE = 'ilike';
    case GREATER = '>';
    case SMALLER = '<';
}
