<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Organization extends Model {
    use HasUuids;
    protected $fillable = ['name'];
    public function memberships() { return $this->hasMany(Membership::class); }
    public function users() { return $this->belongsToMany(User::class, 'memberships')->withPivot('role'); }
    public function projects() { return $this->hasMany(Project::class); }
}
