<x-layouts.app>
@php($currentConversationUuid = $conversation?->uuid)

<style>
    .ai-response-card {
        position: relative;
        overflow: hidden;
        border-color: rgba(129, 140, 248, .18) !important;
        background:
            radial-gradient(36rem 14rem at 0% 0%, rgba(99, 102, 241, .12), transparent 58%),
            linear-gradient(145deg, rgba(15, 23, 42, .96), rgba(2, 6, 23, .86));
        box-shadow: 0 18px 42px rgba(2, 6, 23, .2);
    }

    .ai-response-card::before {
        content: "";
        position: absolute;
        inset-block: 0;
        inset-inline-start: 0;
        width: 3px;
        background: linear-gradient(to bottom, #818cf8, #22d3ee, #34d399);
    }

    .ai-response-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .8rem;
        padding-bottom: .65rem;
        border-bottom: 1px solid rgba(255, 255, 255, .06);
    }

    .ai-answer-body {
        color: #cbd5e1;
        line-height: 1.75;
    }

    .ai-answer-body > * + * {
        margin-top: .7rem;
    }

    .ai-answer-body h1,
    .ai-answer-body h2,
    .ai-answer-body h3,
    .ai-answer-body h4 {
        color: #f8fafc;
        font-weight: 750;
        line-height: 1.35;
        letter-spacing: -.012em;
    }

    .ai-answer-body h1,
    .ai-answer-body h2 {
        margin-top: 1.15rem;
        padding-inline-start: .7rem;
        border-inline-start: 3px solid rgba(129, 140, 248, .8);
    }

    .ai-answer-body h3,
    .ai-answer-body h4 {
        margin-top: 1rem;
        color: #e0e7ff;
    }

    .ai-answer-body strong {
        color: #f8fafc;
        font-weight: 700;
    }

    .ai-answer-body ul,
    .ai-answer-body ol {
        display: grid;
        gap: .45rem;
        padding-inline-start: 1.25rem;
    }

    .ai-answer-body ul {
        list-style: disc;
    }

    .ai-answer-body ol {
        list-style: decimal;
    }

    .ai-answer-body li::marker {
        color: #818cf8;
        font-weight: 700;
    }

    .ai-answer-body blockquote {
        margin-block: .9rem;
        border-inline-start: 3px solid #22d3ee;
        border-radius: 0 .75rem .75rem 0;
        background: rgba(34, 211, 238, .06);
        padding: .8rem 1rem;
        color: #bae6fd;
    }

    .ai-answer-body table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .09);
        border-radius: .9rem;
        font-size: .78rem;
    }

    .ai-answer-body th {
        background: rgba(99, 102, 241, .11);
        color: #e0e7ff;
        font-weight: 700;
    }

    .ai-answer-body th,
    .ai-answer-body td {
        padding: .65rem .75rem;
        border-bottom: 1px solid rgba(255, 255, 255, .06);
        text-align: start;
        vertical-align: top;
    }

    .ai-answer-body tr:last-child td {
        border-bottom: 0;
    }

    .ai-answer-body code {
        border: 1px solid rgba(34, 211, 238, .12);
        border-radius: .4rem;
        background: rgba(15, 23, 42, .9);
        padding: .1rem .35rem;
        color: #a5f3fc;
        font-size: .9em;
    }

    .ai-answer-body pre {
        overflow-x: auto;
        border: 1px solid rgba(255, 255, 255, .09);
        border-radius: .9rem;
        background: rgba(2, 6, 23, .8);
        padding: .9rem 1rem;
    }

    .ai-answer-body pre code {
        border: 0;
        background: transparent;
        padding: 0;
    }

    .ai-answer-body hr {
        margin-block: 1rem;
        border: 0;
        border-top: 1px solid rgba(255, 255, 255, .08);
    }

    html[data-theme="light"] .ai-response-card {
        border-color: rgba(99, 102, 241, .16) !important;
        background:
            radial-gradient(36rem 14rem at 0% 0%, rgba(99, 102, 241, .08), transparent 58%),
            linear-gradient(145deg, rgba(255, 255, 255, .98), rgba(248, 250, 252, .97));
        box-shadow: 0 16px 38px rgba(15, 23, 42, .08);
    }

    html[data-theme="light"] .ai-response-header {
        border-bottom-color: #e2e8f0;
    }

    html[data-theme="light"] .ai-answer-body {
        color: #334155;
    }

    html[data-theme="light"] .ai-answer-body h1,
    html[data-theme="light"] .ai-answer-body h2,
    html[data-theme="light"] .ai-answer-body h3,
    html[data-theme="light"] .ai-answer-body h4,
    html[data-theme="light"] .ai-answer-body strong {
        color: #0f172a;
    }

    html[data-theme="light"] .ai-answer-body th,
    html[data-theme="light"] .ai-answer-body td {
        border-bottom-color: #e2e8f0;
    }

    html[data-theme="light"] .ai-answer-body table {
        border-color: #dbe4ee;
    }
</style>

<div class="mb-4 flex flex-wrap items-start justify-between gap-4">
    <div class="flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-500 text-lg font-black shadow-lg shadow-indigo-950/30">AI</div>
        <div>
            <h1 class="text-2xl font-bold">{{ __('Ask FieldPulse') }}</h1>
            <p class="mt-0.5 text-sm text-slate-400">{{ __('Your permission-aware field sales intelligence assistant.') }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-xs">
        <a href="{{ route('admin.ai-insights.usage') }}" class="rounded-full border border-cyan-400/20 bg-cyan-500/10 px-3 py-1.5 font-semibold text-cyan-200 transition hover:bg-cyan-500/15">
            {{ __('Usage & Audit') }}
        </a>
        <a href="{{ route('admin.ai-insights.briefing') }}" class="rounded-full border border-amber-400/20 bg-amber-500/10 px-3 py-1.5 font-semibold text-amber-200 transition hover:bg-amber-500/15">
            ☀ {{ __('Morning Briefing') }}
        </a>
        <button id="ai-health-button" type="button" class="rounded-full border border-white/10 bg-slate-900 px-3 py-1.5 font-semibold text-slate-400 transition hover:border-emerald-400/20 hover:text-emerald-300">
            {{ __('Check AI connection') }}
        </button>
        <span id="ai-provider-chip" class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 {{ $providerEnabled ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-slate-900 text-slate-400' }}">
            <span id="ai-provider-dot" class="h-2 w-2 rounded-full {{ $providerEnabled ? 'bg-emerald-400' : 'bg-slate-500' }}"></span>
            <span id="ai-provider-label">{{ $providerEnabled ? __('AI provider configured') : __('Grounded local mode') }}</span>
        </span>
        @if($providerEnabled && $providerModel)
            <span class="rounded-full border border-white/10 bg-slate-900 px-3 py-1.5 text-slate-400">{{ str($providerName)->upper() }} · {{ $providerModel }}</span>
        @endif
        <span class="rounded-full border border-white/10 bg-slate-900 px-3 py-1.5 {{ $customerDataEnabled ? 'text-indigo-300' : 'text-slate-500' }}">
            {{ $customerDataEnabled ? __('Customer data tools enabled') : __('Customer data tools disabled') }}
        </span>
    </div>
</div>

<div class="mb-3 flex gap-2 xl:hidden">
    <button type="button" data-panel-toggle="history" class="flex-1 rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm font-semibold text-slate-300">
        ☰ {{ __('History') }}
    </button>
    <button type="button" data-panel-toggle="insights" class="flex-1 rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm font-semibold text-slate-300">
        ✦ {{ __('Insights') }}
    </button>
</div>

<div id="mobile-panel-backdrop" class="fixed inset-0 z-40 hidden bg-slate-950/80 backdrop-blur-sm xl:hidden"></div>

<div class="grid min-h-[72vh] gap-4 xl:grid-cols-[280px_minmax(0,1fr)_300px]">
    <aside id="history-panel" class="order-2 hidden overflow-hidden rounded-2xl border border-white/10 bg-slate-900/95 shadow-2xl xl:order-1 xl:block xl:bg-slate-900/80 xl:shadow-none">
        <div class="border-b border-white/10 p-3">
            <div class="mb-3 flex items-center justify-between xl:hidden">
                <p class="font-semibold">{{ __('Conversation history') }}</p>
                <button type="button" data-panel-close class="rounded-lg p-2 text-slate-400 hover:bg-white/5" title="{{ __('Close panel') }}">×</button>
            </div>
            <a href="{{ route('admin.ai-insights.index') }}" class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-500 px-4 py-3 text-sm font-semibold shadow-lg shadow-indigo-950/20 transition hover:bg-indigo-400">
                <span class="text-lg leading-none">＋</span>{{ __('New chat') }}
            </a>
            <label class="mt-3 block">
                <span class="sr-only">{{ __('Search conversations') }}</span>
                <input id="conversation-search" type="search" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-slate-200 outline-none placeholder:text-slate-600 focus:border-indigo-400/40 focus:ring-2 focus:ring-indigo-500/10" placeholder="{{ __('Search your chat history…') }}">
            </label>
        </div>

        <div class="max-h-[65vh] overflow-y-auto p-2">
            <p class="px-2 pb-2 pt-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ __('Conversation history') }}</p>
            <div id="conversation-list" class="space-y-1">
                @forelse($conversations as $thread)
                    <div class="group relative rounded-xl {{ $conversation?->id === $thread->id ? 'bg-indigo-500/15 ring-1 ring-indigo-400/20' : 'hover:bg-white/5' }}" data-conversation-item="{{ $thread->uuid }}" data-conversation-title="{{ str($thread->title)->lower() }}">
                        <a href="{{ route('admin.ai-insights.index', ['conversation' => $thread->uuid]) }}" class="block px-3 py-3 pr-10">
                            <p class="truncate text-sm font-medium {{ $conversation?->id === $thread->id ? 'text-indigo-100' : 'text-slate-200' }}">{{ $thread->title }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">{{ $thread->messages_count }} {{ __('messages') }}@if($thread->last_message_at) · {{ $thread->last_message_at->diffForHumans() }}@endif</p>
                        </a>
                        <form method="POST" action="{{ route('admin.ai-insights.conversations.archive', $thread->uuid) }}" class="absolute right-2 top-3 opacity-100 transition md:opacity-0 md:group-hover:opacity-100">
                            @csrf
                            @method('DELETE')
                            <button title="{{ __('Archive conversation') }}" class="rounded-lg p-1.5 text-slate-500 hover:bg-white/10 hover:text-rose-300">×</button>
                        </form>
                    </div>
                @empty
                    <div id="conversation-empty" class="px-3 py-8 text-center text-sm text-slate-500">{{ __('Your chats will appear here.') }}</div>
                @endforelse
                <div id="conversation-no-match" class="hidden px-3 py-8 text-center text-sm text-slate-500">{{ __('No conversations match your search.') }}</div>
            </div>

            @if($archivedConversations->isNotEmpty())
                <details class="mt-4 border-t border-white/10 pt-3">
                    <summary class="cursor-pointer px-2 text-xs font-semibold text-slate-500">{{ __('Archived chats') }} · {{ $archivedConversations->count() }}</summary>
                    <div class="mt-2 space-y-1">
                        @foreach($archivedConversations as $archived)
                            <div class="flex items-center gap-2 rounded-xl px-2 py-2 hover:bg-white/5">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-medium text-slate-400">{{ $archived->title }}</p>
                                    <p class="mt-0.5 text-[10px] text-slate-600">{{ $archived->messages_count }} {{ __('messages') }}</p>
                                </div>
                                <form method="POST" action="{{ route('admin.ai-insights.conversations.restore', $archived->uuid) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-lg border border-white/10 px-2 py-1 text-[10px] font-semibold text-slate-400 hover:border-indigo-400/30 hover:text-indigo-300">{{ __('Restore') }}</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </aside>

    <section class="order-1 flex min-h-[70vh] min-w-0 flex-col overflow-hidden rounded-2xl border border-white/10 bg-slate-900 xl:order-2 xl:min-h-[72vh]">
        <header class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-4 sm:px-5">
            <div class="min-w-0">
                <h2 id="conversation-title" class="truncate font-semibold">{{ $conversation?->title ?? __('New conversation') }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">{{ __('Ask follow-up questions naturally — this thread remembers its context.') }}</p>
            </div>
            <span class="shrink-0 rounded-full bg-white/5 px-3 py-1 text-[11px] text-slate-500">{{ __('Read-only assistant') }}</span>
        </header>

        <div id="chat-scroll" class="flex-1 overflow-y-auto scroll-smooth px-3 py-5 sm:px-6 sm:py-6">
            <div id="chat-messages" class="mx-auto max-w-4xl space-y-5">
                @if($messages->isEmpty())
                    <div id="chat-empty-state" class="flex min-h-[42vh] flex-col items-center justify-center text-center">
                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-indigo-500/20 to-violet-500/20 text-2xl ring-1 ring-indigo-400/20">✦</div>
                        <h3 class="text-xl font-bold">{{ __('What would you like to know?') }}</h3>
                        <p class="mt-2 max-w-xl text-sm leading-6 text-slate-400">{{ __('Ask about sales, customers, collections, products, attendance, expenses, stock, returns, visits, follow-ups, or team performance.') }}</p>
                        <div class="mt-6 grid w-full max-w-2xl gap-2 sm:grid-cols-2">
                            @foreach($suggestedPrompts as $prompt)
                                <button type="button" data-suggested-prompt="{{ $prompt }}" class="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-left text-sm text-slate-300 transition hover:border-indigo-400/30 hover:bg-indigo-500/10 hover:text-white">{{ $prompt }}</button>
                            @endforeach
                        </div>
                    </div>
                @else
                    @php($lastUserQuestion = null)
                    @foreach($messages as $message)
                        @if($message->role === 'user')
                            <article class="flex justify-end" data-message-id="{{ $message->uuid }}" data-user-message>
                                <div class="max-w-[92%] rounded-2xl rounded-br-md bg-indigo-500 px-4 py-3 text-sm leading-6 text-white shadow-lg shadow-indigo-950/20 sm:max-w-[78%]">
                                    <p class="whitespace-pre-wrap">{{ $message->content }}</p>
                                    <p class="mt-2 text-right text-[10px] text-indigo-200/70">{{ $message->created_at?->format('H:i') }}</p>
                                </div>
                            </article>
                            @php($lastUserQuestion = $message->content)
                        @else
                            @php($providerStatus = data_get($message->meta, 'provider_status'))
                            @php($tools = data_get($message->meta, 'tools_used', []))
                            <article class="flex items-start gap-3" data-message-id="{{ $message->uuid }}">
                                <div class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-[10px] font-black shadow-lg shadow-indigo-950/20">AI</div>
                                <div class="min-w-0 max-w-[94%]">
                                    <div class="ai-response-card rounded-2xl rounded-tl-md border px-4 py-4 text-sm text-slate-200 sm:px-5">
                                        <div class="ai-response-header">
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-500/15 text-xs text-indigo-200 ring-1 ring-indigo-400/20">✦</span>
                                                <div>
                                                    <p class="text-xs font-semibold text-slate-200">{{ __('FieldPulse AI') }}</p>
                                                    <p class="text-[10px] text-slate-500">{{ __('Grounded business response') }}</p>
                                                </div>
                                            </div>
                                            <span class="text-[10px] text-slate-600">{{ $message->created_at?->format('H:i') }}</span>
                                        </div>
                                        <div class="ai-answer-body break-words">
                                            {!! \Illuminate\Support\Str::markdown($message->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-3 border-t border-white/5 pt-3">
                                            <button type="button" data-copy-answer class="text-[11px] font-medium text-slate-500 hover:text-slate-300">{{ __('Copy answer') }}</button>
                                            @if($lastUserQuestion)
                                                <button type="button" data-ask-again="{{ $lastUserQuestion }}" class="text-[11px] font-medium text-slate-500 hover:text-indigo-300">{{ __('Ask again') }}</button>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-[10px] text-slate-500">
                                        <span class="rounded-full {{ $providerStatus === 'fallback' ? 'bg-amber-500/10 text-amber-300' : 'bg-white/5' }} px-2 py-1">
                                            {{ $providerStatus === 'connected' ? __('AI provider') : ($providerStatus === 'fallback' ? __('Local fallback') : __('Local analysis')) }}
                                        </span>
                                        @if($message->provider)<span>{{ str($message->provider)->upper() }}</span>@endif
                                        @if($message->model)<span>· {{ $message->model }}</span>@endif
                                        @if($message->latency_ms)<span>· {{ $message->latency_ms }}ms</span>@endif
                                    </div>

                                    @if(is_array($tools) && count($tools))
                                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                            <span class="mr-1 text-[10px] font-semibold uppercase tracking-wide text-slate-600">{{ __('Tool activity') }}</span>
                                            @foreach($tools as $tool)
                                                <span class="rounded-full border border-cyan-400/10 bg-cyan-500/5 px-2 py-1 text-[10px] text-cyan-300/80">{{ str($tool)->replace('_', ' ')->title() }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endif
                    @endforeach
                @endif
                <div id="ask-fieldpulse-bottom"></div>
            </div>
        </div>

        <div id="provider-warning" class="mx-3 hidden rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-200 sm:mx-6"></div>

        <div class="border-t border-white/10 bg-slate-900/95 p-3 sm:p-4">
            <form id="ask-fieldpulse-form" method="POST" action="{{ route('admin.ai-insights.ask') }}" class="mx-auto max-w-4xl">
                @csrf
                <input id="conversation-uuid" type="hidden" name="conversation_uuid" value="{{ $currentConversationUuid }}">
                <div class="rounded-2xl border border-white/10 bg-slate-950 p-2 shadow-xl shadow-black/10 transition focus-within:border-indigo-400/40 focus-within:ring-2 focus-within:ring-indigo-500/10">
                    <textarea id="ask-fieldpulse-input" name="question" rows="1" maxlength="1000" required class="max-h-40 min-h-[52px] w-full resize-none border-0 bg-transparent px-3 py-3 text-sm leading-6 text-slate-100 outline-none placeholder:text-slate-600 focus:ring-0" placeholder="{{ __('Message Ask FieldPulse…') }}"></textarea>
                    <div class="flex items-center justify-between gap-3 px-2 pb-1">
                        <div class="hidden text-[11px] text-slate-600 sm:block">{{ __('Enter to send · Shift+Enter for new line') }}</div>
                        <div class="ml-auto flex items-center gap-3">
                            <span id="character-count" class="text-[10px] tabular-nums text-slate-600">0/1000</span>
                            <button id="ask-fieldpulse-button" class="inline-flex items-center gap-2 rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold transition hover:bg-indigo-400 disabled:cursor-not-allowed disabled:opacity-50">
                                <span>{{ __('Send') }}</span><span aria-hidden="true">↑</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 px-2">
                    <p id="ask-fieldpulse-status" class="hidden text-xs text-slate-500" aria-live="polite"></p>
                    <p class="text-[10px] text-slate-600">{{ __('AI can make mistakes. Verify critical business decisions against source records.') }}</p>
                </div>
            </form>
        </div>
    </section>

    <aside id="insights-panel" class="order-3 hidden space-y-4 rounded-2xl bg-slate-950/20 shadow-2xl xl:block xl:bg-transparent xl:shadow-none">
        <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-slate-900 p-3 xl:hidden">
            <p class="font-semibold">{{ __('Insights') }}</p>
            <button type="button" data-panel-close class="rounded-lg p-2 text-slate-400 hover:bg-white/5" title="{{ __('Close panel') }}">×</button>
        </div>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold">{{ __('Today at a glance') }}</h2>
                <span class="text-[10px] uppercase tracking-wider text-slate-600">{{ $snapshot['date'] }}</span>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Visits') }}</p><p class="mt-1 text-xl font-bold">{{ $snapshot['visits_today'] }}</p></div>
                <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Overdue') }}</p><p class="mt-1 text-xl font-bold {{ $snapshot['overdue_followups'] > 0 ? 'text-rose-300' : '' }}">{{ $snapshot['overdue_followups'] }}</p></div>
                <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Stale customers') }}</p><p class="mt-1 text-xl font-bold {{ $snapshot['customers_not_visited_30_days'] > 0 ? 'text-amber-300' : '' }}">{{ $snapshot['customers_not_visited_30_days'] }}</p></div>
                <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Started') }}</p><p class="mt-1 text-xl font-bold">{{ $snapshot['salesmen_started_today'] }}/{{ $snapshot['active_salesmen'] }}</p></div>
            </div>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-4">
            <h2 class="text-sm font-semibold">{{ __('Recommended actions') }}</h2>
            <div class="mt-3 space-y-2">
                @foreach(array_slice($snapshot['recommendations'], 0, 4) as $recommendation)
                    @php($dot = match($recommendation['severity']) { 'critical' => 'bg-rose-500', 'high' => 'bg-orange-400', 'medium' => 'bg-amber-400', default => 'bg-emerald-400' })
                    <div class="rounded-xl bg-slate-950/70 p-3">
                        <div class="flex gap-2">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>
                            <div>
                                <p class="text-xs font-semibold text-slate-200">{{ __($recommendation['title']) }}</p>
                                <p class="mt-1 text-[11px] leading-5 text-slate-500">{{ __($recommendation['message'], $recommendation['message_params'] ?? []) }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-4 text-xs text-slate-500">
            <p class="font-semibold text-slate-300">{{ __('Privacy & access') }}</p>
            <p class="mt-2 leading-5">{{ __('Ask FieldPulse is read-only. Tool access follows the signed-in user’s permissions and tenant scope.') }}</p>
            <p class="mt-2 leading-5">{{ $customerDataEnabled ? __('Customer-level AI tools are enabled for authorized users.') : __('Customer-level AI tools are currently disabled.') }}</p>
            <p class="mt-2 leading-5">
                {{ $historyRetentionDays === 0
                    ? __('Chat history is retained indefinitely for this organization.')
                    : __('Chat history is retained for :days days for this organization.', ['days' => $historyRetentionDays]) }}
            </p>
        </section>
    </aside>
</div>

<script>
(() => {
    const form = document.getElementById('ask-fieldpulse-form');
    const input = document.getElementById('ask-fieldpulse-input');
    const button = document.getElementById('ask-fieldpulse-button');
    const status = document.getElementById('ask-fieldpulse-status');
    const messages = document.getElementById('chat-messages');
    const scroll = document.getElementById('chat-scroll');
    const empty = document.getElementById('chat-empty-state');
    const conversationUuid = document.getElementById('conversation-uuid');
    const conversationTitle = document.getElementById('conversation-title');
    const providerWarning = document.getElementById('provider-warning');
    const providerChip = document.getElementById('ai-provider-chip');
    const providerDot = document.getElementById('ai-provider-dot');
    const providerLabel = document.getElementById('ai-provider-label');
    const healthButton = document.getElementById('ai-health-button');
    const search = document.getElementById('conversation-search');
    const noMatch = document.getElementById('conversation-no-match');
    const count = document.getElementById('character-count');
    const backdrop = document.getElementById('mobile-panel-backdrop');

    if (!form || !input || !button || !messages) return;

    const healthUrl = @json(route('admin.ai-insights.provider-health'));
    const conversationBaseUrl = @json(route('admin.ai-insights.index'));

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    };

    const inlineMarkdown = (value) => value
        .replace(/\*\*(.+?)\*\*/g, '<strong class="font-semibold text-slate-100">$1</strong>')
        .replace(/`([^`]+)`/g, '<code class="rounded bg-white/10 px-1.5 py-0.5 text-[0.9em] text-cyan-200">$1</code>');

    const renderSafeMarkdown = (text) => {
        const lines = escapeHtml(text || '').replace(/\r/g, '').split('\n');
        const html = [];
        let inCode = false;
        let code = [];

        const flushCode = () => {
            if (!code.length) return;
            html.push('<pre class="my-3 overflow-x-auto rounded-xl border border-white/10 bg-black/30 p-3 text-xs leading-5 text-slate-300"><code>' + code.join('\n') + '</code></pre>');
            code = [];
        };

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];

            if (line.trim().startsWith('```')) {
                if (inCode) flushCode();
                inCode = !inCode;
                continue;
            }

            if (inCode) {
                code.push(line);
                continue;
            }

            const next = lines[i + 1] || '';
            const isTableSeparator = /^\s*\|?\s*:?-{3,}/.test(next) && next.includes('|');

            if (line.includes('|') && isTableSeparator) {
                const headers = line.replace(/^\||\|$/g, '').split('|').map(cell => cell.trim());
                const rows = [];
                i += 2;
                while (i < lines.length && lines[i].includes('|') && lines[i].trim() !== '') {
                    rows.push(lines[i].replace(/^\||\|$/g, '').split('|').map(cell => cell.trim()));
                    i++;
                }
                i--;
                html.push('<div class="my-4 overflow-x-auto rounded-xl"><table><thead><tr>' +
                    headers.map(cell => '<th>' + inlineMarkdown(cell) + '</th>').join('') +
                    '</tr></thead><tbody>' +
                    rows.map(row => '<tr>' + row.map(cell => '<td>' + inlineMarkdown(cell) + '</td>').join('') + '</tr>').join('') +
                    '</tbody></table></div>');
                continue;
            }

            if (/^###\s+/.test(line)) {
                html.push('<h4>' + inlineMarkdown(line.replace(/^###\s+/, '')) + '</h4>');
            } else if (/^##\s+/.test(line)) {
                html.push('<h3>' + inlineMarkdown(line.replace(/^##\s+/, '')) + '</h3>');
            } else if (/^#\s+/.test(line)) {
                html.push('<h2>' + inlineMarkdown(line.replace(/^#\s+/, '')) + '</h2>');
            } else if (/^>\s?/.test(line)) {
                html.push('<blockquote>' + inlineMarkdown(line.replace(/^>\s?/, '')) + '</blockquote>');
            } else if (/^\s*---+\s*$/.test(line)) {
                html.push('<hr>');
            } else if (/^[-*]\s+/.test(line)) {
                html.push('<div class="flex gap-3 rounded-xl border border-white/5 bg-white/[.025] px-3 py-2"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400"></span><p>' + inlineMarkdown(line.replace(/^[-*]\s+/, '')) + '</p></div>');
            } else if (/^\d+\.\s+/.test(line)) {
                const match = line.match(/^(\d+)\.\s+(.*)$/);
                html.push('<div class="flex gap-3 rounded-xl border border-white/5 bg-white/[.025] px-3 py-2"><span class="flex h-6 min-w-6 items-center justify-center rounded-lg bg-indigo-500/15 text-[11px] font-bold text-indigo-200">' + match[1] + '</span><p>' + inlineMarkdown(match[2]) + '</p></div>');
            } else if (line.trim() === '') {
                html.push('<div class="h-1"></div>');
            } else {
                html.push('<p>' + inlineMarkdown(line) + '</p>');
            }
        }

        if (inCode) flushCode();
        return html.join('');
    };

    const bottom = () => document.getElementById('ask-fieldpulse-bottom');
    const scrollBottom = (smooth = true) => requestAnimationFrame(() => scroll?.scrollTo({top: scroll.scrollHeight, behavior: smooth ? 'smooth' : 'auto'}));
    const resizeInput = () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        if (count) count.textContent = input.value.length + '/1000';
    };

    const addUserMessage = (text) => {
        empty?.remove();
        const wrapper = document.createElement('article');
        wrapper.className = 'flex justify-end';
        wrapper.dataset.userMessage = 'true';
        const bubble = document.createElement('div');
        bubble.className = 'max-w-[92%] rounded-2xl rounded-br-md bg-indigo-500 px-4 py-3 text-sm leading-6 text-white shadow-lg shadow-indigo-950/20 sm:max-w-[78%]';
        const content = document.createElement('p');
        content.className = 'whitespace-pre-wrap';
        content.textContent = text;
        bubble.appendChild(content);
        wrapper.appendChild(bubble);
        messages.insertBefore(wrapper, bottom());
    };

    const addThinking = () => {
        const wrapper = document.createElement('article');
        wrapper.className = 'flex items-start gap-3';
        wrapper.innerHTML = '<div class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-[10px] font-black">AI</div>' +
            '<div class="rounded-2xl rounded-tl-md border border-white/10 bg-slate-950/70 px-4 py-4"><div class="flex items-center gap-2 text-xs text-slate-500">' +
            '<div class="flex gap-1.5"><span class="h-2 w-2 animate-pulse rounded-full bg-indigo-400"></span><span class="h-2 w-2 animate-pulse rounded-full bg-indigo-400"></span><span class="h-2 w-2 animate-pulse rounded-full bg-indigo-400"></span></div>' +
            '<span>' + escapeHtml(@json(__('Thinking from current FieldPulse data…'))) + '</span></div></div>';
        messages.insertBefore(wrapper, bottom());
        return wrapper;
    };

    const addAssistant = (payload, wrapper, question) => {
        wrapper.innerHTML = '';
        const avatar = document.createElement('div');
        avatar.className = 'mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-[10px] font-black shadow-lg shadow-indigo-950/20';
        avatar.textContent = 'AI';

        const body = document.createElement('div');
        body.className = 'min-w-0 max-w-[94%]';
        const bubble = document.createElement('div');
        bubble.className = 'ai-response-card rounded-2xl rounded-tl-md border px-4 py-4 text-sm text-slate-200 sm:px-5';

        const responseHeader = document.createElement('div');
        responseHeader.className = 'ai-response-header';
        responseHeader.innerHTML = '<div class="flex items-center gap-2"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-500/15 text-xs text-indigo-200 ring-1 ring-indigo-400/20">✦</span><div><p class="text-xs font-semibold text-slate-200">' +
            escapeHtml(@json(__('FieldPulse AI'))) +
            '</p><p class="text-[10px] text-slate-500">' +
            escapeHtml(@json(__('Grounded business response'))) +
            '</p></div></div><span class="rounded-full bg-emerald-500/10 px-2 py-1 text-[10px] font-medium text-emerald-300">' +
            escapeHtml(@json(__('Live insight'))) +
            '</span>';

        const answer = document.createElement('div');
        answer.className = 'ai-answer-body break-words';
        answer.innerHTML = renderSafeMarkdown(payload.answer || '');

        const actions = document.createElement('div');
        actions.className = 'mt-3 flex flex-wrap gap-3 border-t border-white/5 pt-2';

        const copy = document.createElement('button');
        copy.type = 'button';
        copy.className = 'text-[11px] font-medium text-slate-500 hover:text-slate-300';
        copy.textContent = @json(__('Copy answer'));
        copy.addEventListener('click', async () => {
            await navigator.clipboard?.writeText(payload.answer || '');
            copy.textContent = @json(__('Copied'));
            setTimeout(() => copy.textContent = @json(__('Copy answer')), 1200);
        });

        const again = document.createElement('button');
        again.type = 'button';
        again.className = 'text-[11px] font-medium text-slate-500 hover:text-indigo-300';
        again.textContent = @json(__('Ask again'));
        again.addEventListener('click', () => {
            input.value = question;
            resizeInput();
            input.focus();
        });

        actions.append(copy, again);
        bubble.append(responseHeader, answer, actions);

        const meta = document.createElement('div');
        meta.className = 'mt-2 flex flex-wrap items-center gap-2 text-[10px] text-slate-500';
        const source = document.createElement('span');
        source.className = 'rounded-full px-2 py-1 ' + (payload.provider_status === 'fallback' ? 'bg-amber-500/10 text-amber-300' : 'bg-white/5');
        source.textContent = payload.provider_status === 'connected'
            ? @json(__('AI provider'))
            : payload.provider_status === 'fallback'
                ? @json(__('Local fallback'))
                : @json(__('Local analysis'));
        meta.appendChild(source);

        if (payload.provider) {
            const provider = document.createElement('span');
            provider.textContent = String(payload.provider).toUpperCase();
            meta.appendChild(provider);
        }
        if (payload.model) {
            const model = document.createElement('span');
            model.textContent = '· ' + payload.model;
            meta.appendChild(model);
        }
        if (payload.latency_ms) {
            const latency = document.createElement('span');
            latency.textContent = '· ' + payload.latency_ms + 'ms';
            meta.appendChild(latency);
        }

        body.append(bubble, meta);

        if (Array.isArray(payload.tools_used) && payload.tools_used.length) {
            const toolRow = document.createElement('div');
            toolRow.className = 'mt-2 flex flex-wrap items-center gap-1.5';
            const label = document.createElement('span');
            label.className = 'mr-1 text-[10px] font-semibold uppercase tracking-wide text-slate-600';
            label.textContent = @json(__('Tool activity'));
            toolRow.appendChild(label);

            payload.tools_used.forEach((tool) => {
                const chip = document.createElement('span');
                chip.className = 'rounded-full border border-cyan-400/10 bg-cyan-500/5 px-2 py-1 text-[10px] text-cyan-300/80';
                chip.textContent = String(tool).replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
                toolRow.appendChild(chip);
            });

            body.appendChild(toolRow);
        }

        wrapper.append(avatar, body);
    };

    const addError = (wrapper, error, question) => {
        wrapper.innerHTML = '';
        wrapper.className = 'flex items-start gap-3';
        const avatar = document.createElement('div');
        avatar.className = 'mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-500/20 text-[11px] font-black text-rose-300';
        avatar.textContent = '!';
        const bubble = document.createElement('div');
        bubble.className = 'rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-200';
        const message = document.createElement('p');
        message.textContent = error;
        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'mt-2 text-xs font-semibold text-rose-100 underline decoration-rose-300/40 underline-offset-4';
        retry.textContent = @json(__('Ask again'));
        retry.addEventListener('click', () => {
            input.value = question;
            resizeInput();
            input.focus();
        });
        bubble.append(message, retry);
        wrapper.append(avatar, bubble);
    };

    const updateProvider = (payload) => {
        providerWarning?.classList.add('hidden');

        if (payload.provider_status === 'connected') {
            providerChip.className = 'inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1.5 text-emerald-300';
            providerDot.className = 'h-2 w-2 rounded-full bg-emerald-400';
            providerLabel.textContent = @json(__('AI provider connected'));
        } else if (payload.provider_status === 'fallback') {
            providerChip.className = 'inline-flex items-center gap-2 rounded-full border border-amber-400/20 bg-amber-500/10 px-3 py-1.5 text-amber-300';
            providerDot.className = 'h-2 w-2 rounded-full bg-amber-400';
            providerLabel.textContent = @json(__('Local fallback active'));
            providerWarning.textContent = payload.fallback_message || @json(__('The external AI provider was unavailable, so FieldPulse answered locally.'));
            providerWarning.classList.remove('hidden');
        }
    };

    const addConversationToSidebar = (conversation) => {
        if (!conversation?.uuid || document.querySelector('[data-conversation-item="' + conversation.uuid + '"]')) return;
        document.getElementById('conversation-empty')?.remove();

        const item = document.createElement('div');
        item.className = 'group relative rounded-xl bg-indigo-500/15 ring-1 ring-indigo-400/20';
        item.dataset.conversationItem = conversation.uuid;
        item.dataset.conversationTitle = String(conversation.title || '').toLowerCase();

        const link = document.createElement('a');
        const url = new URL(conversationBaseUrl, window.location.origin);
        url.searchParams.set('conversation', conversation.uuid);
        link.href = url.toString();
        link.className = 'block px-3 py-3 pr-10';

        const title = document.createElement('p');
        title.className = 'truncate text-sm font-medium text-indigo-100';
        title.textContent = conversation.title || @json(__('New conversation'));
        const meta = document.createElement('p');
        meta.className = 'mt-1 text-[11px] text-slate-500';
        meta.textContent = '2 ' + @json(__('messages')) + ' · now';

        link.append(title, meta);
        item.appendChild(link);
        document.getElementById('conversation-list')?.prepend(item);
    };

    const sendQuestion = async (question) => {
        if (!question || button.disabled) return;

        addUserMessage(question);
        const thinking = addThinking();
        input.value = '';
        resizeInput();
        button.disabled = true;
        status.textContent = @json(__('Thinking from current FieldPulse data…'));
        status.classList.remove('hidden');
        scrollBottom();

        try {
            const data = new FormData(form);
            data.set('question', question);
            const response = await fetch(form.action, {
                method: 'POST',
                body: data,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin',
            });
            const payload = await response.json();

            if (!response.ok) {
                const firstError = payload?.errors ? Object.values(payload.errors).flat()[0] : payload?.message;
                throw new Error(firstError || @json(__('Ask FieldPulse could not answer right now.')));
            }

            addAssistant(payload, thinking, question);
            updateProvider(payload);

            if (payload.conversation?.uuid) {
                conversationUuid.value = payload.conversation.uuid;
                conversationTitle.textContent = payload.conversation.title;
                addConversationToSidebar(payload.conversation);
                const url = new URL(window.location.href);
                url.searchParams.set('conversation', payload.conversation.uuid);
                history.replaceState({}, '', url);
            }

            status.classList.add('hidden');
            scrollBottom();
        } catch (error) {
            const message = error?.message || @json(__('Ask FieldPulse could not answer right now.'));
            addError(thinking, message, question);
            status.textContent = message;
        } finally {
            button.disabled = false;
            input.focus();
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        sendQuestion(input.value.trim());
    });

    input.addEventListener('input', resizeInput);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    document.querySelectorAll('[data-suggested-prompt]').forEach((suggestion) => {
        suggestion.addEventListener('click', () => {
            input.value = suggestion.dataset.suggestedPrompt || '';
            resizeInput();
            input.focus();
        });
    });

    document.querySelectorAll('[data-copy-answer]').forEach((copy) => {
        copy.addEventListener('click', async () => {
            const text = copy.closest('article')?.querySelector('.ai-answer-body')?.textContent?.trim() || '';
            await navigator.clipboard?.writeText(text);
            copy.textContent = @json(__('Copied'));
            setTimeout(() => copy.textContent = @json(__('Copy answer')), 1200);
        });
    });

    document.querySelectorAll('[data-ask-again]').forEach((retry) => {
        retry.addEventListener('click', () => {
            input.value = retry.dataset.askAgain || '';
            resizeInput();
            input.focus();
        });
    });

    search?.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        let visible = 0;

        document.querySelectorAll('[data-conversation-item]').forEach((item) => {
            const matches = !term || (item.dataset.conversationTitle || '').includes(term);
            item.classList.toggle('hidden', !matches);
            if (matches) visible++;
        });

        noMatch?.classList.toggle('hidden', visible !== 0 || term === '');
    });

    healthButton?.addEventListener('click', async () => {
        healthButton.disabled = true;
        healthButton.textContent = @json(__('Checking AI connection…'));

        try {
            const response = await fetch(healthUrl, {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin',
            });
            const payload = await response.json();

            if (payload.ok) {
                providerChip.className = 'inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1.5 text-emerald-300';
                providerDot.className = 'h-2 w-2 rounded-full bg-emerald-400';
                providerLabel.textContent = @json(__('AI connection healthy')) + (payload.latency_ms ? ' · ' + payload.latency_ms + 'ms' : '');
            } else {
                providerChip.className = 'inline-flex items-center gap-2 rounded-full border border-rose-400/20 bg-rose-500/10 px-3 py-1.5 text-rose-300';
                providerDot.className = 'h-2 w-2 rounded-full bg-rose-400';
                providerLabel.textContent = @json(__('AI connection failed'));
                providerWarning.textContent = payload.message || @json(__('AI connection failed'));
                providerWarning.classList.remove('hidden');
            }
        } catch (error) {
            providerLabel.textContent = @json(__('AI connection failed'));
        } finally {
            healthButton.disabled = false;
            healthButton.textContent = @json(__('Check AI connection'));
        }
    });

    const closePanels = () => {
        ['history-panel', 'insights-panel'].forEach((id) => {
            const panel = document.getElementById(id);
            if (!panel || window.innerWidth >= 1280) return;
            panel.classList.add('hidden');
            panel.classList.remove('fixed', 'inset-y-3', 'left-3', 'right-3', 'z-50', 'block', 'overflow-y-auto', 'max-w-sm', 'ml-auto');
        });
        backdrop?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };

    const openPanel = (name) => {
        closePanels();
        const panel = document.getElementById(name + '-panel');
        if (!panel || window.innerWidth >= 1280) return;

        panel.classList.remove('hidden');
        panel.classList.add('fixed', 'inset-y-3', 'left-3', 'right-3', 'z-50', 'block', 'overflow-y-auto');
        if (name === 'insights') panel.classList.add('max-w-sm', 'ml-auto');
        backdrop?.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    document.querySelectorAll('[data-panel-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => openPanel(toggle.dataset.panelToggle));
    });
    document.querySelectorAll('[data-panel-close]').forEach((close) => close.addEventListener('click', closePanels));
    backdrop?.addEventListener('click', closePanels);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closePanels();
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1280) {
            backdrop?.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('history-panel')?.classList.remove('fixed', 'inset-y-3', 'left-3', 'right-3', 'z-50', 'overflow-y-auto', 'max-w-sm', 'ml-auto');
            document.getElementById('insights-panel')?.classList.remove('fixed', 'inset-y-3', 'left-3', 'right-3', 'z-50', 'overflow-y-auto', 'max-w-sm', 'ml-auto');
        }
    });

    resizeInput();
    scrollBottom(false);
})();
</script>
</x-layouts.app>
