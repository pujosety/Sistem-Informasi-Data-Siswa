<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;

/**
 * Why does /tentang say the profile is empty when the rows exist?
 *
 * The seeder wrote school.npsn, school.address and school.city — verified by
 * a direct SQL read — and `blank()` on all three still takes the "not filled
 * in" branch. So either the values are not what the service returns, or the
 * view is not receiving what the controller passed.
 *
 * This prints the value as the SERVICE sees it, which is the only layer that
 * can explain a discrepancy between correct rows and a blank page. Values are
 * printed because every one of these is a school profile field that is
 * published on the public site anyway; nothing here is a credential.
 */
class DiagnoseSchoolProfileCommand extends Command
{
    protected $signature = 'sida:diagnose-school-profile';

    protected $description = 'Report the school settings as SettingsService returns them';

    public function handle(SettingsService $settings): int
    {
        $keys = [
            'school.name', 'school.npsn', 'school.address', 'school.city',
            'school.province', 'school.email', 'school.phone', 'school.website',
            'school.headmaster',
        ];

        $this->line('as SettingsService returns it:');
        $blank = 0;

        foreach ($keys as $key) {
            $value = $settings->get($key);
            $isBlank = blank($value);

            $blank += $isBlank ? 1 : 0;

            $this->line(sprintf(
                '  %-20s %s',
                $key,
                $isBlank ? 'BLANK' : "'".mb_substr((string) $value, 0, 44)."'"
            ));
        }

        $this->newLine();
        $this->line($blank === 0
            ? 'Every field is filled, so /tentang should NOT show the empty notice.'
            : "{$blank} field(s) read blank to the service — that is why /tentang shows the notice.");

        // A second read in the same process. If this differs from the first,
        // something is rewriting values mid-request.
        $this->newLine();
        $this->line('second read, same process:');
        $this->line('  school.npsn  '.(blank($settings->get('school.npsn')) ? 'BLANK' : "'".$settings->get('school.npsn')."'"));

        return self::SUCCESS;
    }
}
