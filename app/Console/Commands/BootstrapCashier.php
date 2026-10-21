<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapCashier extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kasir:bootstrap-user {--email= : Initial cashier/admin user email} {--name= : Initial cashier/admin user name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bootstrap the initial cashier user when the database has no users.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email');
        $name = $this->option('name');

        if (blank($email) || blank($name)) {
            $this->error('Bootstrap refused: Explicit --email and --name must both be provided.');
            return self::FAILURE;
        }

        $env = (string) app()->environment();
        $dbConnection = (string) config('database.default');
        $dbName = (string) (config("database.connections.{$dbConnection}.database") ?? '');

        $isTestingMemory = $env === 'testing' && $dbConnection === 'sqlite' && $dbName === ':memory:';
        $isDedicatedDatabase = $dbConnection === 'mysql' && (
            ($env === 'production' && $dbName === 'kasir_bangunan_prod')
            || ($env === 'staging' && $dbName === 'kasir_bangunan_stage')
        );
        if (! $isTestingMemory && ! $isDedicatedDatabase) {
            $this->error('Bootstrap refused: use the dedicated cashier database or disposable SQLite :memory: test.');
            return self::FAILURE;
        }

        if (DB::table('users')->exists()) {
            $this->error('Bootstrap refused: Users table is not empty. Cannot bootstrap initial user.');
            return self::FAILURE;
        }

        $password = $this->secret('Enter initial user password');

        if (blank($password)) {
            $this->error('Bootstrap refused: Password cannot be empty.');
            return self::FAILURE;
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            $this->error('Bootstrap refused: invalid name, email, or password.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password) {
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
        });

        $this->info('Initial user created successfully.');
        return self::SUCCESS;
    }
}
