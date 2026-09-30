<?php

namespace App\Notifications\Concerns;

use Ichtrojan\Otp\Models\Otp as OtpModel;
use Ichtrojan\Otp\Otp;

/**
 * A six-digit code that is generated with the email and can be thrown away
 * again if the email could not be sent, so a code nobody received is never
 * left valid in the database.
 */
trait HasOneTimeCode
{
    protected string $otp;

    protected function generateCode(string $email, int $validityMinutes): void
    {
        $this->otp = (new Otp)->generate($email, 'numeric', 6, $validityMinutes)->token;
    }

    public function discardCode(): void
    {
        OtpModel::where('identifier', $this->email)->where('token', $this->otp)->delete();
    }
}
