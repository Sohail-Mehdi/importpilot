<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Project extends Model {
    use HasUuids;
    protected $fillable = ['organization_id', 'name'];
    public function organization() { return $this->belongsTo(Organization::class); }
    public function apiKeys() { return $this->hasMany(ApiKey::class); }
}
