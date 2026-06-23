<div class="py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div>
            <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Qformly</p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Create questionnaire project</h2>
        </div>
        <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <form wire:submit="save" class="space-y-6 p-6 sm:p-8">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Start with your source document</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">We’ll extract the questions into an editable draft. TXT and DOCX files are supported up to 5 MB.</p>
                    </div>

                    <div>
                        <x-label for="title" value="Project title" />
                        <x-input id="title" wire:model="title" type="text" class="mt-1 block w-full" autofocus />
                        <x-input-error for="title" class="mt-2" />
                    </div>

                    <div>
                        <x-label for="description" value="Description (optional)" />
                        <textarea id="description" wire:model="description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"></textarea>
                        <x-input-error for="description" class="mt-2" />
                    </div>

                    <div>
                        <x-label for="file" value="Questionnaire file" />
                        <input id="file" wire:model="file" type="file" accept=".txt,.docx" class="mt-2 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100 dark:text-gray-200 dark:file:bg-emerald-900/40 dark:file:text-emerald-300" />
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">PDF support is on its way; use a TXT or DOCX file for this MVP.</p>
                        <x-input-error for="file" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-6 dark:border-gray-700">
                        <a href="{{ route('questionnaires.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">Cancel</a>
                        <x-button class="bg-emerald-700 hover:bg-emerald-800 focus:bg-emerald-800 active:bg-emerald-900" wire:loading.attr="disabled">
                            <span wire:loading.remove>Create and parse</span>
                            <span wire:loading>Parsing questionnaire…</span>
                        </x-button>
                    </div>
                </form>
            </div>
    </div>
</div>
