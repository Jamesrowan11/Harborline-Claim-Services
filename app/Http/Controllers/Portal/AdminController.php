<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\PipelineStage;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function settings()
    {
        $brandKeys = ['name', 'legal_name', 'parent_company', 'tagline', 'case_prefix', 'phone', 'email', 'address', 'primary_state'];
        $brand = collect($brandKeys)->mapWithKeys(fn ($k) => [$k => Settings::brand($k)]);

        return view('portal.admin.settings', [
            'brand' => $brand,
            'caseNumberFormat' => Settings::get('case_number_format', config('branding.case_number_format')),
            'countiesServed' => Settings::get('counties_served', []),
            'statesServed' => Settings::get('states_served', ['MD']),
            'assignmentRules' => Settings::get('assignment_rules', []),
            'outreachMinVerification' => Settings::get('outreach_min_verification_level', 1),
            'outreachMaxPerWeek' => Settings::get('outreach_max_contacts_per_week', 2),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'brand' => ['nullable', 'array'],
            'brand.*' => ['nullable', 'string', 'max:300'],
            'case_number_format' => ['nullable', 'string', 'max:100'],
            'counties_served' => ['nullable', 'string'],
            'states_served' => ['nullable', 'string'],
            'assignment_rules_json' => ['nullable', 'json'],
            'outreach_min_verification_level' => ['nullable', 'integer', 'min:0', 'max:3'],
            'outreach_max_contacts_per_week' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        foreach (($data['brand'] ?? []) as $key => $value) {
            if ($value !== null) {
                Settings::set("brand.$key", $value, 'branding');
            }
        }
        if (filled($data['case_number_format'] ?? null)) {
            Settings::set('case_number_format', $data['case_number_format'], 'cases');
        }
        if (array_key_exists('counties_served', $data)) {
            Settings::set('counties_served', array_values(array_filter(array_map('trim', explode(',', (string) $data['counties_served'])))), 'service_area');
        }
        if (array_key_exists('states_served', $data)) {
            Settings::set('states_served', array_values(array_filter(array_map('trim', explode(',', (string) $data['states_served'])))), 'service_area');
        }
        if (filled($data['assignment_rules_json'] ?? null)) {
            Settings::set('assignment_rules', json_decode($data['assignment_rules_json'], true), 'leads');
        }
        foreach (['outreach_min_verification_level', 'outreach_max_contacts_per_week'] as $key) {
            if (($data[$key] ?? null) !== null) {
                Settings::set($key, (int) $data[$key], 'compliance');
            }
        }

        AuditEvent::record('settings_changed', null, [], ['keys' => array_keys($request->except('_token'))]);

        return back()->with('status', __('Settings saved.'));
    }

    public function users()
    {
        return view('portal.admin.users', [
            'users' => User::query()->with('roles')->orderBy('name')->paginate(50),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', 'exists:roles,name'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'title' => $data['title'] ?? null,
            'user_type' => $data['role'] === 'Client' ? 'client' : 'staff',
            'password' => Str::password(24),
        ]);
        $user->assignRole($data['role']);

        rescue(fn () => \Illuminate\Support\Facades\Password::sendResetLink(['email' => $user->email]), report: false);
        AuditEvent::record('user_created', $user, [], ['role' => $data['role']]);

        return back()->with('status', __('User created. A password setup link has been emailed.'));
    }

    public function stages()
    {
        return view('portal.admin.stages', [
            'stages' => PipelineStage::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function updateStage(Request $request, PipelineStage $stage)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'client_label' => ['required', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = (bool) ($data['active'] ?? false);
        $stage->update($data);

        return back()->with('status', __('Stage updated.'));
    }

    public function templates()
    {
        return view('portal.admin.templates', [
            'templates' => DocumentTemplate::query()->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function updateTemplate(Request $request, DocumentTemplate $template)
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:250'],
            'body' => ['required', 'string', 'max:50000'],
        ]);

        $template->update([
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'version' => $template->version + 1,
            'approval_status' => 'draft', // edits always require re-approval
            'approved_by' => null,
            'approved_at' => null,
        ]);

        DocumentTemplateVersion::query()->create([
            'document_template_id' => $template->id,
            'version' => $template->version,
            'subject' => $template->subject,
            'body' => $template->body,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        return back()->with('status', __('Template saved as version :v (requires re-approval before use).', ['v' => $template->version]));
    }

    public function approveTemplate(DocumentTemplate $template)
    {
        $template->update([
            'approval_status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        AuditEvent::record('template_approved', $template);

        return back()->with('status', __('Template approved.'));
    }

    public function retention()
    {
        $types = ['leads', 'closed_cases', 'documents', 'communications', 'audit_logs', 'exports', 'temp_files', 'identity_records', 'backups'];
        foreach ($types as $type) {
            RetentionPolicy::query()->firstOrCreate(['record_type' => $type]);
        }

        return view('portal.admin.retention', [
            'policies' => RetentionPolicy::query()->orderBy('record_type')->get(),
        ]);
    }

    public function updateRetention(Request $request)
    {
        $data = $request->validate([
            'policies' => ['required', 'array'],
            'policies.*.retain_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'policies.*.action_after' => ['required', 'in:review,anonymize,delete'],
            'policies.*.active' => ['nullable', 'boolean'],
        ]);

        foreach ($data['policies'] as $id => $values) {
            RetentionPolicy::query()->whereKey($id)->update([
                'retain_months' => $values['retain_months'] ?? null,
                'action_after' => $values['action_after'],
                'active' => (bool) ($values['active'] ?? false),
            ]);
        }

        return back()->with('status', __('Retention policies saved.'));
    }
}
