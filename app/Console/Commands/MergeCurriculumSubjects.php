<?php

namespace App\Console\Commands;

use App\Models\Concept;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\Models\SyllabusTopic;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

#[Signature('app:merge-curriculum-subjects {target : Subject ID to retain} {sources* : Duplicate subject IDs} {--apply : Commit the merge and save a backup; otherwise roll back the preview}')]
#[Description('Merge subjects with matching names and compatible codes, preserving topic hierarchy, concepts, and files.')]
class MergeCurriculumSubjects extends Command
{
    public function handle(): int
    {
        DB::beginTransaction();

        try {
            $target = Subject::query()->findOrFail($this->argument('target'));
            Program::query()->lockForUpdate()->findOrFail($target->program_id);
            $ids = array_unique([$target->id, ...$this->argument('sources')]);
            $subjects = Subject::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            if ($subjects->count() !== count($ids) || $subjects->count() < 2) {
                throw new RuntimeException('Provide an existing target and at least one different existing source subject.');
            }
            $target = $subjects->find($target->id);
            $codes = $subjects->pluck('code')->filter(fn (?string $code): bool => $code !== null)->map(fn (string $code): string => mb_strtolower(trim($code)))->unique();
            if ($codes->count() > 1) {
                throw new RuntimeException('Subject codes conflict. Resolve the intended code before merging.');
            }
            foreach ($subjects as $subject) {
                if ($subject->program_id !== $target->program_id || mb_strtolower(trim($subject->name)) !== mb_strtolower(trim($target->name))) {
                    throw new RuntimeException('All subjects must belong to the same program and have matching names.');
                }
            }

            $topics = SyllabusTopic::query()->whereIn('subject_id', $ids)->orderBy('id')->lockForUpdate()->get();
            $concepts = Concept::query()->whereIn('subject_id', $ids)->lockForUpdate()->get();
            $files = SubjectFile::query()->whereIn('subject_id', $ids)->lockForUpdate()->get();
            foreach ($topics as $topic) {
                if ($topic->parent_id !== null && $topics->find($topic->parent_id)?->subject_id !== $topic->subject_id) {
                    throw new RuntimeException('A topic references a parent outside its subject.');
                }
            }
            foreach ($concepts as $concept) {
                if ($concept->syllabus_topic_id !== null && $topics->find($concept->syllabus_topic_id)?->subject_id !== $concept->subject_id) {
                    throw new RuntimeException('A concept references a topic outside its subject.');
                }
            }
            if ($concepts->count() !== $concepts->pluck('code')->map(fn (string $code): string => mb_strtolower(trim($code)))->unique()->count()) {
                throw new RuntimeException('Concept codes conflict. Resolve them before merging.');
            }
            $backup = [
                'subjects' => $subjects->map->getAttributes()->all(),
                'syllabus_topics' => $topics->map->getAttributes()->all(),
                'concepts' => $concepts->map->getAttributes()->all(),
                'subject_files' => $files->map->getAttributes()->all(),
            ];

            $targetTopics = $topics->where('subject_id', $target->id)->values();
            $pending = $topics->where('subject_id', '!=', $target->id)->keyBy('id');
            $resolved = [];
            $merged = [];
            while ($pending->isNotEmpty()) {
                $progress = false;
                foreach ($pending as $topic) {
                    if ($topic->parent_id !== null && ! array_key_exists($topic->parent_id, $resolved)) {
                        continue;
                    }
                    $parentId = $topic->parent_id === null ? null : $resolved[$topic->parent_id];
                    $matches = $targetTopics->filter(function (SyllabusTopic $existing) use ($topic, $parentId): bool {
                        $sameCode = $topic->code !== null && $existing->code !== null && mb_strtolower(trim($existing->code)) === mb_strtolower(trim($topic->code));

                        return $sameCode || ($existing->parent_id === $parentId && mb_strtolower(trim($existing->title)) === mb_strtolower(trim($topic->title)));
                    });
                    if ($matches->count() > 1) {
                        throw new RuntimeException("Ambiguous topic: {$topic->id} ({$topic->title}).");
                    }
                    $match = $matches->first();
                    if ($match !== null) {
                        if ($match->parent_id !== $parentId || mb_strtolower(trim($match->title)) !== mb_strtolower(trim($topic->title)) || ($match->code !== null && $topic->code !== null && mb_strtolower(trim($match->code)) !== mb_strtolower(trim($topic->code)))) {
                            throw new RuntimeException("Topic code, title, or hierarchy conflicts: {$topic->id} ({$topic->title}).");
                        }
                        $match->update(['code' => $match->code ?? $topic->code, 'description' => $match->description ?? $topic->description]);
                        $resolved[$topic->id] = $match->id;
                        $merged[] = $topic->id;
                    } else {
                        $topic->update(['subject_id' => $target->id, 'parent_id' => $parentId]);
                        $targetTopics->push($topic);
                        $resolved[$topic->id] = $topic->id;
                    }
                    $pending->forget($topic->id);
                    $progress = true;
                }
                if (! $progress) {
                    throw new RuntimeException('A topic hierarchy is cyclic or references a parent outside its subject.');
                }
            }

            foreach ($concepts as $concept) {
                $concept->update([
                    'subject_id' => $target->id,
                    'syllabus_topic_id' => $resolved[$concept->syllabus_topic_id] ?? $concept->syllabus_topic_id,
                ]);
            }
            SubjectFile::query()->whereIn('subject_id', $ids)->update(['subject_id' => $target->id]);
            SyllabusTopic::query()->whereKey($merged)->delete();
            Subject::query()->whereKey($ids)->whereKeyNot($target->id)->delete();
            $target->update([
                'code' => $target->code ?? $subjects->pluck('code')->filter()->first(),
                'description' => $target->description ?? $subjects->pluck('description')->filter()->first(),
            ]);

            $this->info(sprintf('Retain subject %d; remove %d duplicate subjects; consolidate %d topics; retain %d topics, %d concepts, and %d files.', $target->id, $subjects->count() - 1, count($merged), $targetTopics->count(), $concepts->count(), $files->count()));
            if ($this->option('apply')) {
                $path = 'curriculum-merges/'.Str::uuid().'.json';
                if (! Storage::disk('local')->put($path, json_encode($backup, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
                    throw new RuntimeException('Could not save the backup. The merge was rolled back.');
                }
                DB::commit();
                $this->info('Merged. Backup: '.Storage::disk('local')->path($path));
            } else {
                DB::rollBack();
                $this->info('Preview only. No records changed.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
