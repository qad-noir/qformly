<div class="py-8">
    <div class="max-w-7xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Qformly editor</p>
                <h2 class="truncate font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ $project->title }}</h2>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('questionnaires.forms', $project) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Generated forms</a>
                @if ($project->extracted_text)
                    <button wire:click="reparseOriginalUpload" wire:confirm="Reparse the original upload? This replaces the current sections, questions, and options with a fresh parse." type="button" class="inline-flex items-center rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 shadow-sm hover:bg-amber-100" wire:loading.attr="disabled">Reparse original</button>
                @endif
                <button wire:click="save" type="button" class="inline-flex items-center rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2" wire:loading.attr="disabled">Save changes</button>
            </div>
        </div>
            @if (session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <form wire:submit="save" class="space-y-6">
                <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <x-label for="project-title" value="Questionnaire title" />
                            <x-input id="project-title" wire:model="title" class="mt-1 block w-full" />
                            <x-input-error for="title" class="mt-2" />
                        </div>
                        <div>
                            <x-label for="project-description" value="Description" />
                            <textarea id="project-description" wire:model="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"></textarea>
                            <x-input-error for="description" class="mt-2" />
                        </div>
                    </div>
                </section>

                @forelse ($sections as $sectionIndex => $section)
                    <section wire:key="section-{{ $sectionIndex }}" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <div class="flex flex-col gap-4 border-b border-gray-100 p-6 sm:flex-row sm:items-start sm:justify-between dark:border-gray-700">
                            <div class="flex-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Section title</label>
                                <input wire:model="sections.{{ $sectionIndex }}.title" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200" />
                                <textarea wire:model="sections.{{ $sectionIndex }}.help_text" rows="2" placeholder="Optional section instructions" class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"></textarea>
                            </div>
                            <button wire:click="removeSection({{ $sectionIndex }})" type="button" class="text-sm font-semibold text-red-600 hover:text-red-800">Remove section</button>
                        </div>

                        <div class="space-y-4 p-6">
                            @forelse ($section['questions'] as $questionIndex => $question)
                                <article wire:key="question-{{ $sectionIndex }}-{{ $questionIndex }}" class="rounded-lg border border-gray-200 p-5 dark:border-gray-700">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Question {{ $question['number'] ?: $questionIndex + 1 }}</p>
                                        <button wire:click="removeQuestion({{ $sectionIndex }}, {{ $questionIndex }})" type="button" class="text-sm font-medium text-red-600 hover:text-red-800">Remove</button>
                                    </div>
                                    <div class="mt-4 grid gap-4 lg:grid-cols-12">
                                        <div class="lg:col-span-8">
                                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Question</label>
                                            <textarea wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.title" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"></textarea>
                                            <x-input-error for="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.title" class="mt-2" />
                                        </div>
                                        <div class="lg:col-span-4">
                                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Question type</label>
                                            <select wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                                @foreach ($questionTypes as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <label class="mt-4 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                <input wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.required" type="checkbox" value="1" class="rounded border-gray-300 text-emerald-700 shadow-sm focus:ring-emerald-500">
                                                Required answer
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Help text (optional)</label>
                                        <input wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.help_text" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200" />
                                    </div>

                                    @if (in_array($question['type'], ['multiple_choice', 'checkboxes', 'dropdown', 'multiple_choice_grid', 'likert'], true))
                                        <div class="mt-5 border-t border-gray-100 pt-4 dark:border-gray-700">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ $question['type'] === 'multiple_choice_grid' ? 'Rows / sub-questions' : 'Options' }}
                                                    </p>
                                                    @if ($question['type'] === 'multiple_choice_grid')
                                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Shared answer choices are read from the help text, e.g. “Response choices: Yes; No”.</p>
                                                    @endif
                                                </div>
                                                <button wire:click="addOption({{ $sectionIndex }}, {{ $questionIndex }})" type="button" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">{{ $question['type'] === 'multiple_choice_grid' ? 'Add row' : 'Add option' }}</button>
                                            </div>
                                            <x-input-error for="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.options" class="mt-2" />
                                            <div class="mt-3 space-y-2">
                                                @foreach ($question['options'] as $optionIndex => $option)
                                                    <div wire:key="option-{{ $sectionIndex }}-{{ $questionIndex }}-{{ $optionIndex }}" class="flex items-center gap-2">
                                                        <input wire:model="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.options.{{ $optionIndex }}.label" type="text" placeholder="{{ $question['type'] === 'multiple_choice_grid' ? 'Row label' : 'Option label' }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200" />
                                                        <button wire:click="removeOption({{ $sectionIndex }}, {{ $questionIndex }}, {{ $optionIndex }})" type="button" class="rounded p-2 text-red-600 hover:bg-red-50" aria-label="Remove option">×</button>
                                                    </div>
                                                    <x-input-error for="sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.options.{{ $optionIndex }}.label" class="mt-1" />
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </article>
                            @empty
                                <p class="rounded-lg border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">No questions in this section yet.</p>
                            @endforelse

                            <button wire:click="addQuestion({{ $sectionIndex }})" type="button" class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">Add question</button>
                        </div>
                    </section>
                @empty
                    <section class="rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <p class="font-medium text-gray-900 dark:text-white">The parser didn’t find sections.</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add one below and start composing.</p>
                    </section>
                @endforelse

                <button wire:click="addSection" type="button" class="inline-flex items-center rounded-md border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50 dark:bg-gray-800">Add section</button>
            </form>

            <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-900 dark:bg-emerald-950/40">
                <div class="border-b border-emerald-200 pb-5 dark:border-emerald-900">
                    <h3 class="font-semibold text-emerald-950 dark:text-emerald-100">Google account</h3>
                    @if ($googleMockMode)
                        <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">Mock mode is active. Qformly will generate safe placeholder links and will not connect to Google.</p>
                    @elseif (! $googleConfigured)
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">{{ $googleConfigurationMessage }}</p>
                    @elseif ($googleConnection)
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <p class="text-sm text-emerald-800 dark:text-emerald-200">Connected as <span class="font-semibold">{{ $googleConnection->google_email ?: 'Google account' }}</span></p>
                            <form method="POST" action="{{ route('google.disconnect') }}">
                                @csrf
                                <button type="submit" class="text-sm font-semibold text-red-700 hover:text-red-800 dark:text-red-300">Disconnect</button>
                            </form>
                        </div>
                    @else
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <p class="text-sm text-emerald-800 dark:text-emerald-200">Connect Google to create a real Google Form from this questionnaire.</p>
                            <a href="{{ route('google.connect', ['project' => $project->id]) }}" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-100">Connect Google Account</a>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
                    <div>
                        <h3 class="font-semibold text-emerald-950 dark:text-emerald-100">Generate a Google Form</h3>
                        @if ($googleMockMode)
                            <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">Mock mode is active, so Qformly will generate safe local placeholder links.</p>
                        @elseif (! $googleConfigured)
                            <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">Add the missing Google credentials before real generation can start.</p>
                        @elseif (! $googleConnection)
                            <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">Connect your Google account before generating a real form.</p>
                        @else
                            <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">Your connected account will own the newly created Google Form.</p>
                        @endif
                    </div>
                    <button wire:click="generateForm" type="button" @disabled(! $canGenerateForm) class="inline-flex shrink-0 items-center justify-center rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" wire:loading.attr="disabled">Generate Google Form</button>
                </div>

                @if ($latestForm && $latestForm->status === 'completed')
                    <div class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                        @if ($latestForm->respondent_url)
                            <a href="{{ $latestForm->respondent_url }}" target="_blank" rel="noopener noreferrer" class="rounded-md bg-white px-4 py-3 font-semibold text-emerald-700 shadow-sm hover:bg-emerald-100 dark:bg-gray-800">Open respondent link ↗</a>
                        @else
                            <span class="rounded-md bg-white px-4 py-3 font-medium text-gray-600 shadow-sm dark:bg-gray-800 dark:text-gray-300">Respondent link unavailable</span>
                        @endif
                        <a href="{{ $latestForm->edit_url }}" target="_blank" rel="noopener noreferrer" class="rounded-md bg-white px-4 py-3 font-semibold text-emerald-700 shadow-sm hover:bg-emerald-100 dark:bg-gray-800">Open edit link ↗</a>
                    </div>
                @endif
            </section>
    </div>
</div>
