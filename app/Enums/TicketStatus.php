<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'Open';
    case InProgress = 'In Progress';
    case Pending = 'Pending';
    case Resolved = 'Resolved';
    case Closed = 'Closed';
}
