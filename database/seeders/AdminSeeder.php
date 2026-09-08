<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([

            'name' => 'System Admin',

            'email' => 'admin@readsmart.com',

            'lrn' => null,

            'password' => Hash::make('admin123'),

            'role' => 'admin',

            'grade_level' => null,

            'section' => null,

            'first_login' => false,

            'created_at' => now(),

            'updated_at' => now(),
        ]);
    }
}