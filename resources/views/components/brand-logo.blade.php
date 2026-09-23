@props(['iconOnly' => false, 'dark' => false, 'size' => 36])

{{--
    The icon (public/images/logo-icon.png, cropped from the real logo) reads
    fine on both light and dark backgrounds unaided. The "DALOY" text next
    to it is live HTML, not baked into an image, specifically so its color
    can flip for dark backgrounds via the $dark prop — Filament renders
    brandLogo/darkModeBrandLogo as two separate DOM nodes toggled by CSS, so
    each gets its own copy of this component with the right $dark value.

    Every visual property is an inline style, not a Tailwind utility class:
    this renders both on the public landing page (own Vite/Tailwind build)
    and inside the Filament admin panel, which ships its own separate,
    pre-built CSS that doesn't include this app's utilities at all.
--}}
<div {{ $attributes }} style="display: flex; align-items: center; gap: 10px;">
    <img
        src="{{ asset('images/logo-icon.png') }}"
        alt="DALOY"
        style="height: {{ $size }}px; width: auto; display: block; flex-shrink: 0;"
    >

    @unless ($iconOnly)
        <span style="display: flex; flex-direction: column; line-height: 1;">
            <span style="font-size: 1.125rem; font-weight: 700; letter-spacing: -0.01em; color: {{ $dark ? '#FFFFFF' : '#241722' }};">DALOY</span>
            <span style="font-size: 10px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.2em; color: {{ $dark ? 'rgba(255,255,255,0.6)' : 'rgba(122,30,61,0.7)' }};">Document Routing</span>
        </span>
    @endunless
</div>
