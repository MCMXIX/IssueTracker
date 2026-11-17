<?php

namespace App\Enums;

enum IssueSummaryStatus : string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
}