<?php

namespace App\Support\Google;

use App\Models\GoogleConnection;
use RuntimeException;

/**
 * Google refused the connected account's refresh token (it lapsed, was revoked, or the password changed). The connection
 * is marked, and HR has to connect again; until then nothing is sent through it.
 */
class GoogleAuthLost extends RuntimeException
{
    /**
     * @param  bool  $first  this is the first time it was noticed, so the people to tell have not been told yet
     */
    public function __construct(public GoogleConnection $connection, public bool $first)
    {
        parent::__construct('Google menolak akses akun yang terhubung; hubungkan ulang.');
    }
}
