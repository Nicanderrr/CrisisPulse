<x-layouts.app>
    <div class="cp-page-heading">
        <div><div class="cp-eyebrow">Administration</div><h2 class="cp-page-title">Brand Settings</h2><p class="cp-page-copy">Control the logo used across the CrisisPulse command desk.</p></div>
    </div>
    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <section class="cp-panel cp-brand-settings-preview">
                <div class="cp-panel-heading"><div><h3>Live identity</h3><p>This is how the current logo is used across the system.</p></div><span class="badge text-bg-light border">Global</span></div>
                <div class="cp-brand-preview-stage">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Current CrisisPulse logo">
                    @else
                        <span class="cp-brand-mark cp-brand-preview-mark">CP</span>
                    @endif
                    <div><strong>{{ config('app.name') }}</strong><span>Korle Bu Teaching Hospital communications desk</span></div>
                </div>
                <div class="cp-brand-surface-list">
                    <span><i class="bi bi-check-circle-fill"></i> Sidebar brand</span>
                    <span><i class="bi bi-check-circle-fill"></i> Login page</span>
                    <span><i class="bi bi-check-circle-fill"></i> Browser favicon</span>
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-5">
            <section class="cp-panel">
                <div class="cp-panel-heading"><div><h3>Upload logo</h3><p>Use a clear PNG, JPG, WEBP, SVG, or ICO file up to 4 MB.</p></div></div>
                <form method="POST" action="{{ route('admin.settings.logo.update') }}" enctype="multipart/form-data" class="d-grid gap-3">@csrf
                    <div><label for="logo" class="form-label">Brand logo file</label><input id="logo" name="logo" type="file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.svg,.ico" data-preview-target="logo-preview" required><div id="logo-preview" class="cp-upload-preview" data-file-preview></div></div>
                    <button type="submit" class="btn btn-dark"><i class="bi bi-cloud-arrow-up me-1"></i> Upload and apply</button>
                </form>
                @if ($logoUrl)
                    <form method="POST" action="{{ route('admin.settings.logo.destroy') }}" class="mt-2">@csrf @method('DELETE')<button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i> Remove custom logo</button></form>
                @endif
            </section>
        </div>
        <div class="col-12">
            <section class="cp-panel">
                <div class="cp-panel-heading"><div><h3>Login page media</h3><p>Choose an image or video for the branded visual side of the sign-in page. Images and videos up to 50 MB are supported.</p></div><span class="badge text-bg-light border">Login screen</span></div>
                @if ($loginMediaUrl)
                    <div class="cp-login-media-preview mb-4">
                        @if ($loginMediaType === 'video')
                            <video controls muted preload="metadata"><source src="{{ $loginMediaUrl }}"></video>
                        @else
                            <img src="{{ $loginMediaUrl }}" alt="Current login page media">
                        @endif
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.settings.login-media.update') }}" enctype="multipart/form-data" class="row g-3 align-items-end">@csrf
                    <div class="col-12 col-lg-8"><label for="login_media" class="form-label">Login image or video</label><input id="login_media" name="login_media" type="file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,.webm" data-preview-target="login-media-upload-preview" required><div id="login-media-upload-preview" class="cp-upload-preview" data-file-preview></div></div>
                    <div class="col-12 col-lg-4"><button type="submit" class="btn btn-dark w-100"><i class="bi bi-film me-1"></i> Upload login media</button></div>
                </form>
                @if ($loginMediaUrl)
                    <form method="POST" action="{{ route('admin.settings.login-media.destroy') }}" class="mt-3">@csrf @method('DELETE')<button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i> Remove login media</button></form>
                @endif
            </section>
        </div>
    </div>
</x-layouts.app>
