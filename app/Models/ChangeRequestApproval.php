<?php

namespace App\Models;

use Database\Factories\ChangeRequestApprovalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequestApproval extends Model
{
    /** @use HasFactory<ChangeRequestApprovalFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'change_request_id',
        'admin_id',
        'decision',
        'signature',
    ];

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class, 'change_request_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
