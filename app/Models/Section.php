<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    // Ito ang mga fields na pinapayagan nating malagyan ng laman
    protected $fillable = [
        'teacher_id',
        'section_name',
        'class_code',
    ];
}