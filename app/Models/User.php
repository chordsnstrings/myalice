<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $workspace_id
 * @property string $name
 * @property string $email
 * @property string $workspace_role
 * @property string|null $avatar
 * @property-read Workspace|null $workspace
 * @property-read Workspace|null $currentWorkspace
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'workspace_id',
        'workspace_role',
        'avatar',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The active workspace for this user. Single-workspace membership for now;
     * a session override can be layered on for multi-workspace switching.
     */
    public function getCurrentWorkspaceAttribute(): ?Workspace
    {
        return $this->workspace;
    }
}
