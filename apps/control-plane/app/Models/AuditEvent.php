<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuditEvent extends Model {
    use HasUuids;
    protected $fillable = ['project_id', 'action', 'context'];
    protected $casts = ['context' => 'array'];
}
