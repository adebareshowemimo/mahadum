<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Billing\MonnifyBills;
use App\Services\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Read-only audit inventory. No HTTP, record repair, deletion or secret output. */
class InspectBatchFive extends Command
{
    protected $signature = 'audit:batch-five {--user= : Exact account ID or email for ownership diagnosis} {--check-monnify : Check read-only catalogue access; never initialize payment or vend}';

    protected $description = 'Inspect Batch 5 account, demo-data, video and provider prerequisites without changing records';

    public function handle(Settings $settings): int
    {
        $candidates = [
            'organizations' => DB::table('organizations')->whereIn('name', ['Reilly-Hickle Academy', 'Kiehn Inc Academy', 'Fahey PLC Academy'])->get(['id', 'name', 'status'])->all(),
            'promo_codes' => DB::table('promo_codes')->whereIn('code', ['SCHOOL25', 'TERM2024'])->get(['id', 'code', 'status'])->all(),
            'course_levels' => DB::table('course_levels')->whereIn('title', ['hello', 'Hello', 'level 1 / hello'])->get(['id', 'course_id', 'title', 'position'])->all(),
            'lessons' => DB::table('lessons')->whereIn('title', ['hello', 'Hello'])->whereNull('published_at')
                ->get(['id', 'course_level_id', 'title', 'position', 'deleted_at'])->all(),
            'advert_placements' => DB::table('advert_placements')->where('name', 'like', '%demo%')->get(['id', 'name', 'is_active'])->all(),
        ];
        // Inspect dependencies from actual schema, including child tables that
        // cascade. A matching name is only a candidate, never deletion authority.
        $foreignKeys = [];
        if (DB::connection()->getDriverName() === 'mysql') {
            // One metadata query avoids slow full-schema introspection per table.
            $keys = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('REFERENCED_TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->whereIn('REFERENCED_TABLE_NAME', array_keys($candidates))
                ->where('REFERENCED_COLUMN_NAME', 'id')
                ->get(['TABLE_NAME', 'COLUMN_NAME', 'REFERENCED_TABLE_NAME']);
            foreach ($keys as $key) {
                $foreignKeys[$key->TABLE_NAME][] = ['foreign_table' => $key->REFERENCED_TABLE_NAME,
                    'columns' => [$key->COLUMN_NAME], 'foreign_columns' => ['id']];
            }
        } else {
            foreach (Schema::getTableListing(schemaQualified: false) as $table) {
                $foreignKeys[$table] = Schema::getForeignKeys($table);
            }
        }
        foreach ($candidates as $target => &$rows) {
            foreach ($rows as $row) {
                $row->dependencies = [];
                foreach ($foreignKeys as $table => $keys) {
                    foreach ($keys as $fk) {
                        if ($fk['foreign_table'] === $target && count($fk['columns']) === 1 && $fk['foreign_columns'] === ['id']) {
                            $row->dependencies[$table.'.'.$fk['columns'][0]] = DB::table($table)->where($fk['columns'][0], $row->id)->count();
                        }
                    }
                }
            }
        }
        unset($rows);
        $account = null;
        if ($selector = $this->option('user')) {
            $user = ctype_digit((string) $selector) ? User::find((int) $selector) : User::where('email', $selector)->first();
            if (! $user) {
                $this->error('The exact account was not found. No records changed.');

                return self::FAILURE;
            }
            $families = DB::table('families')->where('owner_user_id', $user->id)->get(['id', 'organization_id', 'deleted_at']);
            $memberships = DB::table('family_members')->where('user_id', $user->id)
                ->get(['id', 'family_id', 'learner_profile_id', 'relationship', 'is_account_owner']);
            $familyIds = $families->pluck('id')->merge($memberships->pluck('family_id'))->unique();
            $account = ['user_id' => $user->id, 'signup_account_type' => $user->signup_account_type, 'roles' => $user->getRoleNames()->all(),
                'owned_families' => $families->all(),
                'family_memberships' => $memberships->all(),
                'linked_families' => DB::table('families')->whereIn('id', $familyIds)->get(['id', 'owner_user_id', 'organization_id', 'deleted_at'])->all(),
                'learners' => DB::table('learner_profiles')->where(fn ($q) => $q->where('user_id', $user->id)
                    ->orWhereIn('family_id', $familyIds))->get(['id', 'family_id', 'user_id', 'organization_id', 'deleted_at'])->all()];
        }
        $names = DB::table('learner_profiles')->whereNull('deleted_at')->select('display_name')->groupBy('display_name')->havingRaw('COUNT(*) > 1')->pluck('display_name');
        $learners = DB::table('learner_profiles')->whereIn('display_name', $names)->whereNull('deleted_at')->get(['id', 'display_name', 'family_id', 'user_id', 'organization_id']);
        foreach ($learners as $learner) {
            $learner->total_xp = DB::table('xp_ledger')->where('learner_profile_id', $learner->id)->sum('amount');
        }
        $videos = DB::table('videos')->get(['id', 'lesson_component_id', 'duration_seconds', 'source_type', 'source_asset_id', 'status']);
        foreach ($videos as $video) {
            $video->ready_qualities = DB::table('video_renditions')->where('video_id', $video->id)->where('ready', true)->pluck('quality')->all();
            $video->caption_languages = DB::table('captions')->where('video_id', $video->id)->pluck('language_code')->all();
            $video->require_watch = (bool) data_get(json_decode((string) DB::table('lesson_components')->where('id', $video->lesson_component_id)->value('settings'), true), 'require_watch', false);
        }
        $catalogue = ['state' => 'NOT RUN'];
        if ($this->option('check-monnify')) {
            try {
                $bills = app(MonnifyBills::class);
                $billers = $bills->billers(fresh: true);
                $productCounts = [];
                foreach ($billers as $biller) {
                    $productCounts[$biller['code']] = count($bills->products($biller['code'], fresh: true));
                }
                $catalogue = ['state' => 'accessible', 'network_count' => count($billers),
                    'product_counts_by_network' => $productCounts,
                    'payment_and_delivery' => 'NOT RUN'];
            } catch (\Throwable $error) {
                $catalogue = ['state' => 'failed', 'error_type' => class_basename($error),
                    'error_status' => $error instanceof HttpException ? $error->getStatusCode() : null,
                    'payment_and_delivery' => 'NOT RUN'];
            }
        }
        $this->line(json_encode([
            'mode' => 'read_only', 'account' => $account,
            'same_name_learners_not_merge_targets' => $learners->all(),
            'cleanup_candidates_not_approved_targets' => $candidates,
            'cleanup_limit' => 'Only three of the six reported school names are known. No blanket name-based deletion is safe. Snapshot exact records and dependencies before approved cleanup.',
            'videos' => $videos->all(),
            'providers' => [
                'monnify_key_present' => filled(config('services.monnify.api_key')),
                'monnify_secret_present' => filled(config('services.monnify.secret')),
                'monnify_contract_present' => filled(config('services.monnify.contract_code')),
                'monnify_environment' => str_contains((string) config('services.monnify.base_url'), 'sandbox') ? 'sandbox' : 'other',
                'monnify_catalogue' => $catalogue,
                'payments_live' => (bool) config('services.payments.live'),
                'telco_enrollment_enabled' => (bool) $settings->get('feature.telco_billing'),
                'telco_live' => (bool) config('services.telco.live'),
                'provider_fulfillment' => 'NOT RUN; catalogue access does not prove payment or carrier delivery',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
