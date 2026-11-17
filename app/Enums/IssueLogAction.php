<?php

namespace App\Enums;

enum IssueLogAction : string
{
    case Created = 'created';
    case Updated = 'updated';
    case MarkedAsHighPriority = 'marked_as_high_priority';
    case Deleted = 'deleted';
}