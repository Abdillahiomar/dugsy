<?php

use App\Models\School;
use App\Models\SchoolSmtpConfig;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Mail\Message;

new class extends Component
{
    use WithFileUploads;

    public string $activeSection = 'identity';

    // ── Identité ─────────────────────────────────────────────────
    public string $name           = '';
    public string $short_name     = '';
    public string $slogan         = '';
    public string $description    = '';
    public string $school_type    = '';
    public string $ministry_code  = '';
    public string $director_name  = '';
    public string $primary_color  = '#1E2D5A';
    public string $secondary_color = '#E8A838';

    // Logo + bannière
    public $logo  = null;
    public $banner = null;
    public string $existing_logo   = '';
    public string $existing_banner = '';

    // ── Coordonnées ───────────────────────────────────────────────
    public string $email        = '';
    public string $phone        = '';
    public string $phone2       = '';
    public string $fax          = '';
    public string $address      = '';
    public string $address2     = '';
    public string $city         = '';
    public string $country      = 'Djibouti';
    public string $postal_code  = '';
    public string $website      = '';
    public string $facebook     = '';
    public string $instagram    = '';
    public string $twitter      = '';

    // ── SMTP ─────────────────────────────────────────────────────
    public string $smtp_host          = '';
    public string $smtp_port          = '587';
    public string $smtp_encryption    = 'tls';
    public string $smtp_username      = '';
    public string $smtp_password      = '';
    public bool   $smtp_show_password  = false;
    public string $smtp_from_name     = '';
    public string $smtp_from_email    = '';
    public string $smtp_reply_to      = '';
    public bool   $smtp_is_active     = false;

    // Test email
    public string $test_email     = '';
    public ?string $test_result   = null;
    public bool    $test_success  = false;

    // Feedback
    public bool $savedIdentity    = false;
    public bool $savedCoordinates = false;
    public bool $savedSmtp        = false;

    public function mount(): void
    {
        $school = auth()->user()->school;
        if (! $school) return;

        // Identité
        $this->name           = $school->name;
        $this->short_name     = $school->short_name ?? '';
        $this->slogan         = $school->slogan ?? '';
        $this->description    = $school->description ?? '';
        $this->school_type    = $school->school_type ?? '';
        $this->ministry_code  = $school->ministry_code ?? '';
        $this->director_name  = $school->director_name ?? '';
        $this->primary_color  = $school->primary_color ?? '#1E2D5A';
        $this->secondary_color = $school->secondary_color ?? '#E8A838';
        $this->existing_logo   = $school->logo_path ?? '';
        $this->existing_banner = $school->banner_path ?? '';

        // Coordonnées
        $this->email       = $school->email ?? '';
        $this->phone       = $school->phone ?? '';
        $this->phone2      = $school->phone2 ?? '';
        $this->fax         = $school->fax ?? '';
        $this->address     = $school->address ?? '';
        $this->address2    = $school->address2 ?? '';
        $this->city        = $school->city ?? '';
        $this->country     = $school->country ?? 'Djibouti';
        $this->postal_code = $school->postal_code ?? '';
        $this->website     = $school->website ?? '';
        $this->facebook    = $school->facebook ?? '';
        $this->instagram   = $school->instagram ?? '';
        $this->twitter     = $school->twitter ?? '';

        // SMTP
        $smtp = $school->smtpConfig;
        if ($smtp) {
            $this->smtp_host        = $smtp->host ?? '';
            $this->smtp_port        = (string) ($smtp->port ?? '587');
            $this->smtp_encryption  = $smtp->encryption ?? 'tls';
            $this->smtp_username    = $smtp->username ?? '';
            $this->smtp_password    = $smtp->password ?? '';
            $this->smtp_from_name   = $smtp->from_name ?? '';
            $this->smtp_from_email  = $smtp->from_email ?? '';
            $this->smtp_reply_to    = $smtp->reply_to_email ?? '';
            $this->smtp_is_active   = $smtp->is_active;
        } else {
            $this->smtp_from_name  = $school->name;
            $this->smtp_from_email = $school->email ?? '';
        }
    }

    // ── Sauvegarde identité ──────────────────────────────────────

    public function saveIdentity(): void
    {
        $this->validate([
            'name'        => 'required|string|max:200',
            'short_name'  => 'nullable|string|max:50',
            'slogan'      => 'nullable|string|max:200',
            'description' => 'nullable|string|max:1000',
            'logo'        => 'nullable|image|max:2048',
            'banner'      => 'nullable|image|max:4096',
        ]);

        $school = auth()->user()->school;
        $data   = [
            'name'           => $this->name,
            'short_name'     => $this->short_name ?: null,
            'slogan'         => $this->slogan ?: null,
            'description'    => $this->description ?: null,
            'school_type'    => $this->school_type ?: null,
            'ministry_code'  => $this->ministry_code ?: null,
            'director_name'  => $this->director_name ?: null,
            'primary_color'  => $this->primary_color,
            'secondary_color' => $this->secondary_color,
        ];

        if ($this->logo) {
            if ($this->existing_logo) Storage::disk('public')->delete($this->existing_logo);
            $data['logo_path'] = $this->logo->store('schools/logos', 'public');
            $this->existing_logo = $data['logo_path'];
            $this->logo = null;
        }

        if ($this->banner) {
            if ($this->existing_banner) Storage::disk('public')->delete($this->existing_banner);
            $data['banner_path'] = $this->banner->store('schools/banners', 'public');
            $this->existing_banner = $data['banner_path'];
            $this->banner = null;
        }

        $school->update($data);
        $this->savedIdentity = true;
    }

    public function removeLogo(): void
    {
        $school = auth()->user()->school;
        if ($this->existing_logo) {
            Storage::disk('public')->delete($this->existing_logo);
            $school->update(['logo_path' => null]);
            $this->existing_logo = '';
        }
    }

    public function removeBanner(): void
    {
        $school = auth()->user()->school;
        if ($this->existing_banner) {
            Storage::disk('public')->delete($this->existing_banner);
            $school->update(['banner_path' => null]);
            $this->existing_banner = '';
        }
    }

    // ── Sauvegarde coordonnées ────────────────────────────────────

    public function saveCoordinates(): void
    {
        $this->validate([
            'email'   => 'nullable|email|max:200',
            'phone'   => 'nullable|string|max:30',
            'website' => 'nullable|url|max:200',
        ]);

        auth()->user()->school->update([
            'email'       => $this->email ?: null,
            'phone'       => $this->phone ?: null,
            'phone2'      => $this->phone2 ?: null,
            'fax'         => $this->fax ?: null,
            'address'     => $this->address ?: null,
            'address2'    => $this->address2 ?: null,
            'city'        => $this->city ?: null,
            'country'     => $this->country,
            'postal_code' => $this->postal_code ?: null,
            'website'     => $this->website ?: null,
            'facebook'    => $this->facebook ?: null,
            'instagram'   => $this->instagram ?: null,
            'twitter'     => $this->twitter ?: null,
        ]);

        $this->savedCoordinates = true;
    }

    // ── Sauvegarde SMTP ──────────────────────────────────────────

    public function saveSmtp(): void
    {
        $this->validate([
            'smtp_host'       => 'required|string|max:200',
            'smtp_port'       => 'required|integer|min:1|max:65535',
            'smtp_username'   => 'required|string|max:200',
            'smtp_from_email' => 'required|email|max:200',
        ]);

        $school = auth()->user()->school;

        SchoolSmtpConfig::updateOrCreate(
            ['school_id' => $school->id],
            [
                'host'            => $this->smtp_host,
                'port'            => (int) $this->smtp_port,
                'encryption'      => $this->smtp_encryption,
                'username'        => $this->smtp_username,
                'password'        => $this->smtp_password ?: null,
                'from_name'       => $this->smtp_from_name ?: $school->name,
                'from_email'      => $this->smtp_from_email,
                'reply_to_email'  => $this->smtp_reply_to ?: null,
                'is_active'       => $this->smtp_is_active,
            ]
        );

        $this->savedSmtp = true;
    }

    // ── Test SMTP ────────────────────────────────────────────────

    public function testSmtp(): void
    {
        $this->validate([
            'test_email'      => 'required|email',
            'smtp_host'       => 'required',
            'smtp_username'   => 'required',
            'smtp_from_email' => 'required|email',
        ]);

        try {
            // Configuration temporaire du mailer avec les paramètres saisis
            config([
                'mail.mailers.smtp.host'       => $this->smtp_host,
                'mail.mailers.smtp.port'       => (int) $this->smtp_port,
                'mail.mailers.smtp.encryption' => $this->smtp_encryption !== 'none' ? $this->smtp_encryption : null,
                'mail.mailers.smtp.username'   => $this->smtp_username,
                'mail.mailers.smtp.password'   => $this->smtp_password,
                'mail.from.address'            => $this->smtp_from_email,
                'mail.from.name'               => $this->smtp_from_name ?: auth()->user()->school->name,
            ]);

            Mail::raw(
                "Ceci est un email de test envoyé depuis Dugsi.\n\nSi vous recevez cet email, votre configuration SMTP est correcte.",
                function (Message $msg) {
                    $msg->to($this->test_email)
                        ->subject('[Dugsi] Test de configuration email');
                }
            );

            // Marquer comme vérifié
            SchoolSmtpConfig::where('school_id', auth()->user()->school_id)
                ->update(['is_verified' => true, 'last_tested_at' => now()]);

            $this->test_result  = "Email envoyé avec succès à {$this->test_email}.";
            $this->test_success = true;

        } catch (\Exception $e) {
            $this->test_result  = "Echec : " . $e->getMessage();
            $this->test_success = false;
        }
    }

    public function with(): array
    {
        return [
            'school'     => auth()->user()->school,
            'smtpConfig' => auth()->user()->school?->smtpConfig,
        ];
    }
}; ?>

<style>
    /* ── Nav sections ── */
    .section-nav {
        display: flex; gap: 0.25rem;
        background: var(--paper); border: 1px solid var(--line);
        border-radius: 10px; padding: 4px;
        margin-bottom: 1.5rem; flex-wrap: wrap;
    }
    .section-nav-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 0.45rem 1rem; border-radius: 7px;
        font-size: 0.875rem; font-weight: 500; font-family: 'Inter', sans-serif;
        color: var(--ink); border: none; cursor: pointer; background: none;
        transition: background 0.12s, color 0.12s; opacity: 0.55;
    }
    .section-nav-btn svg { width: 15px; height: 15px; }
    .section-nav-btn:hover { opacity: 0.9; background: var(--paper-raised); }
    .section-nav-btn.active { background: var(--sidebar); color: #FFFFFF; opacity: 1; }

    /* ── Layout ── */
    .page-grid { display: grid; grid-template-columns: 1fr 300px; gap: 1.5rem; align-items: start; }
    @media (max-width: 900px) { .page-grid { grid-template-columns: 1fr; } }

    /* ── Cards ── */
    .card { border-radius: 12px; border: 1px solid var(--line); background: var(--paper-raised); overflow: hidden; margin-bottom: 1.25rem; }
    .card:last-child { margin-bottom: 0; }
    .card-header {
        padding: 0.875rem 1.5rem; border-bottom: 1px solid var(--line);
        display: flex; align-items: center; gap: 0.65rem;
    }
    .card-header-icon {
        width: 28px; height: 28px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .card-header-icon svg { width: 15px; height: 15px; }
    .card-title { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--ink); }
    .card-body { padding: 1.25rem 1.5rem; }

    /* ── Formulaire ── */
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    @media (max-width: 700px) { .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; } }
    .form-field { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-field.full { grid-column: 1 / -1; }
    .form-label { font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink); opacity: 0.5; }
    .form-hint  { font-size: 0.75rem; color: var(--ink); opacity: 0.45; margin-top: 2px; }
    .form-input, .form-select-inp, .form-textarea {
        padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper); font-size: 0.875rem; font-family: 'Inter', sans-serif;
        color: var(--ink); outline: none; width: 100%;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .form-input:focus, .form-select-inp:focus, .form-textarea:focus {
        border-color: var(--sidebar-soft); box-shadow: 0 0 0 3px rgba(42,63,126,0.08);
    }
    .form-textarea { resize: vertical; min-height: 80px; line-height: 1.5; }
    .form-error { font-size: 0.75rem; color: var(--accent-red); margin-top: 0.2rem; }
    .form-actions { display: flex; align-items: center; justify-content: flex-end; gap: 0.65rem; padding-top: 1.25rem; border-top: 1px solid var(--line); margin-top: 1.25rem; }
    .btn-save {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 0.5rem 1.25rem; border-radius: 8px;
        background: var(--sidebar); color: #FFFFFF;
        font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.15s;
    }
    .btn-save:hover { background: var(--sidebar-soft); }
    .btn-save svg { width: 15px; height: 15px; }
    .btn-secondary {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 0.5rem 1rem; border-radius: 8px;
        border: 1px solid var(--line); background: var(--paper);
        font-size: 0.875rem; font-weight: 500; font-family: 'Inter', sans-serif;
        color: var(--ink); cursor: pointer; transition: border-color 0.15s;
    }
    .btn-secondary:hover { border-color: var(--sidebar-soft); color: var(--sidebar-soft); }

    /* Toast */
    .toast-success {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 0.65rem 1rem; border-radius: 8px;
        background: rgba(30,120,80,0.1); border: 1px solid rgba(30,120,80,0.2);
        color: #1A6040; font-size: 0.875rem; font-weight: 500;
        margin-bottom: 1rem; animation: slideDown 0.15s ease;
    }
    .toast-success svg { width: 16px; height: 16px; flex-shrink: 0; }
    @keyframes slideDown { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:translateY(0); } }

    /* ── Logo upload ── */
    .upload-zone {
        border: 1.5px dashed var(--line); border-radius: 10px;
        padding: 1.5rem; text-align: center; cursor: pointer;
        transition: border-color 0.15s, background 0.15s;
        position: relative;
    }
    .upload-zone:hover { border-color: var(--sidebar-soft); background: rgba(42,63,126,0.03); }
    .upload-zone input[type=file] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer;
    }
    .upload-icon { font-size: 28px; opacity: 0.3; margin-bottom: 6px; }
    .upload-label { font-size: 0.875rem; color: var(--ink); opacity: 0.6; }
    .upload-hint  { font-size: 0.75rem; color: var(--ink); opacity: 0.4; margin-top: 3px; }

    .logo-preview {
        display: flex; align-items: center; gap: 0.875rem;
        padding: 0.875rem; border-radius: 10px;
        border: 1px solid var(--line); background: var(--paper);
    }
    .logo-img { width: 56px; height: 56px; border-radius: 8px; object-fit: contain; }
    .logo-placeholder {
        width: 56px; height: 56px; border-radius: 8px;
        background: rgba(42,63,126,0.08); display: flex; align-items: center;
        justify-content: center; font-family: 'Fraunces', serif;
        font-size: 1.25rem; font-weight: 600; color: var(--sidebar-soft);
    }
    .logo-actions { display: flex; flex-direction: column; gap: 0.35rem; }
    .logo-name { font-size: 0.875rem; font-weight: 500; color: var(--ink); }
    .logo-sub  { font-size: 0.75rem; color: var(--ink); opacity: 0.45; }
    .btn-remove {
        font-size: 0.75rem; color: var(--accent-red);
        background: none; border: none; cursor: pointer; padding: 0;
        text-align: left;
    }
    .btn-remove:hover { text-decoration: underline; }

    /* Couleurs */
    .color-row { display: flex; align-items: center; gap: 0.75rem; }
    .color-input { width: 48px; height: 36px; border-radius: 8px; border: 1px solid var(--line); cursor: pointer; padding: 3px; background: var(--paper); }
    .color-text { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--ink); opacity: 0.6; }

    /* ── Carte de prévisualisation ── */
    .preview-card {
        border-radius: 12px; border: 1px solid var(--line);
        background: var(--paper-raised); overflow: hidden;
    }
    .preview-banner { height: 70px; background: linear-gradient(135deg, var(--sidebar) 0%, var(--sidebar-soft) 100%); position: relative; }
    .preview-banner img { width: 100%; height: 100%; object-fit: cover; }
    .preview-logo-wrap {
        position: absolute; bottom: -18px; left: 16px;
        width: 40px; height: 40px; border-radius: 8px;
        border: 2px solid var(--paper-raised); background: var(--paper-raised);
        display: flex; align-items: center; justify-content: center; overflow: hidden;
    }
    .preview-logo-wrap img { width: 100%; height: 100%; object-fit: contain; }
    .preview-logo-placeholder {
        font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 700;
        color: var(--sidebar-soft);
    }
    .preview-body { padding: 1.5rem 1rem 1rem; }
    .preview-name { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--ink); }
    .preview-slogan { font-size: 0.75rem; color: var(--ink); opacity: 0.5; margin-top: 2px; }
    .preview-meta { margin-top: 0.75rem; display: flex; flex-direction: column; gap: 4px; }
    .preview-meta-row { display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--ink); opacity: 0.6; }
    .preview-meta-row svg { width: 12px; height: 12px; flex-shrink: 0; opacity: 0.5; }

    /* ── SMTP ── */
    .smtp-status {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.25rem;
        font-size: 0.875rem; font-weight: 500;
    }
    .smtp-status.active   { background: rgba(30,120,80,0.08); color: #1A6040; border: 1px solid rgba(30,120,80,0.2); }
    .smtp-status.inactive { background: rgba(0,0,0,0.05); color: var(--ink); opacity: 0.6; border: 1px solid var(--line); }
    .smtp-dot { width: 8px; height: 8px; border-radius: 50%; }
    .smtp-dot.on  { background: #22c55e; }
    .smtp-dot.off { background: var(--line); }

    .password-wrap { position: relative; }
    .password-toggle {
        position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
        background: none; border: none; cursor: pointer; padding: 0;
        color: var(--ink); opacity: 0.4;
    }
    .password-toggle:hover { opacity: 0.8; }
    .password-toggle svg { width: 16px; height: 16px; }

    .test-result {
        padding: 0.75rem 1rem; border-radius: 8px; margin-top: 0.75rem;
        font-size: 0.875rem; animation: slideDown 0.15s ease;
    }
    .test-result.ok  { background: rgba(30,120,80,0.08); color: #1A6040; border: 1px solid rgba(30,120,80,0.2); }
    .test-result.err { background: rgba(224,92,58,0.08); color: var(--accent-red); border: 1px solid rgba(224,92,58,0.2); }

    /* Toggle */
    .toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 0; }
    .toggle-label { font-size: 0.875rem; font-weight: 500; color: var(--ink); }
    .toggle-desc  { font-size: 0.8rem; color: var(--ink); opacity: 0.5; margin-top: 2px; }
    .toggle-switch { position: relative; width: 40px; height: 22px; cursor: pointer; flex-shrink: 0; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider { position: absolute; inset: 0; border-radius: 22px; background: var(--line); transition: background 0.2s; }
    .toggle-slider::before { content: ''; position: absolute; width: 16px; height: 16px; border-radius: 50%; background: white; top: 3px; left: 3px; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
    .toggle-switch input:checked + .toggle-slider { background: var(--sidebar-soft); }
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(18px); }

    /* Réseau sociaux */
    .social-row { display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.75rem; }
    .social-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .social-icon svg { width: 17px; height: 17px; }
</style>

<div>
    {{-- Navigation --}}
    <nav class="section-nav">
        @foreach ([
            ['key' => 'identity',    'label' => 'Identité',     'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
            ['key' => 'coordinates', 'label' => 'Coordonnées',   'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z'],
            ['key' => 'smtp',        'label' => 'Email & SMTP',  'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ] as $s)
            <button wire:click="$set('activeSection', '{{ $s['key'] }}')"
                    class="section-nav-btn {{ $activeSection === $s['key'] ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $s['icon'] }}"/>
                </svg>
                {{ $s['label'] }}
            </button>
        @endforeach
    </nav>

    {{-- ═══════════════════════════════════════ --}}
    {{-- SECTION : IDENTITÉ --}}
    {{-- ═══════════════════════════════════════ --}}
    @if ($activeSection === 'identity')

        @if ($savedIdentity)
            <div class="toast-success" x-data x-init="setTimeout(() => $el.remove(), 3000)">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Identité enregistrée.
            </div>
        @endif

        <div class="page-grid">
            <div>
                {{-- Informations de base --}}
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon" style="background:rgba(42,63,126,0.08); color:var(--sidebar-soft);">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <span class="card-title">Informations de l'établissement</span>
                    </div>
                    <div class="card-body">
                        <div class="form-grid-2">
                            <div class="form-field">
                                <label class="form-label">Nom complet *</label>
                                <input wire:model="name" type="text" class="form-input" placeholder="Institut El Amal">
                                @error('name') <span class="form-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-field">
                                <label class="form-label">Nom abrégé</label>
                                <input wire:model="short_name" type="text" class="form-input" placeholder="IEA">
                                <span class="form-hint">Utilisé dans les en-têtes et bulletins.</span>
                            </div>
                        </div>
                        <div class="form-grid-2" style="margin-bottom:1rem;">
                            <div class="form-field">
                                <label class="form-label">Type d'établissement</label>
                                <select wire:model="school_type" class="form-select-inp">
                                    <option value="">— Sélectionner —</option>
                                    <option value="Privé laïque">Privé laïque</option>
                                    <option value="Privé islamique">Privé islamique</option>
                                    <option value="Privé international">Privé international</option>
                                    <option value="Public">Public</option>
                                    <option value="Semi-public">Semi-public</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label class="form-label">Code Ministère</label>
                                <input wire:model="ministry_code" type="text" class="form-input" placeholder="MEN-DJ-001">
                                <span class="form-hint">Numéro d'agrément officiel.</span>
                            </div>
                        </div>
                        <div class="form-field" style="margin-bottom:1rem;">
                            <label class="form-label">Directeur / Directrice</label>
                            <input wire:model="director_name" type="text" class="form-input" placeholder="M. Ahmed Hassan">
                        </div>
                        <div class="form-field" style="margin-bottom:1rem;">
                            <label class="form-label">Slogan / Devise</label>
                            <input wire:model="slogan" type="text" class="form-input" placeholder="L'excellence au service de l'avenir">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Description</label>
                            <textarea wire:model="description" class="form-textarea"
                                      placeholder="Décrivez brièvement votre établissement..."></textarea>
                        </div>
                    </div>
                </div>

                {{-- Logo & Bannière --}}
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon" style="background:rgba(232,168,56,0.12); color:#8A6010;">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <span class="card-title">Logo & Bannière</span>
                    </div>
                    <div class="card-body">
                        {{-- Logo --}}
                        <div class="form-field" style="margin-bottom:1.25rem;">
                            <label class="form-label">Logo de l'école</label>
                            @if ($existing_logo || $logo)
                                <div class="logo-preview">
                                    @if ($logo)
                                        <img src="{{ $logo->temporaryUrl() }}" class="logo-img" alt="Logo">
                                    @elseif ($existing_logo)
                                        <img src="{{ asset('storage/'.$existing_logo) }}" class="logo-img" alt="Logo">
                                    @endif
                                    <div class="logo-actions">
                                        <span class="logo-name">Logo actuel</span>
                                        <span class="logo-sub">JPG, PNG ou SVG • max 2 Mo</span>
                                        <button wire:click="removeLogo" class="btn-remove">Supprimer</button>
                                    </div>
                                </div>
                            @else
                                <div class="upload-zone">
                                    <input wire:model="logo" type="file" accept="image/*">
                                    <div class="upload-icon">🖼</div>
                                    <div class="upload-label">Glisser-déposer ou cliquer</div>
                                    <div class="upload-hint">JPG, PNG, SVG — max 2 Mo — fond transparent recommandé</div>
                                </div>
                            @endif
                            @error('logo') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        {{-- Bannière --}}
                        <div class="form-field">
                            <label class="form-label">Bannière (image de couverture)</label>
                            @if ($existing_banner || $banner)
                                <div class="logo-preview" style="flex-direction:column; align-items:flex-start;">
                                    @if ($banner)
                                        <img src="{{ $banner->temporaryUrl() }}" style="width:100%; height:80px; object-fit:cover; border-radius:8px; margin-bottom:8px;">
                                    @elseif ($existing_banner)
                                        <img src="{{ asset('storage/'.$existing_banner) }}" style="width:100%; height:80px; object-fit:cover; border-radius:8px; margin-bottom:8px;">
                                    @endif
                                    <div style="display:flex; align-items:center; gap:0.65rem;">
                                        <div class="logo-actions">
                                            <span class="logo-name">Bannière actuelle</span>
                                            <button wire:click="removeBanner" class="btn-remove">Supprimer</button>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="upload-zone">
                                    <input wire:model="banner" type="file" accept="image/*">
                                    <div class="upload-icon">🌅</div>
                                    <div class="upload-label">Image de couverture</div>
                                    <div class="upload-hint">JPG, PNG — min 1200×300px recommandé — max 4 Mo</div>
                                </div>
                            @endif
                            @error('banner') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Couleurs --}}
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon" style="background:rgba(99,102,241,0.1); color:#3730A3;">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                        </div>
                        <span class="card-title">Couleurs de l'établissement</span>
                    </div>
                    <div class="card-body">
                        <div class="form-grid-2">
                            <div class="form-field">
                                <label class="form-label">Couleur principale</label>
                                <div class="color-row">
                                    <input wire:model.live="primary_color" type="color" class="color-input">
                                    <span class="color-text">{{ $primary_color }}</span>
                                </div>
                                <span class="form-hint">Utilisée pour la sidebar, les en-têtes, les boutons principaux.</span>
                            </div>
                            <div class="form-field">
                                <label class="form-label">Couleur secondaire / accent</label>
                                <div class="color-row">
                                    <input wire:model.live="secondary_color" type="color" class="color-input">
                                    <span class="color-text">{{ $secondary_color }}</span>
                                </div>
                                <span class="form-hint">Utilisée pour les badges, les highlights.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; margin-top:0.5rem;">
                    <button wire:click="saveIdentity" class="btn-save">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                        Enregistrer
                    </button>
                </div>
            </div>

            {{-- Carte de prévisualisation --}}
            <div>
                <div class="preview-card" style="position:sticky; top:1.5rem;">
                    <div class="preview-banner" style="{{ $primary_color ? 'background: linear-gradient(135deg, '.$primary_color.' 0%, '.$primary_color.'CC 100%)' : '' }}">
                        @if ($existing_banner || $banner)
                            <img src="{{ $banner ? $banner->temporaryUrl() : asset('storage/'.$existing_banner) }}" alt="">
                        @endif
                        <div class="preview-logo-wrap">
                            @if ($existing_logo || $logo)
                                <img src="{{ $logo ? $logo->temporaryUrl() : asset('storage/'.$existing_logo) }}" alt="">
                            @else
                                <span class="preview-logo-placeholder">
                                    {{ strtoupper(substr($name ?: 'D', 0, 1)) }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="preview-body">
                        <div class="preview-name">{{ $name ?: 'Nom de l\'école' }}</div>
                        @if ($slogan)
                            <div class="preview-slogan">{{ $slogan }}</div>
                        @endif
                        <div class="preview-meta">
                            @if ($school_type)
                                <div class="preview-meta-row">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                                    {{ $school_type }}
                                </div>
                            @endif
                            @if ($director_name)
                                <div class="preview-meta-row">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    {{ $director_name }}
                                </div>
                            @endif
                            @if ($ministry_code)
                                <div class="preview-meta-row">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                                    {{ $ministry_code }}
                                </div>
                            @endif
                        </div>

                        {{-- Aperçu couleurs --}}
                        <div style="margin-top:1rem; display:flex; gap:0.5rem;">
                            <div style="flex:1; height:28px; border-radius:6px; background:{{ $primary_color }}; display:flex; align-items:center; justify-content:center;">
                                <span style="font-size:10px; color:white; font-family:'JetBrains Mono',monospace; font-weight:600;">Principale</span>
                            </div>
                            <div style="width:60px; height:28px; border-radius:6px; background:{{ $secondary_color }};"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @endif

    {{-- ═══════════════════════════════════════ --}}
    {{-- SECTION : COORDONNÉES --}}
    {{-- ═══════════════════════════════════════ --}}
    @if ($activeSection === 'coordinates')

        @if ($savedCoordinates)
            <div class="toast-success" x-data x-init="setTimeout(() => $el.remove(), 3000)">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Coordonnées enregistrées.
            </div>
        @endif

        {{-- Contact --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon" style="background:rgba(30,120,80,0.08); color:#1A6040;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </div>
                <span class="card-title">Contact</span>
            </div>
            <div class="card-body">
                <div class="form-grid-2">
                    <div class="form-field">
                        <label class="form-label">Email principal</label>
                        <input wire:model="email" type="email" class="form-input" placeholder="contact@ecole.dj">
                        @error('email') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-field">
                        <label class="form-label">Site web</label>
                        <input wire:model="website" type="url" class="form-input" placeholder="https://www.ecole.dj">
                    </div>
                </div>
                <div class="form-grid-3">
                    <div class="form-field">
                        <label class="form-label">Téléphone principal</label>
                        <input wire:model="phone" type="tel" class="form-input" placeholder="77 00 00 00">
                    </div>
                    <div class="form-field">
                        <label class="form-label">Téléphone secondaire</label>
                        <input wire:model="phone2" type="tel" class="form-input" placeholder="21 00 00 00">
                    </div>
                    <div class="form-field">
                        <label class="form-label">Fax</label>
                        <input wire:model="fax" type="tel" class="form-input" placeholder="21 00 00 00">
                    </div>
                </div>
            </div>
        </div>

        {{-- Adresse --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon" style="background:rgba(224,92,58,0.1); color:var(--accent-red);">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <span class="card-title">Adresse</span>
            </div>
            <div class="card-body">
                <div class="form-field" style="margin-bottom:1rem;">
                    <label class="form-label">Adresse ligne 1</label>
                    <input wire:model="address" type="text" class="form-input" placeholder="Quartier, Rue, N°">
                </div>
                <div class="form-field" style="margin-bottom:1rem;">
                    <label class="form-label">Adresse ligne 2</label>
                    <input wire:model="address2" type="text" class="form-input" placeholder="Bâtiment, étage...">
                </div>
                <div class="form-grid-3">
                    <div class="form-field">
                        <label class="form-label">Ville</label>
                        <input wire:model="city" type="text" class="form-input" placeholder="Djibouti">
                    </div>
                    <div class="form-field">
                        <label class="form-label">Pays</label>
                        <select wire:model="country" class="form-select-inp">
                            <option value="Djibouti">Djibouti</option>
                            <option value="Ethiopie">Ethiopie</option>
                            <option value="Somalie">Somalie</option>
                            <option value="Érythrée">Érythrée</option>
                            <option value="France">France</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Code postal</label>
                        <input wire:model="postal_code" type="text" class="form-input" placeholder="BP 001">
                    </div>
                </div>
            </div>
        </div>

        {{-- Réseaux sociaux --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon" style="background:rgba(99,102,241,0.1); color:#3730A3;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                </div>
                <span class="card-title">Réseaux sociaux</span>
            </div>
            <div class="card-body">
                <div class="social-row">
                    <div class="social-icon" style="background:#1877F220; color:#1877F2;">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </div>
                    <input wire:model="facebook" type="url" class="form-input" placeholder="https://facebook.com/votre-ecole">
                </div>
                <div class="social-row">
                    <div class="social-icon" style="background:#E1306C20; color:#E1306C;">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </div>
                    <input wire:model="instagram" type="url" class="form-input" placeholder="https://instagram.com/votre-ecole">
                </div>
                <div class="social-row" style="margin-bottom:0;">
                    <div class="social-icon" style="background:#1DA1F220; color:#1DA1F2;">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                    </div>
                    <input wire:model="twitter" type="url" class="form-input" placeholder="https://twitter.com/votre-ecole">
                </div>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:0.5rem;">
            <button wire:click="saveCoordinates" class="btn-save">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                Enregistrer les coordonnées
            </button>
        </div>

    @endif

    {{-- ═══════════════════════════════════════ --}}
    {{-- SECTION : EMAIL & SMTP --}}
    {{-- ═══════════════════════════════════════ --}}
    @if ($activeSection === 'smtp')

        @if ($savedSmtp)
            <div class="toast-success" x-data x-init="setTimeout(() => $el.remove(), 3000)">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Configuration SMTP enregistrée.
            </div>
        @endif

        {{-- Statut actuel --}}
        @if ($smtpConfig)
            <div class="smtp-status {{ $smtp_is_active ? 'active' : 'inactive' }}">
                <div class="smtp-dot {{ $smtp_is_active ? 'on' : 'off' }}"></div>
                @if ($smtp_is_active)
                    SMTP actif — les notifications par email sont activées.
                    @if ($smtpConfig->last_tested_at)
                        Dernier test : {{ $smtpConfig->last_tested_at->diffForHumans() }}.
                    @endif
                @else
                    SMTP inactif — les notifications par email sont désactivées.
                @endif
            </div>
        @endif

        {{-- Serveur SMTP --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon" style="background:rgba(42,63,126,0.08); color:var(--sidebar-soft);">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                </div>
                <span class="card-title">Serveur SMTP</span>
            </div>
            <div class="card-body">
                <div class="form-grid-3" style="margin-bottom:1rem;">
                    <div class="form-field" style="grid-column:1/3;">
                        <label class="form-label">Hôte SMTP *</label>
                        <input wire:model="smtp_host" type="text" class="form-input" placeholder="smtp.gmail.com">
                        @error('smtp_host') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-field">
                        <label class="form-label">Port *</label>
                        <input wire:model="smtp_port" type="number" class="form-input" placeholder="587">
                    </div>
                </div>
                <div class="form-grid-2" style="margin-bottom:1rem;">
                    <div class="form-field">
                        <label class="form-label">Chiffrement</label>
                        <select wire:model="smtp_encryption" class="form-select-inp">
                            <option value="tls">TLS (port 587 recommandé)</option>
                            <option value="ssl">SSL (port 465)</option>
                            <option value="none">Aucun (port 25 — non recommandé)</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Nom d'utilisateur *</label>
                        <input wire:model="smtp_username" type="text" class="form-input" placeholder="votre@email.com">
                        @error('smtp_username') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="form-field">
                    <label class="form-label">Mot de passe SMTP</label>
                    <div class="password-wrap">
                        <input wire:model="smtp_password"
                               type="{{ $smtp_show_password ? 'text' : 'password' }}"
                               class="form-input"
                               placeholder="{{ $smtp_password ? '••••••••' : 'Mot de passe ou clé d\'application' }}"
                               style="padding-right:2.5rem;">
                        <button type="button" wire:click="$toggle('smtp_show_password')" class="password-toggle">
                            @if ($smtp_show_password)
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            @else
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            @endif
                        </button>
                    </div>
                    <span class="form-hint">Laissez vide pour conserver le mot de passe actuel. Pour Gmail, utilisez un "mot de passe d'application" (pas votre mot de passe principal).</span>
                </div>
            </div>
        </div>

        {{-- Expéditeur --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon" style="background:rgba(30,120,80,0.08); color:#1A6040;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <span class="card-title">Expéditeur des emails</span>
            </div>
            <div class="card-body">
                <div class="form-grid-2" style="margin-bottom:1rem;">
                    <div class="form-field">
                        <label class="form-label">Nom de l'expéditeur *</label>
                        <input wire:model="smtp_from_name" type="text" class="form-input" placeholder="Institut El Amal">
                        @error('smtp_from_name') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-field">
                        <label class="form-label">Email de l'expéditeur *</label>
                        <input wire:model="smtp_from_email" type="email" class="form-input" placeholder="no-reply@ecole.dj">
                        @error('smtp_from_email') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="form-field">
                    <label class="form-label">Email de réponse (Reply-To)</label>
                    <input wire:model="smtp_reply_to" type="email" class="form-input" placeholder="contact@ecole.dj">
                    <span class="form-hint">Si différent de l'expéditeur — les réponses seront redirigées ici.</span>
                </div>

                <div class="toggle-row" style="border-top:1px solid var(--line); margin-top:1.25rem;">
                    <div>
                        <div class="toggle-label">Activer les notifications email</div>
                        <div style="font-size:0.8rem; color:var(--ink); opacity:0.5; margin-top:2px;">Les emails de notifications (nouvelles factures, bulletins, etc.) seront envoyés via ce serveur SMTP.</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" wire:model="smtp_is_active">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Test --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon" style="background:rgba(232,168,56,0.12); color:#8A6010;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <span class="card-title">Tester la configuration</span>
            </div>
            <div class="card-body">
                <div style="display:flex; gap:0.75rem; align-items:flex-end; flex-wrap:wrap;">
                    <div class="form-field" style="flex:1; min-width:200px; margin:0;">
                        <label class="form-label">Email de test</label>
                        <input wire:model="test_email" type="email" class="form-input" placeholder="votre@email.com">
                        @error('test_email') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <button wire:click="testSmtp" class="btn-save" style="flex-shrink:0;">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        Envoyer un email test
                    </button>
                </div>
                @if ($test_result)
                    <div class="test-result {{ $test_success ? 'ok' : 'err' }}">
                        {{ $test_result }}
                    </div>
                @endif
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:0.5rem;">
            <button wire:click="saveSmtp" class="btn-save">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                Enregistrer la configuration SMTP
            </button>
        </div>

    @endif

</div>
