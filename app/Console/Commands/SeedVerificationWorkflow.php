<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use App\Services\VerificationService;
use Illuminate\Console\Command;

/**
 * Seeds the `registration.verification` workflow definition.
 *
 * WHY A COMMAND AND NOT A MIGRATION
 *
 * The workflow tables already exist, so there is nothing to migrate. What is
 * missing is *data*: a definition for the PPDB document flow. And the engine
 * tables are deliberately not seeded from a migration, because a re-run of
 * migrations must never reset an operator's configuration — an edited step
 * order or a deactivated definition is theirs, and silently restoring a
 * shipped default on the next deploy would rewrite a decision someone made on
 * purpose. EnableBuiltModulesCommand sets the same precedent for exactly this
 * reason: these are data changes, and this command is the idempotent way to
 * make them.
 *
 * IDEMPOTENCE
 *
 * updateOrCreate on the definition key, then updateOrCreate per step_order,
 * and steps above the declared count are pruned. Running this twice changes
 * nothing the second time, and running it after an operator has tweaked a step
 * restores the shipped shape rather than duplicating or tripping the
 * (workflow_definition_id, step_order) unique constraint.
 */
class SeedVerificationWorkflow extends Command
{
    protected $signature = 'sida:seed-verification-workflow {--dry-run : Show what would be written}';

    protected $description = 'Seed the registration.verification workflow definition (idempotent)';

    /**
     * The definition key. Kept as a constant on VerificationService too, so a
     * typo in either place is a class-loading error rather than a workflow
     * that silently never starts.
     */
    private const KEY = 'registration.verification';

    /**
     * One step, not two.
     *
     * The tempting shape is two steps — `verification.approve` then
     * `verification.request_revision` — but that is wrong for this flow. The
     * engine only reaches APPROVED when the LAST step approves, so a
     * two-step definition would leave every approved document with an
     * instance stuck IN_PROGRESS at step 2, and a second approver who does
     * not exist. The revision request is an *action on* the verification
     * step, not a later step after it, and workflow_steps already models
     * that with can_request_revision.
     *
     * The permission gate is therefore `verification.approve` — held by
     * verifikator and admin — and VerificationService additionally checks
     * `verification.request_revision` before recording a revise, because a
     * step can name exactly one approver_permission and the two permissions
     * are genuinely different capabilities.
     */
    private const STEPS = [
        [
            'step_order' => 1,
            'name' => 'Verifikasi Dokumen',
            'approver_permission' => 'verification.approve',
            'can_approve' => true,
            'can_reject' => true,
            'can_request_revision' => true,
            'can_comment' => true,
            'allow_delegate' => true,
            'due_after_hours' => 48,
            'notes' => 'Step 1 of 1. Binds verification.approve; verification.request_revision '
                .'is enforced by VerificationService because a step can carry only one '
                .'approver_permission.',
        ],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $existing = WorkflowDefinition::query()->where('key', self::KEY)->first();

        $this->line($existing
            ? "  [".self::KEY.'] updating existing definition #'.$existing->id
            : '  ['.self::KEY.'] creating new definition');

        if ($dry) {
            $this->line('  Dry run: '.count(self::STEPS).' step(s) would be written.');

            return self::SUCCESS;
        }

        $definition = WorkflowDefinition::updateOrCreate(
            ['key' => self::KEY],
            [
                'name' => 'Verifikasi Dokumen PPDB',
                'description' => 'Satu langkah: verifikator memeriksa berkas dan menyetujui, '
                    .'menolak dengan alasan, atau meminta perbaikan.',
                'entity_type' => Document::class,
                'is_active' => true,
                'sort_order' => 10,
            ]
        );

        foreach (self::STEPS as $step) {
            WorkflowStep::updateOrCreate(
                [
                    'workflow_definition_id' => $definition->id,
                    'step_order' => $step['step_order'],
                ],
                $step + ['workflow_definition_id' => $definition->id]
            );
        }

        // A step left behind by an earlier, longer definition would make the
        // workflow completable only after a step nobody configured.
        $definition->steps()
            ->where('step_order', '>', max(array_column(self::STEPS, 'step_order')))
            ->delete();

        $this->info(sprintf(
            'Seeded [%s] (#%d) with %d step(s). Wiring key: %s::WORKFLOW_KEY',
            self::KEY,
            $definition->id,
            count(self::STEPS),
            VerificationService::class
        ));

        return self::SUCCESS;
    }
}
