<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable {
    use HasFactory, Notifiable, HasUuids;
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'password' => 'hashed']; }
    public function memberships() { return $this->hasMany(Membership::class); }
    public function organizations() { return $this->belongsToMany(Organization::class, 'memberships')->withPivot('role'); }
}
