<?php

namespace App\Http\Resources;

use App\Models\Playbook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaybookResource extends JsonResource
{
    /**
     * @param  Playbook  $resource
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'required_doc_types' => $this->required_doc_types,
            'optional_doc_types' => $this->optional_doc_types,
            'forbidden_doc_types' => $this->forbidden_doc_types,
            'recipient_label' => $this->recipient_label,
            'default_ttl_minutes' => $this->default_ttl_minutes,
            'max_ttl_minutes' => $this->max_ttl_minutes,
            // FR-PB-02: các TTL offer cho Owner, đã lọc theo max của playbook.
            'ttl_options' => array_values(array_filter(
                (array) config('trustgate.grant_ttl_options', [5, 15, 60, 1440]),
                fn (int $minutes) => $minutes <= (int) $this->max_ttl_minutes,
            )),
            'version' => $this->version,
            'active' => (bool) $this->active,
        ];
    }
}
