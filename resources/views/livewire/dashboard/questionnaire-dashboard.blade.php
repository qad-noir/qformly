<div class="py-10">
        <div class="max-w-7xl mx-auto space-y-8 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Qformly</p>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Your questionnaire workspace</h2>
                </div>
                <a href="{{ route('questionnaires.create') }}" class="inline-flex items-center rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
                    New questionnaire
                </a>
            </div>
            @if (session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="rounded-xl bg-emerald-700 p-6 text-white shadow-sm">
                    <p class="text-sm font-medium text-emerald-100">Questionnaire projects</p>
                    <p class="mt-2 text-4xl font-semibold">{{ $projectCount }}</p>
                    <p class="mt-3 text-sm text-emerald-100">Upload a document, refine the questions, then generate a form.</p>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Generated Google Forms</p>
                    <p class="mt-2 text-4xl font-semibold text-gray-900 dark:text-white">{{ $formCount }}</p>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Completed forms across your projects.</p>
                </div>
            </div>

            <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Recent projects</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pick up where you left off.</p>
                    </div>
                    <a href="{{ route('questionnaires.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">View all</a>
                </div>

                @forelse ($recentProjects as $project)
                    <a href="{{ route('questionnaires.edit', $project) }}" class="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-4 last:border-0 hover:bg-emerald-50/50 dark:border-gray-700 dark:hover:bg-gray-700">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-gray-900 dark:text-white">{{ $project->title }}</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Updated {{ $project->updated_at->diffForHumans() }} · {{ $project->generated_forms_count }} generated {{ Str::plural('form', $project->generated_forms_count) }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $project->status === 'generated' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">{{ str($project->status)->headline() }}</span>
                    </a>
                @empty
                    <div class="px-6 py-12 text-center">
                        <p class="font-medium text-gray-900 dark:text-white">No questionnaires yet.</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Your first upload is only a few clicks away.</p>
                    </div>
                @endforelse
            </section>
        </div>
    </div>
</div>
