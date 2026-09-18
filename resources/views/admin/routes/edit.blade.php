<x-layouts.app>
    <div class="mx-auto max-w-4xl">
        <h1 class="mb-6 text-2xl font-bold">Edit route</h1>
        <form method="POST" action="{{ route('admin.routes.update', $route) }}" class="rounded-2xl border border-white/10 bg-slate-900 p-6">
            @csrf
            @method('PUT')
            @include('admin.routes._form')
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin.routes.show', $route) }}" class="rounded-xl bg-white/10 px-4 py-2.5">Cancel</a>
                <button class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Save route</button>
            </div>
        </form>
    </div>
</x-layouts.app>
