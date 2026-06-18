<?php

declare(strict_types=1);

namespace App\Http\Requests\ProjectVista;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreCompanySubcontractorsRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->route('company')?->id;

        return [
            'subcontractors' => ['required', 'array', 'min:1', 'max:25'],
            'subcontractors.*.name' => ['required', 'string', 'max:255'],
            'subcontractors.*.email' => ['required', 'email:rfc', 'max:255'],
            'subcontractors.*.title' => ['nullable', 'string', 'max:255'],
            'subcontractors.*.subcontractor_type_id' => [
                'nullable',
                'integer',
                Rule::exists('subcontractor_types', 'id')->where('company_id', $companyId),
            ],
            'subcontractors.*.scheduling_capacity_daily' => ['required', 'integer', 'min:1', 'max:20'],
            'subcontractors.*.reliability_score' => ['required', 'integer', 'min:0', 'max:100'],
            'subcontractors.*.scheduling_is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $company = $this->route('company');
            $rows = collect($this->input('subcontractors', []));
            $normalizedEmails = $rows
                ->map(fn (array $row): string => Str::lower((string) ($row['email'] ?? '')))
                ->filter();
            $duplicateEmails = $normalizedEmails
                ->duplicates()
                ->unique()
                ->values();

            $existingCompanyEmails = User::query()
                ->whereIn('email', $normalizedEmails)
                ->whereHas('companies', fn ($query) => $query->whereKey($company?->id))
                ->pluck('email')
                ->map(fn (string $email): string => Str::lower($email));

            $rows->each(function (array $row, int $index) use (
                $duplicateEmails,
                $existingCompanyEmails,
                $validator,
            ): void {
                $email = Str::lower((string) ($row['email'] ?? ''));

                if ($duplicateEmails->contains($email)) {
                    $validator->errors()->add(
                        "subcontractors.{$index}.email",
                        'Each subcontractor email must be unique in this batch.',
                    );
                }

                if ($existingCompanyEmails->contains($email)) {
                    $validator->errors()->add(
                        "subcontractors.{$index}.email",
                        'This user is already attached to the company.',
                    );
                }
            });
        });
    }
}
