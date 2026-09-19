<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ApiKey extends Model {
    use HasUuids;
    protected $fillable = ['project_id', 'key_hash'];
    protected $hidden = ['key_hash'];
    public function project() { return $this->belongsTo(Project::class); }
}
