<x-layouts.app>
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Add branch</h1>
        </div>

        <form method="POST" action="{{ route('admin.branches.store') }}" class="rounded-2xl border border-white/10 bg-slate-900 p-6">
            @csrf
            @include('admin.branches._form')

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin.branches.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5">Cancel</a>
                <button class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold hover:bg-indigo-400">Create branch</button>
            </div>
        </form>
    </div>
</x-layouts.app>
