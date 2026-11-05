<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Models\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes append-only, hash-chained audit events for a council database.
 * Each event's hash covers the previous event's hash, so tampering with any
 * row breaks verification of every later row.
 */
class AuditLogger
{
    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        array $metadata = [],
        ?string $module = null,
    ): AuditEvent {
        $this->tenants->requireCouncil();

        return DB::connection('tenant')->transaction(function () use ($action, $subject, $before, $after, $metadata, $module) {
            $sequence = NumberSequence::query()->lockForUpdate()->find('audit_chain');

            if (! $sequence) {
                $sequence = NumberSequence::create([
                    'key' => 'audit_chain',
                    'prefix' => 'AUD',
                    'next_value' => 1,
                    'padding' => 10,
                ]);
                $sequence->refresh();
            }

            $index = (int) $sequence->next_value;
            $sequence->next_value = $index + 1;
            $sequence->updated_at = now();
            $sequence->save();

            $previous = AuditEvent::query()->orderByDesc('chain_index')->first();
            $occurredAt = now();
            $user = Auth::user();
            $membership = $this->tenants->membership();

            $payload = [
                'chain_index' => $index,
                'occurred_at' => $occurredAt->toIso8601String(),
                'action' => $action,
                'module' => $module,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'actor_user_id' => $user?->getKey(),
                'actor_name' => $user?->name,
                'actor_role' => $membership?->role?->code,
                'before' => $before,
                'after' => $after,
                'metadata' => $metadata,
            ];

            $prevHash = $previous?->hash;
            $hash = hash('sha256', ($prevHash ?? 'genesis').'|'.$this->canonicalJson($payload));

            return AuditEvent::create([
                'chain_index' => $index,
                'occurred_at' => $occurredAt,
                'actor_user_id' => $user?->getKey(),
                'actor_name' => $user?->name,
                'actor_role' => $membership?->role?->code,
                'action' => $action,
                'module' => $module,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'before' => $before ?: null,
                'after' => $after ?: null,
                'metadata' => $metadata ?: null,
                'ip_address' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
                'request_id' => request()?->headers->get('X-Request-Id'),
                'prev_hash' => $prevHash,
                'hash' => $hash,
            ]);
        });
    }

    /**
     * @return array{ok: bool, checked: int, broken_at: int|null, message: string}
     */
    public function verify(): array
    {
        $previous = null;
        $checked = 0;

        foreach (AuditEvent::query()->orderBy('chain_index')->cursor() as $event) {
            $payload = [
                'chain_index' => (int) $event->chain_index,
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'action' => $event->action,
                'module' => $event->module,
                'subject_type' => $event->subject_type,
                'subject_id' => $event->subject_id,
                'actor_user_id' => $event->actor_user_id,
                'actor_name' => $event->actor_name,
                'actor_role' => $event->actor_role,
                'before' => $event->before ?? [],
                'after' => $event->after ?? [],
                'metadata' => $event->metadata ?? [],
            ];

            $expected = hash('sha256', (($previous ?? 'genesis')).'|'.$this->canonicalJson($payload));

            if (! hash_equals($expected, $event->hash) || $event->prev_hash !== $previous) {
                return [
                    'ok' => false,
                    'checked' => $checked,
                    'broken_at' => (int) $event->chain_index,
                    'message' => 'Audit hash chain is broken.',
                ];
            }

            $previous = $event->hash;
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null, 'message' => 'Audit hash chain verified.'];
    }

    /**
     * PostgreSQL jsonb does not preserve key order, so the hashed payload must
     * be canonicalised with sorted keys on both write and verification.
     */
    private function canonicalJson(array $payload): string
    {
        return json_encode($this->canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonicalize($item), $value);
        }

        ksort($value);

        return array_map(fn ($item) => $this->canonicalize($item), $value);
    }
}
