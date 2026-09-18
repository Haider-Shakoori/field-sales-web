<x-layouts.app>
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Edit salesman</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $salesman->employee_code }}</p>
        </div>
        <form method="POST" action="{{ route('admin.salesmen.update', $salesman) }}" class="rounded-2xl border border-white/10 bg-slate-900 p-6">
            @csrf
            @method('PUT')
            @include('admin.salesmen._form')
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin.salesmen.show', $salesman) }}" class="rounded-xl bg-white/10 px-4 py-2.5">Cancel</a>
                <button class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Save changes</button>
            </div>
        </form>
    </div>
</x-layouts.app>
