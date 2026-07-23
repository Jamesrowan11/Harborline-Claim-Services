<div {{ $attributes->merge(['class' => 'rounded-md border border-gold-200 bg-gold-50 px-5 py-4 text-sm leading-relaxed text-navy-800']) }}>
    <p>
        <span class="font-semibold">{{ \App\Services\Settings::brand('name') }}</span>
        {{ ltrim(\Illuminate\Support\Str::after(config('branding.disclaimer'), 'Harborline Claim Services')) }}
    </p>
</div>
