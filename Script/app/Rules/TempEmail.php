<?php

namespace App\Rules;

use Cache;
use Illuminate\Contracts\Validation\Rule;

class TempEmail implements Rule
{
    protected $blacklistedDomains;

    public function __construct()
    {
        $this->blacklistedDomains = Cache::remember('TempEmailBlackList', 60 * 10, function () {
            $data = @file_get_contents('https://gist.githubusercontent.com/saaiful/dd2b4b34a02171d7f9f0b979afe48f65/raw/2ad5590be72b69a51326b3e9d229f615e866f2e5/blocklist.txt');
            if ($data) {
                return array_filter(array_map('trim', explode("\n", $data)));
            }
            return [];
        });
    }

    public function passes($attribute, $value)
    {
        $emailDomain = substr(strrchr($value, "@"), 1);
        return !in_array($emailDomain, $this->blacklistedDomains);
    }

    public function message()
    {
        return __('general.email_valid');
    }
}