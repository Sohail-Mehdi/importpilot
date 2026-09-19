<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UploadAttempt extends Model {
    use HasUuids;
    protected $fillable = ['import_session_id', 'storage_key', 'status', 'original_filename', 'expires_at'];
    public function session() { return $this->belongsTo(ImportSession::class, 'import_session_id'); }
}
