<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ImportSchema extends Model {
    use HasUuids;
    protected $fillable = ['project_id', 'schema_name', 'definition', 'version'];
    protected $casts = ['definition' => 'array'];
    public function project() { return $this->belongsTo(Project::class); }
}
