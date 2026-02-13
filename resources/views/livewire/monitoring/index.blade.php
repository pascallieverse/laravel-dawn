<div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Monitoring</h1>

    {{-- Add Tag Form --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6 mb-6">
        <form wire:submit="addTag" class="flex flex-col sm:flex-row gap-3">
            <input
                wire:model="newTag"
                type="text"
                placeholder="e.g. App\Models\User:1"
                class="flex-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-dawn-500 focus:border-dawn-500 dark:focus:ring-dawn-400 dark:focus:border-dawn-400"
            />
            <button
                type="submit"
                class="px-4 py-2 bg-dawn-500 hover:bg-dawn-600 text-white text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-dawn-500 focus:ring-offset-2"
            >
                Monitor
            </button>
        </form>
        @error('newTag')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- Tags List --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden">
        @if(count($tags) > 0)
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($tags as $tag)
                    <li class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <a
                            href="{{ route('dawn.monitoring.tag', ['tag' => $tag]) }}"
                            wire:navigate
                            class="text-sm font-medium text-dawn-600 dark:text-dawn-400 hover:text-dawn-700 dark:hover:text-dawn-300"
                        >
                            {{ $tag }}
                        </a>
                        <button
                            wire:click="removeTag('{{ $tag }}')"
                            class="text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                No tags are being monitored.
            </div>
        @endif
    </div>
</div>
