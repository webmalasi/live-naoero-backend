<?php

namespace App\Http\Requests\Api\V1;

class AppLogRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'deviceId' => ['required', 'string', 'min:8', 'max:100'],
            'appVersion' => ['nullable', 'string', 'max:50'],
            'platform' => ['nullable', 'string', 'max:50'],
            'entries' => ['required', 'array', 'min:1', 'max:200'],
            'entries.*' => ['array'],
            'entries.*.ts' => ['sometimes', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/', $value)) {
                    $fail("{$attribute} must be an ISO date-time.");
                }
            }],
            'entries.*.level' => ['required', 'string', 'in:debug,info,warn,error'],
            'entries.*.event' => ['required', 'string', 'min:1', 'max:100'],
            'entries.*.message' => ['nullable', 'string', 'max:2000'],
            'entries.*.detail' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'deviceId.min' => 'deviceId is too short.'];
    }

    protected function normalize(array $data): array
    {
        self::map($data, 'deviceId', self::trimmed(...));
        if (is_array($data['entries'] ?? null)) {
            foreach ($data['entries'] as &$e) {
                if (is_array($e)) {
                    self::map($e, 'event', self::trimmed(...));
                }
            }
            unset($e);
        }

        return $data;
    }
}
