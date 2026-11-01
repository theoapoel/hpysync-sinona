<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErpSyncLog extends Model
{
    protected $fillable = [
        'type', 'reference_id', 'reference_no', 'status',
        'request_payload', 'response_payload', 'error_message', 'erp_docname',
    ];

    public function scopeByType($query, $type) { return $query->where('type', $type); }
    public function scopeFailed($query) { return $query->where('status', 'failed'); }
    public function scopeSuccess($query) { return $query->where('status', 'success'); }
}
