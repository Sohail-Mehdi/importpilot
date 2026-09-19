<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SourceArtifact extends Model {
    use HasUuids;
    protected $fillable = ['import_session_id', 'storage_key', 'original_filename', 'file_size', 'content_type', 'checksum'];
    public function session() { return $this->belongsTo(ImportSession::class, 'import_session_id'); }
}
