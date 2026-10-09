<?php

/**
 * Opt-in local MariaDB/MySQL acceptance: php tests/Support/verify-roster-concurrency.php
 * Creates/drops only its random database on 127.0.0.1, ignoring application DB_* values.
 * Optional ROSTER_VERIFY_MYSQL_USER/PASSWORD/PORT override local root defaults.
 */

use App\Models\ClassLearnerInvitation;
use App\Models\Course;
use App\Models\Language;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Models\SeatAllocation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$worker = ($argv[1] ?? '') === '--worker';
$database = $worker ? ($argv[2] ?? '') : 'roster_verify_'.bin2hex(random_bytes(8));
if (! preg_match('/^roster_verify_[a-f0-9]{16}$/D', $database)) {
    throw new RuntimeException('Refusing a database outside the isolated verification namespace.');
}
$port = (int) (getenv('ROSTER_VERIFY_MYSQL_PORT') ?: 3306);
$username = getenv('ROSTER_VERIFY_MYSQL_USER') ?: 'root';
$password = getenv('ROSTER_VERIFY_MYSQL_PASSWORD') ?: '';
$control = new PDO("mysql:host=127.0.0.1;port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (! $worker) {
    $control->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
$processes = [];

try {
    foreach (['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_CONFIG_CACHE' => dirname(__DIR__, 2).'/tmp/roster-verification-no-config.php',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => (string) $port, 'DB_DATABASE' => $database,
        'DB_USERNAME' => $username, 'DB_PASSWORD' => $password, 'DB_URL' => '',
        'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'PAYMENT_GATEWAY_LIVE' => 'false', 'MESSAGING_LIVE' => 'false', 'BCRYPT_ROUNDS' => '4'] as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    config(['database.default' => 'mysql', 'database.connections.mysql' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $port, 'database' => $database,
        'username' => $username, 'password' => $password, 'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
    ], 'cache.default' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync']);
    DB::purge('mysql');
    $control->exec("USE `{$database}`");

    $send = function (User $user, string $method, string $path, array $payload = []) use ($app): array {
        $token = $user->createToken('isolated-roster-verification')->plainTextToken;
        $request = Request::create('/api/v1/'.$path, $method, server: [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($payload, JSON_THROW_ON_ERROR));
        $kernel = $app->make(HttpKernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)];
    };
    if ($worker) {
        $scenario = $argv[3];
        $index = (int) $argv[4];
        $fixture = DB::table('roster_verification')->where('id', 1)->first();
        $data = json_decode($fixture->fixture, true, flags: JSON_THROW_ON_ERROR);
        DB::table('roster_verification')->where('id', 1)->increment('ready');
        $deadline = microtime(true) + 30;
        while (! DB::table('roster_verification')->where('id', 1)->value('go')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Worker start barrier timed out.');
            }
            usleep(20_000);
        }
        $rows = [['display_name' => 'Same Name', 'student_id' => 'S-001'], ['display_name' => 'Same Name', 'student_id' => 'S-002']];
        $actor = User::findOrFail($scenario === 'invitation' ? $data['invitee'] : $data['admin']);
        $result = match ($scenario) {
            'import' => $send($actor, 'POST', "schools/{$data['school']}/students/import", ['students' => $rows, 'class_id' => $data['first_class']]),
            'add' => $send($actor, 'POST', "classes/{$data['second_class']}/learners", ['learner_id' => $data['learner']]),
            'mixed' => $index === 0
                ? $send($actor, 'POST', "schools/{$data['school']}/students/import", ['students' => $rows, 'class_id' => $data['second_class']])
                : $send($actor, 'POST', "classes/{$data['second_class']}/learners", ['learner_id' => $data['other_learner']]),
            'invitation' => $send($actor, 'POST', "class-invitations/{$data['invite_token']}/accept"),
            'course' => $index === 0
                ? $send($actor, 'POST', "schools/{$data['school']}/students/import", ['students' => [
                    ['display_name' => 'New Learner', 'student_id' => 'S-003'], ['display_name' => 'New Learner', 'student_id' => 'S-004'],
                ], 'class_id' => $data['first_class']])
                : $send($actor, 'POST', "classes/{$data['first_class']}/courses/{$data['course']}"),
        };
        echo json_encode($result, JSON_THROW_ON_ERROR);
        exit($result['status'] >= 400 ? 1 : 0);
    }

    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException('Isolated migrations failed: '.Artisan::output());
    }
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $school = Organization::create(['name' => 'Isolated verification school', 'slug' => 'isolated-roster', 'type' => 'school', 'status' => 'active']);
    $admin = User::factory()->create();
    $admin->assignRole('school_admin');
    $school->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
    $allocation = SeatAllocation::create(['organization_id' => $school->id, 'total_purchased' => 10, 'active_filled' => 0]);
    $first = SchoolClass::create(['organization_id' => $school->id, 'name' => 'First class']);
    $second = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Second class']);
    $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
    $course = Course::create(['language_id' => $language->id, 'title' => 'Concurrent class course', 'status' => 'published', 'is_published' => true]);
    $course->levels()->create(['title' => 'L0', 'position' => 0, 'is_free' => true]);
    $invitee = User::factory()->create();
    $inviteToken = bin2hex(random_bytes(16));
    ClassLearnerInvitation::create(['organization_id' => $school->id, 'school_class_id' => $first->id,
        'invited_by_user_id' => $admin->id, 'name' => 'Invited learner', 'email' => $invitee->email,
        'token_hash' => hash('sha256', $inviteToken), 'expires_at' => now()->addDay()]);
    $fixture = ['school' => $school->id, 'admin' => $admin->id, 'first_class' => $first->id,
        'second_class' => $second->id, 'invitee' => $invitee->id, 'invite_token' => $inviteToken, 'course' => $course->id];
    $control->exec('CREATE TABLE roster_verification (id INT PRIMARY KEY, ready INT NOT NULL DEFAULT 0, go INT NOT NULL DEFAULT 0, fixture JSON NOT NULL) ENGINE=InnoDB');
    DB::table('roster_verification')->insert(['id' => 1, 'fixture' => json_encode($fixture)]);
    $results = [];
    foreach (['import', 'add', 'mixed', 'invitation', 'course'] as $scenario) {
        if ($scenario !== 'import') {
            [$fixture['learner'], $fixture['other_learner']] = LearnerProfile::orderBy('id')->limit(2)->pluck('id')->all();
        }
        DB::table('roster_verification')->where('id', 1)->update(['ready' => 0, 'go' => 0, 'fixture' => json_encode($fixture)]);
        foreach ([0, 1] as $index) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', $database, $scenario, (string) $index],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), options: ['bypass_shell' => true]);
            if (! is_resource($process)) {
                throw new RuntimeException('Could not start verification worker.');
            }
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        $deadline = microtime(true) + 30;
        while ((int) DB::table('roster_verification')->where('id', 1)->value('ready') !== 2) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not reach the start barrier.');
            }
            usleep(20_000);
        }
        DB::beginTransaction();
        Organization::whereKey($school->id)->lockForUpdate()->firstOrFail();
        $control->exec('UPDATE roster_verification SET go = 1 WHERE id = 1');
        $waiting = 0;
        $deadline = microtime(true) + 10;
        while ($waiting < 2 && microtime(true) < $deadline) {
            $waiting = count(array_filter($control->query('SHOW FULL PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC),
                fn ($row) => $row['db'] === $database && str_contains(strtolower($row['Info'] ?? ''), 'for update') && str_contains($row['Info'], 'organizations')));
            usleep(20_000);
        }
        DB::commit();
        if ($waiting !== 2) {
            throw new RuntimeException("{$scenario}: both requests did not contend on the school lock.");
        }
        $responses = [];
        foreach ($processes as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            if ($exit !== 0) {
                throw new RuntimeException("{$scenario} worker failed: {$output} {$errors}");
            }
            $responses[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        }
        $processes = [];
        $expectedLearners = ['import' => 2, 'add' => 2, 'mixed' => 2, 'invitation' => 3, 'course' => 5][$scenario];
        $expectedMemberships = ['import' => 2, 'add' => 3, 'mixed' => 4, 'invitation' => 5, 'course' => 7][$scenario];
        if (LearnerProfile::count() !== $expectedLearners || (int) $allocation->fresh()->active_filled !== $expectedLearners
            || DB::table('class_enrollments')->count() !== $expectedMemberships) {
            throw new RuntimeException("{$scenario}: profiles, memberships or seats did not reconcile.");
        }
        if ($scenario === 'course' && (DB::table('enrollments')->count() !== 5 || DB::table('class_course_assignments')->count() !== 1)) {
            throw new RuntimeException('Concurrent course assignment missed or duplicated a current/new learner.');
        }
        $results[$scenario] = ['overlapping_requests' => 2, 'statuses' => array_column($responses, 'status'),
            'profiles' => $expectedLearners, 'filled_seats' => $expectedLearners, 'memberships' => $expectedMemberships];
    }
    $directory = $send($admin, 'GET', "schools/{$school->id}/students");
    $dashboard = $send($admin, 'GET', "schools/{$school->id}/dashboard");
    $classes = $send($admin, 'GET', 'classes');
    $classDetail = $send($admin, 'GET', "classes/{$first->id}");
    $classCounts = array_column($classes['body']['data'] ?? [], 'students');
    sort($classCounts);
    if ($directory['status'] !== 200 || count($directory['body']['data']) !== 5 || $dashboard['status'] !== 200
        || $dashboard['body']['data']['student_counts'] !== ['total' => 5, 'in_classes' => 5, 'unassigned' => 0]
        || $classes['status'] !== 200 || $classCounts !== [2, 5]
        || $classDetail['status'] !== 200 || count($classDetail['body']['data']['students']) !== 5) {
        throw new RuntimeException('Directory/dashboard API totals did not reconcile.');
    }
    echo json_encode(['result' => 'passed', 'engine' => $control->query('SELECT VERSION()')->fetchColumn(), 'scenarios' => $results,
        'directory_profiles' => 5, 'course_enrollments' => 5, 'class_counts' => $classCounts, 'student_counts' => $dashboard['body']['data']['student_counts']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    if (! $worker) {
        if (isset($app) && DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($processes as [$process]) {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
        $control->exec("DROP DATABASE `{$database}`");
    }
}
