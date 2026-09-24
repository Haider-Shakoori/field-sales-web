<div class="mt-3 max-h-[52vh] space-y-1 overflow-y-auto">
    @forelse($conversations as $conversation)
        <a
            href="{{ route('admin.ai-insights.index', ['chat' => $conversation->uuid]) }}"
            class="block rounded-xl px-3 py-3 transition {{ ($selectedConversation['uuid'] ?? null) === $conversation->uuid ? 'bg-indigo-500/10 text-white ring-1 ring-indigo-400/20' : 'text-slate-400 hover:bg-white/5 hover:text-slate-200' }}"
        >
            <p class="truncate text-sm font-medium">{{ $conversation->title }}</p>
            <p class="mt-1 text-[11px] text-slate-600">{{ $conversation->last_message_at?->diffForHumans() ?? $conversation->created_at?->diffForHumans() }}</p>
        </a>
    @empty
        <p class="rounded-xl border border-dashed border-white/10 px-3 py-5 text-center text-xs text-slate-500">
            {{ __('Your conversations will appear here.') }}
        </p>
    @endforelse
</div>

@if($archivedConversations->isNotEmpty())
    <details class="mt-3 border-t border-white/10 pt-3">
        <summary class="cursor-pointer text-xs font-semibold text-slate-500">
            {{ __('Archived conversations') }} · {{ $archivedConversations->count() }}
        </summary>
        <div class="mt-2 space-y-1 opacity-70">
            @foreach($archivedConversations as $conversation)
                <a
                    href="{{ route('admin.ai-insights.index', ['chat' => $conversation->uuid]) }}"
                    class="block truncate rounded-lg px-3 py-2 text-xs text-slate-500 hover:bg-white/5 hover:text-slate-300"
                >
                    {{ $conversation->title }}
                </a>
            @endforeach
        </div>
    </details>
@endif

<p class="mt-3 border-t border-white/10 pt-3 text-[11px] leading-5 text-slate-600">
    {{ __('History context uses the most recent messages; internal tool payloads are not shown.') }}
</p>
