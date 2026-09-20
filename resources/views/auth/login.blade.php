<x-layouts.app>
    <div class="relative isolate flex min-h-screen items-center justify-center overflow-hidden bg-[#031733] px-4 py-10 sm:px-6 lg:px-8">
        <div class="pointer-events-none absolute -left-40 -top-36 h-[34rem] w-[34rem] rounded-full bg-blue-500/15 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-48 -right-32 h-[36rem] w-[36rem] rounded-full bg-cyan-300/10 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 1px 1px, rgba(125,211,252,.28) 1px, transparent 0); background-size: 28px 28px;"></div>

        <div class="relative grid w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/10 bg-slate-950/55 shadow-2xl shadow-black/40 backdrop-blur-xl lg:grid-cols-[1.05fr_.95fr]">
            <section class="relative hidden overflow-hidden border-r border-white/10 p-10 lg:flex lg:flex-col lg:justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 via-transparent to-cyan-300/10"></div>

                <div class="relative">
                    <div class="flex items-center gap-4">
                        <div class="grid h-14 w-14 place-items-center rounded-2xl bg-cyan-300/10 ring-1 ring-inset ring-cyan-200/20 shadow-lg shadow-cyan-950/40">
                            <svg viewBox="0 0 56 56" class="h-10 w-10" aria-hidden="true">
                                <defs>
                                    <linearGradient id="fieldpulse-login-mark" x1="8" y1="7" x2="47" y2="48" gradientUnits="userSpaceOnUse">
                                        <stop stop-color="#2E7CFF"/>
                                        <stop offset="1" stop-color="#26D8FF"/>
                                    </linearGradient>
                                </defs>
                                <path d="M28 5.5c-10.7 0-19.4 8.7-19.4 19.4 0 13.1 15.2 24 18.2 26.1.7.5 1.7.5 2.4 0 3-2.1 18.2-13 18.2-26.1C47.4 14.2 38.7 5.5 28 5.5Z" fill="none" stroke="url(#fieldpulse-login-mark)" stroke-width="4.2"/>
                                <path d="M17 35.4c5.1-1.8 9.1-4.5 12.1-8.1 3.6-4.2 6-8.7 10.4-11.8" fill="none" stroke="url(#fieldpulse-login-mark)" stroke-width="3.4" stroke-linecap="round"/>
                                <rect x="20.5" y="25.8" width="4.2" height="8.7" rx="2.1" fill="#27D7FF"/>
                                <rect x="27.1" y="21.3" width="4.2" height="10.2" rx="2.1" fill="#27D7FF"/>
                                <rect x="33.7" y="16.8" width="4.2" height="11.7" rx="2.1" fill="#27D7FF"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-2xl font-extrabold tracking-tight">
                                <span class="text-blue-400">Field</span><span class="text-cyan-300">Pulse</span>
                            </div>
                            <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">
                                by <span class="text-blue-400">BusinessOS</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-16 max-w-md">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-cyan-300/80">Field operations platform</p>
                        <h2 class="mt-4 text-4xl font-bold leading-tight text-white">
                            See your field team clearly. Act on the work that matters.
                        </h2>
                        <p class="mt-5 text-base leading-7 text-slate-400">
                            Live operations, visits, orders, collections, expenses, approvals and reporting in one focused workspace.
                        </p>
                    </div>
                </div>

                <div class="relative grid grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <div class="text-sm font-semibold text-cyan-200">Live</div>
                        <div class="mt-1 text-xs text-slate-500">Team visibility</div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <div class="text-sm font-semibold text-blue-300">Offline</div>
                        <div class="mt-1 text-xs text-slate-500">Field continuity</div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <div class="text-sm font-semibold text-violet-300">Secure</div>
                        <div class="mt-1 text-xs text-slate-500">Tenant controls</div>
                    </div>
                </div>
            </section>

            <section class="p-6 sm:p-10 lg:p-12">
                <div class="mb-9 lg:hidden">
                    <div class="flex items-center gap-3">
                        <div class="grid h-12 w-12 place-items-center rounded-2xl bg-cyan-300/10 ring-1 ring-inset ring-cyan-200/20">
                            <svg viewBox="0 0 56 56" class="h-8 w-8" aria-hidden="true">
                                <path d="M28 5.5c-10.7 0-19.4 8.7-19.4 19.4 0 13.1 15.2 24 18.2 26.1.7.5.5 1.7.5 2.4 0 3-2.1 18.2-13 18.2-26.1C47.4 14.2 38.7 5.5 28 5.5Z" fill="none" stroke="#27D7FF" stroke-width="4.2"/>
                                <rect x="20.5" y="25.8" width="4.2" height="8.7" rx="2.1" fill="#27D7FF"/>
                                <rect x="27.1" y="21.3" width="4.2" height="10.2" rx="2.1" fill="#27D7FF"/>
                                <rect x="33.7" y="16.8" width="4.2" height="11.7" rx="2.1" fill="#27D7FF"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xl font-extrabold tracking-tight">
                                <span class="text-blue-400">Field</span><span class="text-cyan-300">Pulse</span>
                            </div>
                            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                by <span class="text-blue-400">BusinessOS</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <p class="text-sm font-semibold text-cyan-300">Administrator access</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-white">Welcome back</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-400">Sign in to your FieldPulse operations workspace.</p>
                </div>

                @if($errors->any())
                    <div class="mb-5 rounded-2xl border border-red-400/20 bg-red-500/10 p-4 text-sm text-red-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="/login" class="space-y-5">
                    @csrf

                    <label class="block">
                        <span class="text-sm font-medium text-slate-300">Email</span>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3.5 text-white outline-none transition placeholder:text-slate-600 focus:border-cyan-300/40 focus:ring-4 focus:ring-cyan-300/10"
                            placeholder="you@company.com"
                            required
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-300">Company identifier</span>
                        <span class="mt-1 block text-xs text-slate-500">Only needed when the same email belongs to multiple companies.</span>
                        <input
                            name="tenant"
                            value="{{ old('tenant') }}"
                            autocomplete="organization"
                            placeholder="Company slug or UUID"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3.5 text-white outline-none transition placeholder:text-slate-600 focus:border-cyan-300/40 focus:ring-4 focus:ring-cyan-300/10"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-300">Password</span>
                        <input
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3.5 text-white outline-none transition placeholder:text-slate-600 focus:border-cyan-300/40 focus:ring-4 focus:ring-cyan-300/10"
                            required
                        >
                    </label>

                    <button class="group flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-cyan-400 px-4 py-3.5 font-bold text-slate-950 shadow-lg shadow-cyan-950/30 transition hover:-translate-y-0.5 hover:shadow-cyan-900/40 focus:outline-none focus:ring-4 focus:ring-cyan-300/20">
                        <span>Sign in</span>
                        <svg viewBox="0 0 24 24" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M14 7l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </form>

                <p class="mt-8 text-center text-xs text-slate-600">
                    FieldPulse · Secure field operations by BusinessOS
                </p>
            </section>
        </div>
    </div>
</x-layouts.app>
