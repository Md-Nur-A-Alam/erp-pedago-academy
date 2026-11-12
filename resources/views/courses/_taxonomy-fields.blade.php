<label class="flex flex-col gap-1.5 text-xs font-semibold" for="subject_id">
    Subject <span class="text-pedago-red">*</span>
    <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="subject_id" name="subject_id" required>
        <option value="">Select a subject</option>
        @foreach ($subjects as $subject)
            <option value="{{ $subject->id }}" @selected((string) old('subject_id', $course?->subject_id) === (string) $subject->id)>{{ $subject->name }}</option>
        @endforeach
        <option value="other" @selected(old('subject_id') === 'other')>Other</option>
    </select>
    @error('subject_id')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
</label>
<label class="hidden flex-col gap-1.5 text-xs font-semibold" for="subject_name" id="subject-name-field">
    New subject name <span class="text-pedago-red">*</span>
    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="subject_name" name="subject_name" value="{{ old('subject_name') }}" maxlength="100" placeholder="e.g. Science">
    @error('subject_name')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
</label>
<label class="flex flex-col gap-1.5 text-xs font-semibold" for="topic_id">
    Topic <span class="text-pedago-red">*</span>
    <select class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="topic_id" name="topic_id" required>
        <option value="">Select a topic</option>
    </select>
    @error('topic_id')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
</label>
<label class="hidden flex-col gap-1.5 text-xs font-semibold" for="topic_name" id="topic-name-field">
    New topic name <span class="text-pedago-red">*</span>
    <input class="w-full rounded-md border border-pedago-line bg-white px-3 py-2 text-sm font-normal focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="topic_name" name="topic_name" value="{{ old('topic_name') }}" maxlength="100" placeholder="e.g. Mechanics">
    @error('topic_name')<span class="font-normal text-pedago-red">{{ $message }}</span>@enderror
</label>

@pushOnce('scripts')
    <script>
        (() => {
            const subjectTopics = {{ \Illuminate\Support\Js::from($subjects->mapWithKeys(fn ($subject) => [
                (string) $subject->id => $subject->topics->map(fn ($topic) => ['id' => $topic->id, 'name' => $topic->name])->values(),
            ])->all()) }};
            const subjectSelect = document.getElementById('subject_id');
            const subjectNameField = document.getElementById('subject-name-field');
            const subjectNameInput = document.getElementById('subject_name');
            const topicSelect = document.getElementById('topic_id');
            const topicNameField = document.getElementById('topic-name-field');
            const topicNameInput = document.getElementById('topic_name');
            const selectedTopicId = {{ \Illuminate\Support\Js::from((string) old('topic_id', $course?->course_topic_id ?? '')) }};

            const updateTopicOptions = (preferredTopicId = '') => {
                const subjectId = subjectSelect.value;
                topicSelect.replaceChildren(new Option('Select a topic', ''));

                for (const topic of (subjectTopics[subjectId] ?? [])) {
                    topicSelect.add(new Option(topic.name, String(topic.id)));
                }

                topicSelect.add(new Option('Other', 'other'));
                topicSelect.value = subjectId === 'other' ? 'other' : preferredTopicId;

                if (!topicSelect.value) {
                    topicSelect.value = '';
                }

                const isNewSubject = subjectId === 'other';
                subjectNameField.classList.toggle('hidden', !isNewSubject);
                subjectNameField.classList.toggle('flex', isNewSubject);
                subjectNameInput.required = isNewSubject;

                const isNewTopic = topicSelect.value === 'other';
                topicNameField.classList.toggle('hidden', !isNewTopic);
                topicNameField.classList.toggle('flex', isNewTopic);
                topicNameInput.required = isNewTopic;
            };

            subjectSelect.addEventListener('change', () => updateTopicOptions());
            topicSelect.addEventListener('change', () => updateTopicOptions(topicSelect.value));
            updateTopicOptions(selectedTopicId);
        })();
    </script>
@endPushOnce
