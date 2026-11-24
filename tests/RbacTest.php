<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RbacTest extends TestCase
{
    #[DataProvider('roles')]
    public function testRoleNormalization(?string $input, string $expected): void
    {
        self::assertSame($expected, pdrRoleNormalize($input));
    }

    public static function roles(): array
    {
        return [
            'legacy administrator' => ['Administrator', 'admin'],
            'admin'                => ['Admin', 'admin'],
            'superadmin'           => ['superadmin', 'admin'],
            'super admin'          => ['Super Admin', 'admin'],
            'staff'                => ['STAFF', 'staff'],
            'employee alias'       => ['employee', 'staff'],
            'member alias'         => ['member', 'staff'],
            'viewer'               => ['Viewer', 'viewer'],
            'padded value'         => ['  staff  ', 'staff'],
            'empty'                => ['', 'viewer'],
            'null'                 => [null, 'viewer'],
            // Unknown roles must fall back to the LOWEST privilege, never admin.
            'unknown'              => ['root', 'viewer'],
        ];
    }

    #[DataProvider('capabilities')]
    public function testCapabilityMatrix(string $capability, array $allowedRoles): void
    {
        foreach (['admin', 'staff', 'viewer'] as $role) {
            self::assertSame(
                in_array($role, $allowedRoles, true),
                pdrCan($capability, $role),
                "pdrCan('$capability') for role '$role'"
            );
        }
    }

    public static function capabilities(): array
    {
        $all   = ['admin', 'staff', 'viewer'];
        $ops   = ['admin', 'staff'];
        $admin = ['admin'];

        return [
            'view'                => ['view', $all],
            'export_reports'      => ['export_reports', $all],
            'manage_reservations' => ['manage_reservations', $ops],
            'manage_payments'     => ['manage_payments', $ops],
            'manage_blackouts'    => ['manage_blackouts', $ops],
            'manage_catalog'      => ['manage_catalog', $admin],
            'manage_staff'        => ['manage_staff', $admin],
            'view_audit'          => ['view_audit', $admin],
            'unknown capability'  => ['drop_tables', []],
        ];
    }

    public function testLabels(): void
    {
        self::assertSame('Administrator', pdrRoleLabel('Administrator'));
        self::assertSame('Staff', pdrRoleLabel('staff'));
        self::assertSame('Viewer', pdrRoleLabel('nonsense'));
    }
}
