<x-layouts.app>
@php($currentConversationUuid = $conversation?->uuid)

<div class="mb-5 flex flex-wrap items-start justify-between gap-4">
    <div class="flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-500 text-lg font-black shadow-lg shadow-indigo-950/30">AI</div>
        <div>
            <h1 class="text-2xl font-bold">{{ __('Ask FieldPulse') }}</h1>
            <p class="mt-0.5 text-sm text-slate-400">{{ __('Your permission-aware field sales intelligence assistant.') }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span id="ai-provider-chip" class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 {{ $providerEnabled ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-slate-900 text-slate-400' }}">
            <span class="h-2 w-2 rounded-full {{ $providerEnabled ? 'bg-emerald-400' : 'bg-slate-500' }}"></span>
            <span>{{ $providerEnabled ? __('AI provider configured') : __('Grounded local mode') }}</span>
        </span>
        @if($providerEnabled && $providerModel)
            <span class="rounded-full border border-white/10 bg-slate-900 px-3 py-1.5 text-slate-400">{{ str($providerName)->upper() }} · {{ $providerModel }}</span>
        @endif
        <span class="rounded-full border border-white/10 bg-slate-900 px-3 py-1.5 {{ $customerDataEnabled ? 'text-indigo-300' : 'text-slate-500' }}">
            {{ $customerDataEnabled ? __('Customer data tools enabled') : __('Customer data tools disabled') }}
        </span>
    </div>
</div>

<div class="grid min-h-[72vh] gap-4 xl:grid-cols-[260px_minmax(0,1fr)_300px]">
    <aside class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900/80">
        <div class="border-b border-white/10 p-3">
            <a href="{{ route('admin.ai-insights.index') }}" class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-500 px-4 py-3 text-sm font-semibold shadow-lg shadow-indigo-950/20 transition hover:bg-indigo-400">
                <span class="text-lg leading-none">＋</span>{{ __('New chat') }}
            </a>
        </div>
        <div class="max-h-[64vh] overflow-y-auto p-2">
            <p class="px-2 pb-2 pt-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ __('Conversation history') }}</p>
            <div id="conversation-list" class="space-y-1">
                @forelse($conversations as $thread)
                    <div class="group relative rounded-xl {{ $conversation?->id === $thread->id ? 'bg-indigo-500/15 ring-1 ring-indigo-400/20' : 'hover:bg-white/5' }}" data-conversation-item="{{ $thread->uuid }}">
                        <a href="{{ route('admin.ai-insights.index', ['conversation' => $thread->uuid]) }}" class="block px-3 py-3 pr-9">
                            <p class="truncate text-sm font-medium {{ $conversation?->id === $thread->id ? 'text-indigo-100' : 'text-slate-200' }}">{{ $thread->title }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">{{ $thread->messages_count }} {{ __('messages') }}@if($thread->last_message_at) · {{ $thread->last_message_at->diffForHumans() }}@endif</p>
                        </a>
                        <form method="POST" action="{{ route('admin.ai-insights.conversations.archive', $thread->uuid) }}" class="absolute right-2 top-3 opacity-0 transition group-hover:opacity-100">
                            @csrf
                            @method('DELETE')
                            <button title="{{ __('Archive conversation') }}" class="rounded-lg p-1.5 text-slate-500 hover:bg-white/10 hover:text-rose-300">×</button>
                        </form>
                    </div>
                @empty
                    <div id="conversation-empty" class="px-3 py-8 text-center text-sm text-slate-500">{{ __('Your chats will appear here.') }}</div>
                @endforelse
            </div>
        </div>
    </aside>

    <section class="flex min-h-[72vh] min-w-0 flex-col overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <header class="flex items-center justify-between gap-3 border-b border-white/10 px-5 py-4">
            <div class="min-w-0">
                <h2 id="conversation-title" class="truncate font-semibold">{{ $conversation?->title ?? __('New conversation') }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">{{ __('Ask follow-up questions naturally — this thread remembers its context.') }}</p>
            </div>
            <span class="shrink-0 rounded-full bg-white/5 px-3 py-1 text-[11px] text-slate-500">{{ __('Read-only assistant') }}</span>
        </header>

        <div id="chat-scroll" class="flex-1 overflow-y-auto scroll-smooth px-4 py-6 sm:px-6">
            <div id="chat-messages" class="mx-auto max-w-4xl space-y-5">
                @if($messages->isEmpty())
                    <div id="chat-empty-state" class="flex min-h-[44vh] flex-col items-center justify-center text-center">
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
                    @foreach($messages as $message)
                        @if($message->role === 'user')
                            <article class="flex justify-end" data-message-id="{{ $message->uuid }}">
                                <div class="max-w-[88%] rounded-2xl rounded-br-md bg-indigo-500 px-4 py-3 text-sm leading-6 text-white shadow-lg shadow-indigo-950/20 sm:max-w-[78%]">
                                    <p class="whitespace-pre-wrap">{{ $message->content }}</p>
                                    <p class="mt-2 text-right text-[10px] text-indigo-200/70">{{ $message->created_at?->format('H:i') }}</p>
                                </div>
                            </article>
                        @else
                            <article class="flex items-start gap-3" data-message-id="{{ $message->uuid }}">
                                <div class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-[10px] font-black">AI</div>
                                <div class="min-w-0 max-w-[92%]">
                                    <div class="rounded-2xl rounded-tl-md border border-white/10 bg-slate-950/70 px-4 py-3 text-sm leading-6 text-slate-200">
                                        <p class="whitespace-pre-wrap">{{ $message->content }}</p>
                                        <button type="button" data-copy-answer class="mt-3 text-[11px] text-slate-500 hover:text-slate-300">{{ __('Copy answer') }}</button>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-[10px] text-slate-500">
                                        @php($providerStatus = data_get($message->meta, 'provider_status'))
                                        @php($tools = data_get($message->meta, 'tools_used', []))
                                        <span class="rounded-full bg-white/5 px-2 py-1">{{ $providerStatus === 'connected' ? __('AI provider') : ($providerStatus === 'fallback' ? __('Local fallback') : __('Local analysis')) }}</span>
                                        @if($message->model)<span>{{ $message->model }}</span>@endif
                                        @if($message->latency_ms)<span>· {{ $message->latency_ms }}ms</span>@endif
                                        @if(is_array($tools) && count($tools))<span>· {{ __('Tools') }}: {{ implode(', ', $tools) }}</span>@endif
                                    </div>
                                </div>
                            </article>
                        @endif
                    @endforeach
                @endif
                <div id="ask-fieldpulse-bottom"></div>
            </div>
        </div>

        <div id="provider-warning" class="mx-4 hidden rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-200 sm:mx-6"></div>

        <div class="border-t border-white/10 bg-slate-900/95 p-3 sm:p-4">
            <form id="ask-fieldpulse-form" method="POST" action="{{ route('admin.ai-insights.ask') }}" class="mx-auto max-w-4xl">
                @csrf
                <input id="conversation-uuid" type="hidden" name="conversation_uuid" value="{{ $currentConversationUuid }}">
                <div class="rounded-2xl border border-white/10 bg-slate-950 p-2 shadow-xl shadow-black/10 transition focus-within:border-indigo-400/40 focus-within:ring-2 focus-within:ring-indigo-500/10">
                    <textarea id="ask-fieldpulse-input" name="question" rows="1" maxlength="1000" required class="max-h-40 min-h-[52px] w-full resize-none border-0 bg-transparent px-3 py-3 text-sm leading-6 text-slate-100 outline-none placeholder:text-slate-600 focus:ring-0" placeholder="{{ __('Message Ask FieldPulse…') }}"></textarea>
                    <div class="flex items-center justify-between gap-3 px-2 pb-1">
                        <div class="hidden text-[11px] text-slate-600 sm:block">{{ __('Enter to send · Shift+Enter for new line') }}</div>
                        <button id="ask-fieldpulse-button" class="ml-auto inline-flex items-center gap-2 rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold transition hover:bg-indigo-400 disabled:cursor-not-allowed disabled:opacity-50">
                            <span>{{ __('Send') }}</span><span aria-hidden="true">↑</span>
                        </button>
                    </div>
                </div>
                <p id="ask-fieldpulse-status" class="mt-2 hidden px-2 text-xs text-slate-500" aria-live="polite"></p>
            </form>
        </div>
    </section>

    <aside class="space-y-4">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-4">
            <div class="mb-3 flex items-center justify-between"><h2 class="text-sm font-semibold">{{ __('Today at a glance') }}</h2><span class="text-[10px] uppercase tracking-wider text-slate-600">{{ $snapshot['date'] }}</span></div>
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
                    @php($dot = match($recommendation['severity']) { 'high' => 'bg-rose-400', 'medium' => 'bg-amber-400', default => 'bg-emerald-400' })
                    <div class="rounded-xl bg-slate-950/70 p-3">
                        <div class="flex gap-2">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>
                            <div><p class="text-xs font-semibold text-slate-200">{{ __($recommendation['title']) }}</p><p class="mt-1 text-[11px] leading-5 text-slate-500">{{ __($recommendation['message'], $recommendation['message_params'] ?? []) }}</p></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-4 text-xs text-slate-500">
            <p class="font-semibold text-slate-300">{{ __('Privacy & access') }}</p>
            <p class="mt-2 leading-5">{{ __('Ask FieldPulse is read-only. Tool access follows the signed-in user’s permissions and tenant scope.') }}</p>
            <p class="mt-2 leading-5">{{ $customerDataEnabled ? __('Customer-level AI tools are enabled for authorized users.') : __('Customer-level AI tools are currently disabled.') }}</p>
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

    if (!form || !input || !button || !messages) return;

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    };

    const bottom = () => document.getElementById('ask-fieldpulse-bottom');
    const scrollBottom = (smooth = true) => requestAnimationFrame(() => scroll.scrollTo({top: scroll.scrollHeight, behavior: smooth ? 'smooth' : 'auto'}));
    const resizeInput = () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    };

    const addUserMessage = (text) => {
        empty?.remove();
        const wrapper = document.createElement('article');
        wrapper.className = 'flex justify-end';
        const bubble = document.createElement('div');
        bubble.className = 'max-w-[88%] rounded-2xl rounded-br-md bg-indigo-500 px-4 py-3 text-sm leading-6 text-white shadow-lg shadow-indigo-950/20 sm:max-w-[78%]';
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
            '<div class="rounded-2xl rounded-tl-md border border-white/10 bg-slate-950/70 px-4 py-4"><div class="flex gap-1.5">' +
            '<span class="h-2 w-2 animate-pulse rounded-full bg-indigo-400"></span><span class="h-2 w-2 animate-pulse rounded-full bg-indigo-400"></span><span class="h-2 w-2 animate-pulse rounded-full bg-indigo-400"></span></div></div>';
        messages.insertBefore(wrapper, bottom());
        return wrapper;
    };

    const addAssistant = (payload, wrapper) => {
        wrapper.innerHTML = '';
        const avatar = document.createElement('div');
        avatar.className = 'mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-[10px] font-black';
        avatar.textContent = 'AI';

        const body = document.createElement('div');
        body.className = 'min-w-0 max-w-[92%]';
        const bubble = document.createElement('div');
        bubble.className = 'rounded-2xl rounded-tl-md border border-white/10 bg-slate-950/70 px-4 py-3 text-sm leading-6 text-slate-200';
        const answer = document.createElement('p');
        answer.className = 'whitespace-pre-wrap';
        answer.textContent = payload.answer || '';
        const copy = document.createElement('button');
        copy.type = 'button';
        copy.className = 'mt-3 text-[11px] text-slate-500 hover:text-slate-300';
        copy.textContent = @json(__('Copy answer'));
        copy.addEventListener('click', async () => {
            await navigator.clipboard?.writeText(payload.answer || '');
            copy.textContent = @json(__('Copied'));
            setTimeout(() => copy.textContent = @json(__('Copy answer')), 1200);
        });
        bubble.append(answer, copy);

        const meta = document.createElement('div');
        meta.className = 'mt-2 flex flex-wrap items-center gap-2 text-[10px] text-slate-500';
        const source = document.createElement('span');
        source.className = 'rounded-full bg-white/5 px-2 py-1';
        source.textContent = payload.provider_status === 'connected'
            ? @json(__('AI provider'))
            : payload.provider_status === 'fallback'
                ? @json(__('Local fallback'))
                : @json(__('Local analysis'));
        meta.appendChild(source);

        if (payload.model) {
            const model = document.createElement('span');
            model.textContent = payload.model;
            meta.appendChild(model);
        }
        if (payload.latency_ms) {
            const latency = document.createElement('span');
            latency.textContent = '· ' + payload.latency_ms + 'ms';
            meta.appendChild(latency);
        }
        if (Array.isArray(payload.tools_used) && payload.tools_used.length) {
            const tools = document.createElement('span');
            tools.textContent = '· ' + @json(__('Tools')) + ': ' + payload.tools_used.join(', ');
            meta.appendChild(tools);
        }

        body.append(bubble, meta);
        wrapper.append(avatar, body);
    };

    const updateProvider = (payload) => {
        providerWarning.classList.add('hidden');

        if (payload.provider_status === 'connected') {
            providerChip.className = 'inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1.5 text-emerald-300';
            providerChip.innerHTML = '<span class="h-2 w-2 rounded-full bg-emerald-400"></span><span>' + escapeHtml(@json(__('AI provider connected'))) + '</span>';
        } else if (payload.provider_status === 'fallback') {
            providerChip.className = 'inline-flex items-center gap-2 rounded-full border border-amber-400/20 bg-amber-500/10 px-3 py-1.5 text-amber-300';
            providerChip.innerHTML = '<span class="h-2 w-2 rounded-full bg-amber-400"></span><span>' + escapeHtml(@json(__('Local fallback active'))) + '</span>';
            providerWarning.textContent = payload.fallback_message || @json(__('The external AI provider was unavailable, so FieldPulse answered locally.'));
            providerWarning.classList.remove('hidden');
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = input.value.trim();
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

            addAssistant(payload, thinking);
            updateProvider(payload);

            if (payload.conversation?.uuid) {
                conversationUuid.value = payload.conversation.uuid;
                conversationTitle.textContent = payload.conversation.title;
                const url = new URL(window.location.href);
                url.searchParams.set('conversation', payload.conversation.uuid);
                history.replaceState({}, '', url);
            }

            status.classList.add('hidden');
            scrollBottom();
        } catch (error) {
            thinking.remove();
            status.textContent = error?.message || @json(__('Ask FieldPulse could not answer right now.'));
        } finally {
            button.disabled = false;
            input.focus();
        }
    });

    input.addEventListener('input', resizeInput);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
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
            const text = copy.parentElement?.querySelector('p')?.textContent || '';
            await navigator.clipboard?.writeText(text);
            copy.textContent = @json(__('Copied'));
            setTimeout(() => copy.textContent = @json(__('Copy answer')), 1200);
        });
    });

    resizeInput();
    scrollBottom(false);
})();
</script>
</x-layouts.app>
