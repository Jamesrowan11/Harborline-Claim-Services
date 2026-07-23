<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        // Leads & cases
        'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.assign',
        'cases.view', 'cases.create', 'cases.update', 'cases.delete', 'cases.assign',
        'cases.change_stage', 'cases.merge', 'cases.hold',
        // Research
        'sources.view', 'sources.manage', 'surplus.view', 'surplus.verify',
        // Parties
        'claimants.view', 'claimants.manage', 'claimants.view_sensitive',
        // Work
        'tasks.view', 'tasks.manage', 'deadlines.manage', 'notes.manage',
        // Documents & communications
        'documents.view', 'documents.upload', 'documents.approve', 'documents.download',
        'templates.manage', 'templates.approve',
        'communications.view', 'communications.send', 'communications.approve',
        'agreements.view', 'agreements.manage', 'agreements.approve',
        // Compliance & legal
        'compliance.review', 'attorney.review', 'legal_holds.manage',
        // Financial
        'claims.view', 'claims.manage', 'payments.view', 'payments.manage', 'expenses.manage',
        // Automations
        'automations.view', 'automations.manage', 'automations.approve',
        // Reporting
        'reports.view', 'reports.export', 'audit.view',
        // Administration
        'admin.settings', 'admin.users', 'admin.roles', 'admin.retention', 'admin.integrations',
    ];

    public const ROLES = [
        'Super Administrator' => '*',
        'Company Administrator' => [
            'leads.*', 'cases.*', 'sources.*', 'surplus.*', 'claimants.view', 'claimants.manage',
            'tasks.*', 'deadlines.manage', 'notes.manage', 'documents.*', 'templates.*',
            'communications.*', 'agreements.view', 'agreements.manage', 'claims.*', 'payments.*',
            'expenses.manage', 'automations.view', 'automations.manage', 'reports.*',
            'admin.settings', 'admin.users', 'compliance.review',
        ],
        'Researcher' => [
            'leads.view', 'leads.update', 'cases.view', 'cases.update', 'cases.change_stage',
            'sources.*', 'surplus.view', 'surplus.verify', 'claimants.view', 'tasks.view',
            'tasks.manage', 'notes.manage', 'documents.view', 'documents.upload', 'reports.view',
        ],
        'Case Manager' => [
            'leads.*', 'cases.view', 'cases.create', 'cases.update', 'cases.assign',
            'cases.change_stage', 'sources.view', 'surplus.view', 'claimants.view',
            'claimants.manage', 'tasks.*', 'deadlines.manage', 'notes.manage', 'documents.view',
            'documents.upload', 'documents.download', 'communications.view', 'communications.send',
            'agreements.view', 'agreements.manage', 'claims.view', 'claims.manage',
            'reports.view', 'reports.export',
        ],
        'Compliance Reviewer' => [
            'cases.view', 'cases.hold', 'claimants.view', 'claimants.view_sensitive',
            'documents.view', 'documents.approve', 'templates.approve', 'communications.view',
            'communications.approve', 'agreements.view', 'agreements.approve',
            'compliance.review', 'automations.view', 'automations.approve', 'audit.view', 'reports.view',
        ],
        'Attorney' => [
            'cases.view', 'claimants.view', 'documents.view', 'documents.download',
            'agreements.view', 'agreements.approve', 'attorney.review', 'notes.manage', 'reports.view',
        ],
        'Accounting User' => [
            'cases.view', 'claims.view', 'payments.*', 'expenses.manage',
            'reports.view', 'reports.export',
        ],
        'Read-Only Auditor' => [
            'leads.view', 'cases.view', 'sources.view', 'surplus.view', 'claimants.view',
            'tasks.view', 'documents.view', 'communications.view', 'agreements.view',
            'claims.view', 'payments.view', 'automations.view', 'reports.view', 'audit.view',
        ],
        'Client' => [], // Client portal access is scoped by policies, not staff permissions.
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach (self::ROLES as $roleName => $grants) {
            $role = Role::findOrCreate($roleName);

            if ($grants === '*') {
                $role->syncPermissions(Permission::all());
                continue;
            }

            $resolved = collect($grants)->flatMap(function (string $grant) {
                if (str_ends_with($grant, '.*')) {
                    $prefix = substr($grant, 0, -1); // keep trailing dot
                    return collect(self::PERMISSIONS)->filter(fn ($p) => str_starts_with($p, $prefix));
                }
                return [$grant];
            })->unique()->values()->all();

            $role->syncPermissions($resolved);
        }
    }
}
