<?php

namespace App\Enums;

enum AttendanceStatus: int
{
    case Entered = 0;
    case Unapproved = 1;
    case Approved = 2;
    case Rejected = 3;

    public function label()
    {
        return match ($this) {
            self::Entered => '入力済',
            self::Unapproved => '承認待ち',
            self::Approved => '承認済み',
            self::Rejected => '差戻し'
        };
    }
}
