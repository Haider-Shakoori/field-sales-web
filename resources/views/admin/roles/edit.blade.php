<x-layouts.app>
    <div class="mx-auto max-w-4xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Edit custom role</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $role->slug }}</p>
        </div>

        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="rounded-2xl border border-white/10 bg-slate-900 p-6">
            @csrf
            @method('PUT')
            @include('admin.roles._form')

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin.roles.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5">Cancel</a>
                <button class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold hover:bg-indigo-400">Save role</button>
            </div>
        </form>
    </div>
</x-layouts.app>
