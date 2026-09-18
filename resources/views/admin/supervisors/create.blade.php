<x-layouts.app>
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-6 text-2xl font-bold">Add supervisor</h1>
        <form method="POST" action="{{ route('admin.supervisors.store') }}" class="rounded-2xl border border-white/10 bg-slate-900 p-6">
            @csrf
            @include('admin.supervisors._form')
            <div class="mt-6 flex justify-end gap-3"><a href="{{ route('admin.supervisors.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5">Cancel</a><button class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Create supervisor</button></div>
        </form>
    </div>
</x-layouts.app>
