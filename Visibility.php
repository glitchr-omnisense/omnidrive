<?php

namespace Omnidrive;

/**
 * Who may read a file: anyone (a public bucket's object, a file the web server
 * serves, 0644) or the drive's own user (0600, a private object).
 */
enum Visibility: string
{
    case Public = 'public';
    case Private = 'private';

    /** A Visibility, or its name as configuration gives it ("public", "private"). */
    public static function of(self|string $visibility): self
    {
        return $visibility instanceof self ? $visibility : self::from(strtolower(trim($visibility)));
    }
}
