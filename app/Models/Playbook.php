<?php

namespace App\Models;

use Database\Factories\PlaybookFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Playbook extends Model
{
    /** @use HasFactory<PlaybookFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'required_doc_types',
        'optional_doc_types',
        'forbidden_doc_types',
        'recipient_label',
        'default_ttl_minutes',
        'max_ttl_minutes',
        'version',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'required_doc_types' => 'array',
            'optional_doc_types' => 'array',
            'forbidden_doc_types' => 'array',
            'default_ttl_minutes' => 'integer',
            'max_ttl_minutes' => 'integer',
            'version' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Doc types a grant produced by this playbook may contain (required + optional).
     *
     * @return list<string>
     */
    public function allowedDocTypes(): array
    {
        return array_values(array_unique([
            ...($this->required_doc_types ?? []),
            ...($this->optional_doc_types ?? []),
        ]));
    }

    public function allowsDocType(string $docType): bool
    {
        return in_array($docType, $this->allowedDocTypes(), true)
            && ! in_array($docType, $this->forbidden_doc_types ?? [], true);
    }

    public function isRequiredDocType(string $docType): bool
    {
        return in_array($docType, $this->required_doc_types ?? [], true);
    }

    /**
     * TTL choices (in minutes) the Owner may pick for this playbook.
     *
     * @param  list<int>  $options
     * @return list<int>
     */
    public function allowedTtlOptions(array $options): array
    {
        return array_values(array_filter(
            $options,
            fn (int $minutes) => $minutes > 0 && $minutes <= $this->max_ttl_minutes,
        ));
    }
}
