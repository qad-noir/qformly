<div class="py-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Qformly</p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ $project ? 'Generated forms: '.$project->title : 'Generated forms' }}</h2>
            </div>
            @if ($project)
                <a href="{{ route('questionnaires.edit', $project) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">Back to editor</a>
            @endif
        </div>
        <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Generation history</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Each attempt is retained here, including any setup errors.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                @if (! $project)<th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Project</th>@endif
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Respondent link</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Edit link</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($forms as $form)
                                <tr>
                                    @if (! $project)<td class="whitespace-nowrap px-6 py-4 text-sm font-medium"><a href="{{ route('questionnaires.edit', $form->project) }}" class="text-emerald-700 hover:text-emerald-800">{{ $form->project->title }}</a></td>@endif
                                    <td class="whitespace-nowrap px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-medium {{ $form->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($form->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">{{ str($form->status)->headline() }}</span></td>
                                    <td class="px-6 py-4 text-sm">@if ($form->respondent_url)<a href="{{ $form->respondent_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-emerald-700 hover:text-emerald-800">Open form ↗</a>@else<span class="text-gray-400">—</span>@endif</td>
                                    <td class="px-6 py-4 text-sm">@if ($form->edit_url)<a href="{{ $form->edit_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-emerald-700 hover:text-emerald-800">Open editor ↗</a>@else<span class="text-gray-400">—</span>@endif</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $form->created_at->format('M j, Y g:i A') }}</td>
                                </tr>
                                @if ($form->error_message)
                                    <tr class="bg-red-50/50 dark:bg-red-950/20"><td colspan="{{ $project ? 4 : 5 }}" class="px-6 pb-4 text-sm text-red-700 dark:text-red-300">{{ $form->error_message }}</td></tr>
                                @endif
                            @empty
                                <tr><td colspan="{{ $project ? 4 : 5 }}" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No generation attempts yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
    </div>
</div>
