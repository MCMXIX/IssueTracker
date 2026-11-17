<?php

namespace App\Enums;

enum IssueCategory : string
{
    case Billing = 'billing';
    case Access = 'access';
    case Operations = 'operations';
}