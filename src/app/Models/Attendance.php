<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\AttendanceStatus;

class Attendance extends Model
{
    use HasFactory;

    protected $casts = [
        'status' => AttendanceStatus::class,
    ];

    protected $fillable = [
        'user_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'note',
        'approver_id',
        'approved_at',
    ];

    public function breaktimes()
    {
        return $this->hasMany(Breaktime::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
