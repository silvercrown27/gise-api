<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

trait HasTimezone
{
    /**
     * Get the timezone for the current user, defaulting to 'Africa/Nairobi'.
     *
     * @return string
     */
    public function getTimezone()
    {
        $user = Auth::user();
        return $user->timezone ?? 'Africa/Nairobi';
    }

    /**
     * Get the current date and time in 'Y-m-d H:i:s' format according to the user's timezone.
     *
     * @return string
     */
    public function getDateTime()
    {
        $timezone = $this->getTimezone();
        return Carbon::now($timezone)->format('Y-m-d H:i:s');
    }
}
