<?php

namespace App\Http\Requests\Api\V1\Me;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Services\Auth\AccountDeletion;
use Illuminate\Validation\Rule;

class DeleteRequestRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            // Why (AccountDeletion::REASONS); "other" needs reasonText.
            'reasonCode' => ['sometimes', 'string', Rule::in(array_keys(AccountDeletion::REASONS))],
            'reasonText' => ['sometimes', 'nullable', 'string', 'max:1000'],
            // Older app versions send only their own words.
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            // The user must type their current password to confirm.
            'password' => ['required', 'string', 'min:1', 'max:128'],
        ];
    }

    protected function normalize(array $data): array
    {
        self::map($data, 'reasonText', self::optionalText(...));
        self::map($data, 'reason', self::optionalText(...));

        return $data;
    }
}
