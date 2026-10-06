<?php

namespace Tests\Feature\Lms;

use App\Services\PermissionCatalog;
use App\Services\RoleSeeder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LmsAuthorizationTest extends TestCase
{
    /** @test */
    public function lms_permissions_are_catalogued_and_granted_to_staff_tiers(): void
    {
        $names = PermissionCatalog::names();

        $this->assertContains('lms.course.view', $names);
        $this->assertContains('lms.course.create', $names);
        $this->assertContains('lms.course.update', $names);
        $this->assertContains('lms.lesson.manage', $names);

        app(RoleSeeder::class)->run();

        foreach (['admin', 'kesiswaan', 'wali_kelas'] as $roleName) {
            $role = Role::findByName($roleName);

            $this->assertTrue($role->hasPermissionTo('lms.course.view'), $roleName);
            $this->assertTrue($role->hasPermissionTo('lms.course.create'), $roleName);
            $this->assertTrue($role->hasPermissionTo('lms.lesson.manage'), $roleName);
        }
    }

    /** @test */
    public function students_do_not_receive_staff_lms_permissions(): void
    {
        app(RoleSeeder::class)->run();

        $student = Role::findByName('siswa');

        $this->assertFalse($student->hasPermissionTo('lms.course.create'));
        $this->assertFalse($student->hasPermissionTo('lms.lesson.manage'));
    }
}
