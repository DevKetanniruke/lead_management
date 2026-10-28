<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/** Writes entries to the audit log. Never stores secrets (passwords, tokens, card data). */
class Audit
{
    private const REDACT = ['password', 'password_confirmation', 'remember_token', 'token', 'card_number', 'cvv', 'secret'];

    public static function log(string $action, ?Model $subject = null, string $description = '', array $properties = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 500),
            'properties' => self::clean($properties) ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    private static function clean(array $properties): array
    {
        $clean = Arr::except($properties, self::REDACT);

        foreach ($clean as $key => $value) {
            if (is_array($value)) {
                $clean[$key] = self::clean($value);
            }
        }

        return $clean;
    }
}
