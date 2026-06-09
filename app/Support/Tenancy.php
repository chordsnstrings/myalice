<?php

namespace App\Support;

use App\Models\Workspace;

/**
 * Holds the active tenant for the request lifecycle. Resolved by
 * SetCurrentWorkspace middleware from the authenticated user/session.
 */
class Tenancy
{
    protected static ?Workspace $current = null;

    public static function set(?Workspace $workspace): void
    {
        static::$current = $workspace;
    }

    public static function current(): ?Workspace
    {
        return static::$current;
    }

    public static function id(): ?int
    {
        return static::$current?->id;
    }

    /**
     * The active workspace, guaranteed. Use inside routes behind the
     * `workspace` middleware, where a tenant is always resolved.
     */
    public static function currentOrFail(): Workspace
    {
        return static::$current ?? throw new \RuntimeException('No active workspace for this request.');
    }

    public static function clear(): void
    {
        static::$current = null;
    }
}
