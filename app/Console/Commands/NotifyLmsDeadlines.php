<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\LmsAssignment;
use App\Models\LmsQuiz;
use App\Notifications\SidaNotification;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class NotifyLmsDeadlines extends Command
{
    protected $signature = 'lms:notify-deadlines {--hours=24 : Notify deadlines in this many hours}';
    protected $description = 'Notify enrolled students about upcoming LMS assignment and quiz deadlines';

    public function handle(NotificationService $notifications): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $from = now();
        $until = $from->copy()->addHours($hours);
        $sent = 0;

        Course::query()
            ->with(['enrollments' => fn ($query) => $query->where('status', 'active')->with('student')])
            ->whereHas('assignments', fn ($query) => $query
                ->where('status', LmsAssignment::PUBLISHED)
                ->whereBetween('due_at', [$from, $until]))
            ->get()
            ->each(function (Course $course) use ($notifications, $from, $until, &$sent): void {
                $assignments = $course->assignments()
                    ->where('status', LmsAssignment::PUBLISHED)
                    ->whereBetween('due_at', [$from, $until])
                    ->get();
                foreach ($assignments as $assignment) {
                    $sent += $this->notifyCourseStudents(
                        $course,
                        $notifications,
                        NotificationService::ASSIGNMENT_DEADLINE,
                        'Deadline tugas: '.$assignment->title,
                        'Batas pengumpulan '.$assignment->due_at?->format('d M Y H:i').'.',
                        ['assignment_id' => $assignment->id, 'course_id' => $course->id]
                    );
                }
            });

        Course::query()
            ->with(['enrollments' => fn ($query) => $query->where('status', 'active')->with('student')])
            ->whereHas('quizzes', fn ($query) => $query
                ->where('status', LmsQuiz::PUBLISHED)
                ->whereBetween('due_at', [$from, $until]))
            ->get()
            ->each(function (Course $course) use ($notifications, $from, $until, &$sent): void {
                $quizzes = $course->quizzes()
                    ->where('status', LmsQuiz::PUBLISHED)
                    ->whereBetween('due_at', [$from, $until])
                    ->get();
                foreach ($quizzes as $quiz) {
                    $sent += $this->notifyCourseStudents(
                        $course,
                        $notifications,
                        NotificationService::QUIZ_DEADLINE,
                        'Deadline kuis: '.$quiz->title,
                        'Batas pengerjaan '.$quiz->due_at?->format('d M Y H:i').'.',
                        ['quiz_id' => $quiz->id, 'course_id' => $course->id]
                    );
                }
            });

        $this->info("Notifikasi deadline dikirim: {$sent}");

        return self::SUCCESS;
    }

    private function notifyCourseStudents(
        Course $course,
        NotificationService $notifications,
        string $type,
        string $title,
        string $body,
        array $context,
    ): int {
        $key = $type.'-'.implode('-', array_map(static fn ($value): string => (string) $value, $context));
        $sent = 0;

        foreach ($course->enrollments as $enrollment) {
            $userId = $enrollment->student?->user_id;
            if (! $userId || $this->alreadySent($userId, $key)) {
                continue;
            }

            $notifications->notifyUser($userId, $type, $title, $body, $context + ['deadline_key' => $key]);
            $sent++;
        }

        return $sent;
    }

    private function alreadySent(int $userId, string $key): bool
    {
        return DB::table('notifications')
            ->where('notifiable_id', $userId)
            ->where('notifiable_type', \App\Models\User::class)
            ->where('type', SidaNotification::class)
            ->whereJsonContains('data->context->deadline_key', $key)
            ->exists();
    }
}
