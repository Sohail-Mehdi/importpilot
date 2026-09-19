<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ImportSession extends Model {
    use HasUuids;
    protected $fillable = ['project_id', 'schema_id', 'state'];
    public function project() { return $this->belongsTo(Project::class); }
    public function schema() { return $this->belongsTo(ImportSchema::class); }
}
