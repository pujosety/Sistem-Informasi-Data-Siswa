<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A generic approval engine, so the platform stops growing one approval system
 * per feature.
 *
 * WHY THIS EXISTS
 *
 * §39 requires leave requests, student letters, data corrections, CMS
 * publishing, procurement, mutations and document approval to share one
 * engine. The verification queue is the proof that a bespoke one was the
 * alternative: `verifications` is a table shaped around `registration_id` and
 * `document_id`, with a fixed five-value `action` enum. It cannot express "a
 * leave request" or "a page awaiting publication" without another table and
 * another service beside it, and §39 is explicit that seven incompatible
 * approval systems is the outcome to avoid.
 *
 * WHY entity_type / entity_id AND NOT A POLYMORPH FK
 *
 * Because the subject is not always a model row. A CMS publication is
 * workflow_instances + posts; a leave request is workflow_instances +
 * employee_leave. Polymorphic columns cannot carry a foreign key, and a
 * workflow that outlives the record it governs — a rejected request still has
 * to show who decided what — would lose its subject entirely if the subject
 * row cascaded away. This is the same reasoning behind
 * activity_logs.subject_type/subject_id already in the schema.
 *
 * WHAT IS *NOT* HERE
 *
 * No `assigned_to`. Steps name a ROLE or a PERMISSION, and the engine
 * resolves who may act from that at the moment of the action. Storing a user
 * id would freeze the approver at creation and would not survive an
 * organisation change: if the only person holding `verification.approve` leaves,
 * a queue pinned to their id is unanswerable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();

            $table->string('key', 60)->unique();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();

            // What kind of record this governs: registration, employee_leave,
            // cms_post, student_mutation. Informational — the instance carries
            // the actual subject.
            $table->string('entity_type', 80)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'workflow_definitions_active_index');
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('step_order');
            $table->string('name', 150);

            /*
             * Who may act, by capability rather than by identity.
             *
             * Both are nullable but not both set: a step is either role-bound or
             * permission-bound. The check is in the service layer, because a
             * partial CHECK constraint is not enforced by MySQL in the versions
             * this project supports.
             */
            $table->string('approver_role', 60)->nullable();
            $table->string('approver_permission', 100)->nullable();

            /*
             * What the engine is allowed to do on the way through.
             *
             * allow_reject and allow_delegate default true because a step that
             * can only ever say yes is a step nobody trusts. can_approve and
             * can_request_revision default true; can_comment is the escape
             * hatch that lets someone participate without deciding.
             */
            $table->boolean('can_approve')->default(true);
            $table->boolean('can_reject')->default(true);
            $table->boolean('can_request_revision')->default(true);
            $table->boolean('can_comment')->default(true);
            $table->boolean('allow_delegate')->default(true);

            $table->unsignedInteger('due_after_hours')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['workflow_definition_id', 'step_order'], 'workflow_steps_order_unique');
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();

            // The subject, when it is a model row. Not a foreign key on
            // purpose — see the migration docblock.
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id');

            // pending | in_progress | approved | rejected | cancelled
            $table->string('status', 20)->default('pending');

            $table->unsignedSmallInteger('current_step')->default(1);

            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Set when the outcome is a rejection, so the reason is kept
            // together rather than being inferred from the last action.
            $table->text('outcome_note')->nullable();

            $table->timestamps();

            // One live workflow per subject: starting a second one while the
            // first is open is almost always a double-submit, and two open
            // workflows for one record make "what is the status" ambiguous.
            $table->index(['entity_type', 'entity_id', 'status'], 'workflow_instances_subject_index');
            $table->index('status', 'workflow_instances_status_index');
        });

        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('step_order')->nullable();

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            // approve | reject | revise | delegate | comment | cancel
            $table->string('action', 20);

            $table->text('note')->nullable();

            // Set when this action was a delegation, so the trail shows both the
            // person who handed it over and the person who acted.
            $table->foreignId('delegated_from_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_instance_id', 'acted_at'], 'workflow_actions_instance_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_actions');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_definitions');
    }
};
