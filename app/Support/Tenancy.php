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

    public static function clear(): void
    {
        static::$current = null;
    }
}
