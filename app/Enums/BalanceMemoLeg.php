<?php

namespace App\Enums;

enum BalanceMemoLeg: string
{
    case Single = 'single';
    case Source = 'source';
    case Target = 'target';
}
