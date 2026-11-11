<?php

namespace App\Modules\Platform\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AcceptanceStepRunner
{
    /** @param array<string, mixed> $input @param callable(): array<string, mixed> $operation @return array<string, mixed> */
    public function run(string $key, array $input, callable $operation): array
    {
        $canonicalInput = $this->canonicalize($input);
        $inputHash = hash('sha256', json_encode($canonicalInput, JSON_THROW_ON_ERROR));
        $steps = DB::connection('tenant')->table('acceptance_setup_steps');
        $existing = $steps->where('step_key', $key)->first();

        if ($existing && $existing->status === 'complete') {
            if (! hash_equals($existing->input_sha256, $inputHash)) {
                throw new RuntimeException("Acceptance step [{$key}] was already completed with different inputs.");
            }

            return json_decode($existing->output ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        }

        DB::connection('tenant')->transaction(function () use ($key, $inputHash): void {
            $row = DB::connection('tenant')->table('acceptance_setup_steps')->where('step_key', $key)->lockForUpdate()->first();
            $values = [
                'input_sha256' => $inputHash,
                'status' => 'running',
                'attempts' => (int) ($row->attempts ?? 0) + 1,
                'last_error' => null,
                'started_at' => now(),
                'completed_at' => null,
                'updated_at' => now(),
            ];
            if ($row) {
                DB::connection('tenant')->table('acceptance_setup_steps')->where('step_key', $key)->update($values);
            } else {
                DB::connection('tenant')->table('acceptance_setup_steps')->insert([
                    ...$values,
                    'id' => (string) Str::ulid(),
                    'step_key' => $key,
                    'created_at' => now(),
                ]);
            }
        });

        try {
            return DB::connection('tenant')->transaction(function () use ($key, $inputHash, $operation): array {
                $output = $operation();
                DB::connection('tenant')->table('acceptance_setup_steps')->where('step_key', $key)->update([
                    'input_sha256' => $inputHash,
                    'status' => 'complete',
                    'output' => json_encode($output, JSON_THROW_ON_ERROR),
                    'last_error' => null,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

                return $output;
            });
        } catch (Throwable $exception) {
            DB::connection('tenant')->table('acceptance_setup_steps')->where('step_key', $key)->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'updated_at' => now(),
            ]);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonicalize($item);
            }
        }

        return $value;
    }
}
