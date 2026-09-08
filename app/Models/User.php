<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail; // <-- Idagdag ito
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail // <-- Idagdag ang MustVerifyEmail
{
    use Notifiable;

    protected $fillable = [
        'name',
        'parent_id',
        'email',
        'lrn',
        'password',
        'role',
        'grade_level',
        'section',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Idagdag ang casts para mabasang datetime ang verification
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_student', 'student_id', 'class_id')->withTimestamps();
    }

    public function progress()
    {
        return $this->hasMany(StudentProgress::class, 'user_id');
    }
}