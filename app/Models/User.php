<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
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
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

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
     * Every workspace this user is a member of (role carried on the pivot).
     *
     * @return BelongsToMany<Workspace, $this>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('workspace_role')
            ->withTimestamps();
    }

    /** Whether the user is a member of the given workspace. */
    public function isMemberOf(int $workspaceId): bool
    {
        return $this->workspaces()->whereKey($workspaceId)->exists();
    }

    /** The user's role in the given workspace, or null if not a member. */
    public function roleIn(int $workspaceId): ?string
    {
        $ws = $this->workspaces()->whereKey($workspaceId)->first();

        return $ws?->getAttribute('pivot')?->workspace_role;
    }

    /**
     * The active workspace for this user. `workspace_id` is updated by the
     * workspace switcher; membership is governed by the workspaces() pivot.
     */
    public function getCurrentWorkspaceAttribute(): ?Workspace
    {
        return $this->workspace;
    }
}
