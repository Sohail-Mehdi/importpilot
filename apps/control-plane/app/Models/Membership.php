<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Membership extends Model {
    use HasUuids;
    protected $fillable = ['user_id', 'organization_id', 'role'];
    public function user() { return $this->belongsTo(User::class); }
    public function organization() { return $this->belongsTo(Organization::class); }
}
