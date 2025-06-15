<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-test-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test user for admin access';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! User::where('email', 'admin@example.com')->exists()) {
            $user = User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $this->info('Test user created: admin@example.com / password');
        } else {
            $this->info('Test user already exists: admin@example.com / password');
        }

        $this->info('Admin panel: http://localhost:8000/admin');
        $this->info('Tool Management: http://localhost:8000/admin/tool-management');
    }
}
