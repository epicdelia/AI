<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Throwable;

class InstallController extends Controller
{
    /**
     * Get server environment and install status.
     */
    public function status(): JsonResponse
    {
        $dbConnected = false;
        $hasUsers = false;
        $dbError = null;

        try {
            DB::connection()->getPdo();
            $dbConnected = true;
            $hasUsers = User::count() > 0;
        } catch (Throwable $e) {
            $dbConnected = false;
            $dbError = $e->getMessage();
        }

        $envPath = base_path('.env');
        $envExists = File::exists($envPath);
        $envWritable = $envExists ? is_writable($envPath) : is_writable(base_path());

        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');

        $extensions = [
            'pdo' => extension_loaded('pdo'),
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'pdo_pgsql' => extension_loaded('pdo_pgsql'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'curl' => extension_loaded('curl'),
            'fileinfo' => extension_loaded('fileinfo'),
            'tokenizer' => extension_loaded('tokenizer'),
        ];

        $storageWritable = is_writable(storage_path());
        $cacheWritable = is_writable(base_path('bootstrap/cache'));
        $databaseWritable = is_writable(database_path());

        $hasAppKey = !empty(config('app.key'));

        return response()->json([
            'installed' => $hasUsers,
            'database' => [
                'connected' => $dbConnected,
                'default' => config('database.default'),
                'error' => $dbError,
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'php_ok' => $phpOk,
                'extensions' => $extensions,
                'storage_writable' => $storageWritable,
                'cache_writable' => $cacheWritable,
                'database_writable' => $databaseWritable,
                'env_exists' => $envExists,
                'env_writable' => $envWritable,
                'has_app_key' => $hasAppKey,
            ],
        ]);
    }

    /**
     * Test a proposed database connection before saving.
     */
    public function testDatabase(Request $request): JsonResponse
    {
        if ($this->installed()) {
            return $this->alreadyInstalled();
        }

        $data = $request->validate([
            'driver' => 'required|string|in:sqlite,mysql,mariadb,pgsql',
            'host' => 'nullable|string',
            'port' => 'nullable|numeric',
            'database' => 'required|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $driver = $data['driver'] === 'mariadb' ? 'mysql' : $data['driver'];
        $database = trim($data['database']);

        try {
            if ($driver === 'sqlite') {
                if ($database === ':memory:') {
                    $pdo = new PDO('sqlite::memory:', null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 3,
                    ]);
                    $pdo->query('SELECT 1');

                    return response()->json([
                        'ok' => true,
                        'message' => 'SQLite in-memory database accessible',
                    ]);
                }

                $path = $database;
                if (!File::isAbsolutePath($path)) {
                    $path = base_path($path);
                }
                $dir = dirname($path);
                if (!File::isDirectory($dir)) {
                    File::makeDirectory($dir, 0755, true, true);
                }
                if (!File::exists($path)) {
                    File::put($path, '');
                }
                $pdo = new PDO("sqlite:{$path}", null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3,
                ]);
                $pdo->query('SELECT 1');

                return response()->json([
                    'ok' => true,
                    'message' => "SQLite database accessible at {$database}",
                ]);
            }

            $host = !empty($data['host']) ? $data['host'] : '127.0.0.1';
            $port = !empty($data['port']) ? (int)$data['port'] : ($driver === 'pgsql' ? 5432 : 3306);
            $user = $data['username'] ?? '';
            $pass = $data['password'] ?? '';

            if ($driver === 'mysql') {
                try {
                    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database}", $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 5,
                    ]);
                    $pdo->query('SELECT 1');
                } catch (Throwable $e) {
                    // If error is 1049 (database doesn't exist), try to connect to server and create it
                    if (str_contains($e->getMessage(), 'Unknown database') || str_contains($e->getMessage(), '1049')) {
                        $rootPdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_TIMEOUT => 5,
                        ]);
                        $cleanDb = preg_replace('/[^a-zA-Z0-9_\-]/', '', $database);
                        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$cleanDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                        return response()->json([
                            'ok' => true,
                            'message' => "Database `{$cleanDb}` was created and connection is ready!",
                        ]);
                    }

                    throw $e;
                }
            } elseif ($driver === 'pgsql') {
                $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$database}", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                $pdo->query('SELECT 1');
            }

            return response()->json([
                'ok' => true,
                'message' => "Connected successfully to {$driver} database `{$database}` on {$host}:{$port}!",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Configure database, write .env, generate key if needed, and run migrations.
     */
    public function setupDatabase(Request $request): JsonResponse
    {
        if ($this->installed()) {
            return $this->alreadyInstalled();
        }

        $data = $request->validate([
            'driver' => 'required|string|in:sqlite,mysql,mariadb,pgsql',
            'host' => 'nullable|string',
            'port' => 'nullable|numeric',
            'database' => 'required|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'app_url' => 'nullable|url',
        ]);

        $driver = $data['driver'] === 'mariadb' ? 'mysql' : $data['driver'];
        $database = trim($data['database']);
        $host = !empty($data['host']) ? $data['host'] : '127.0.0.1';
        $port = !empty($data['port']) ? (int)$data['port'] : ($driver === 'pgsql' ? 5432 : 3306);
        $user = $data['username'] ?? '';
        $pass = $data['password'] ?? '';

        // Test connection first
        if ($driver === 'sqlite') {
            $path = $database;
            if (!File::isAbsolutePath($path)) {
                $path = base_path($path);
            }
            $dir = dirname($path);
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true, true);
            }
            if (!File::exists($path)) {
                File::put($path, '');
            }
        }

        // Prepare .env values
        $envUpdates = [
            'DB_CONNECTION' => $driver,
            'DB_HOST' => $host,
            'DB_PORT' => (string)$port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $user,
            'DB_PASSWORD' => $pass,
        ];

        if (!empty($data['app_url'])) {
            $envUpdates['APP_URL'] = $data['app_url'];
        }

        $this->updateEnvFile($envUpdates);

        // Update runtime config
        config([
            'database.default' => $driver,
            "database.connections.{$driver}.host" => $host,
            "database.connections.{$driver}.port" => $port,
            "database.connections.{$driver}.database" => $driver === 'sqlite' ? (File::isAbsolutePath($database) ? $database : base_path($database)) : $database,
            "database.connections.{$driver}.username" => $user,
            "database.connections.{$driver}.password" => $pass,
        ]);

        DB::purge();

        // Generate app key if missing
        if (empty(config('app.key'))) {
            try {
                Artisan::call('key:generate', [
                    '--force' => true,
                ]);
            } catch (Throwable) {
                // Non-fatal
            }
        }

        // Run migrations
        try {
            Artisan::call('migrate', [
                '--force' => true,
            ]);
            $migrationOutput = Artisan::output();
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Database configured, but migration failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Database configured and migrations completed successfully!',
            'output' => trim($migrationOutput),
        ]);
    }

    /**
     * Complete VPS installation: Database config, app key, migrations, and Admin account creation.
     */
    public function vpsInstall(Request $request): JsonResponse
    {
        if ($this->installed()) {
            return $this->alreadyInstalled();
        }

        $data = $request->validate([
            'driver' => 'required|string|in:sqlite,mysql,mariadb,pgsql',
            'host' => 'nullable|string',
            'port' => 'nullable|numeric',
            'database' => 'required|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'app_url' => 'nullable|url',
            // Admin user details
            'admin_name' => 'required|string|max:100',
            'admin_email' => 'required|email|max:200',
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            // Optional keys
            'gemini_api_key' => 'nullable|string|max:200',
            'openai_api_key' => 'nullable|string|max:200',
            'youtube_api_key' => 'nullable|string|max:200',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
        ]);

        $driver = $data['driver'] === 'mariadb' ? 'mysql' : $data['driver'];
        $database = trim($data['database']);
        $host = !empty($data['host']) ? $data['host'] : '127.0.0.1';
        $port = !empty($data['port']) ? (int)$data['port'] : ($driver === 'pgsql' ? 5432 : 3306);
        $user = $data['username'] ?? '';
        $pass = $data['password'] ?? '';

        // SQLite path setup
        if ($driver === 'sqlite') {
            $path = $database;
            if (!File::isAbsolutePath($path)) {
                $path = base_path($path);
            }
            $dir = dirname($path);
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true, true);
            }
            if (!File::exists($path)) {
                File::put($path, '');
            }
        }

        $envUpdates = [
            'DB_CONNECTION' => $driver,
            'DB_HOST' => $host,
            'DB_PORT' => (string)$port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $user,
            'DB_PASSWORD' => $pass,
        ];

        if (!empty($data['app_url'])) {
            $envUpdates['APP_URL'] = $data['app_url'];
        }
        if (!empty($data['gemini_api_key'])) {
            $envUpdates['GEMINI_API_KEY'] = $data['gemini_api_key'];
        }
        if (!empty($data['openai_api_key'])) {
            $envUpdates['OPENAI_API_KEY'] = $data['openai_api_key'];
        }
        if (!empty($data['youtube_api_key'])) {
            $envUpdates['YOUTUBE_API_KEY'] = $data['youtube_api_key'];
        }

        $this->updateEnvFile($envUpdates);

        config([
            'database.default' => $driver,
            "database.connections.{$driver}.host" => $host,
            "database.connections.{$driver}.port" => $port,
            "database.connections.{$driver}.database" => $driver === 'sqlite' ? (File::isAbsolutePath($database) ? $database : base_path($database)) : $database,
            "database.connections.{$driver}.username" => $user,
            "database.connections.{$driver}.password" => $pass,
        ]);

        DB::purge();

        if (empty(config('app.key'))) {
            try {
                Artisan::call('key:generate', ['--force' => true]);
            } catch (Throwable) {
            }
        }

        // Run migrations
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Database migration failed: ' . $e->getMessage(),
            ], 500);
        }

        // Provision Delia's Admin Account
        $adminUser = new User([
            'name' => $data['admin_name'],
            'email' => strtolower(trim($data['admin_email'])),
            'password' => $data['admin_password'],
        ]);

        $adminUser->forceFill([
            'role' => Role::Admin,
            'email_verified_at' => now(),
        ])->save();

        Setting::put('creator_name', $data['admin_name']);
        if (!empty($data['timezone'])) {
            Setting::put('timezone', $data['timezone']);
        }
        if (!empty($data['currency'])) {
            Setting::put('currency', $data['currency']);
        }

        // Sign in immediately
        $request->session()->regenerate();
        auth()->login($adminUser, true);

        return response()->json([
            'ok' => true,
            'message' => "Welcome, {$adminUser->name}! VPS setup complete. Launching your studio.",
            'user' => $adminUser->toSession(),
        ]);
    }

    /**
     * Run migrations on the currently configured database.
     */
    public function runMigrations(): JsonResponse
    {
        if ($this->installed()) {
            return $this->alreadyInstalled();
        }

        try {
            Artisan::call('migrate', [
                '--force' => true,
            ]);
            $output = Artisan::output();

            return response()->json([
                'ok' => true,
                'message' => 'Migrations executed successfully.',
                'output' => trim($output),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Migration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * These routes are public so a fresh server can be set up from the browser. Once an
     * account exists, nobody may test connections, reconfigure or migrate from here.
     */
    private function installed(): bool
    {
        try {
            return User::count() > 0;
        } catch (Throwable) {
            return false; // DB not connected/migrated yet
        }
    }

    private function alreadyInstalled(): JsonResponse
    {
        return response()->json([
            'error' => 'Application is already installed.',
        ], 403);
    }

    /**
     * Update .env file with given key => value pairs.
     */
    private function updateEnvFile(array $values): void
    {
        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        $content = '';
        if (File::exists($envPath)) {
            $content = File::get($envPath);
        } elseif (File::exists($examplePath)) {
            $content = File::get($examplePath);
        }

        foreach ($values as $key => $val) {
            // Escape / quote value if needed
            $formattedVal = $val;
            if (str_contains($val, ' ') || str_contains($val, '#') || str_contains($val, '$')) {
                $formattedVal = '"' . str_replace('"', '\"', $val) . '"';
            }

            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$formattedVal}", $content);
            } else {
                $content .= "\n{$key}={$formattedVal}";
            }
        }

        if (is_writable($envPath) || (!File::exists($envPath) && is_writable(base_path()))) {
            File::put($envPath, $content);
        }
    }
}
