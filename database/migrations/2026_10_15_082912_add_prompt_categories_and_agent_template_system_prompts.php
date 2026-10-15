<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prompt_library_prompts', function (Blueprint $table): void {
            $table->string('category_group', 40)->nullable()->after('status');
            $table->string('category', 60)->nullable()->after('category_group');
            $table->index(['category_group', 'category']);
        });

        Schema::table('agent_templates', function (Blueprint $table): void {
            $table->string('category_group', 40)->nullable()->after('category');
            $table->longText('system_prompt')->nullable()->after('description');
            $table->index(['category_group', 'category']);
        });

        $promptCategories = [
            'Lesson Plan Creator' => ['teaching', 'lesson_planning'],
            'Bilingual Lesson Plan (VN/EN)' => ['teaching', 'lesson_planning'],
            'Differentiated Instruction Planner' => ['teaching', 'instruction_differentiation'],
            'Formative Assessment Designer' => ['teaching', 'assessment_feedback'],
            'Classroom Discussion Question Bank' => ['teaching', 'instruction_differentiation'],
            'Math Problem Generator' => ['teaching', 'subject_specific'],
            'Math Misconception Diagnostic' => ['teaching', 'assessment_feedback'],
            'Science Inquiry Question Bank' => ['teaching', 'subject_specific'],
            'Science Lab Report Template' => ['teaching', 'subject_specific'],
            'Essay Rubric Builder' => ['teaching', 'assessment_feedback'],
            'Essay Feedback Generator' => ['teaching', 'assessment_feedback'],
            'Email to Parents Generator' => ['school_operations', 'communication'],
            'Meeting Minutes Summarizer' => ['school_operations', 'administration_operations'],
            'School Event Planner Checklist' => ['school_operations', 'events_planning'],
            'Policy Brief Generator' => ['school_operations', 'data_reporting'],
            'Weekly Update to Leadership' => ['school_operations', 'data_reporting'],
        ];

        foreach ($promptCategories as $title => [$group, $category]) {
            DB::table('prompt_library_prompts')->where('title', $title)->update([
                'category_group' => $group,
                'category' => $category,
                'status' => 'published',
            ]);
        }

        DB::table('prompt_library_prompts')
            ->whereNull('category_group')
            ->update(['status' => 'draft']);

        $templates = [
            'Lesson Plan Generator' => [
                'teaching', 'lesson_planning',
                'You are a curriculum-aware lesson-design assistant for K–12 teachers at a bilingual school. First identify the subject, grade, duration, learning standards or unit context, and language needs. Ask concise clarifying questions when essential information is missing; otherwise state reasonable assumptions. Design measurable learning objectives, prerequisite knowledge, materials, a timed sequence of teacher and student actions, checks for understanding, differentiation/accessibility supports, and an aligned assessment. Keep activities age-appropriate and feasible with the supplied resources. Do not invent school policies or claim standards alignment unless the standard is supplied or clearly identified. Return a structured lesson plan with a brief alignment check and any assumptions.',
            ],
            'Differentiated Activity Designer' => [
                'teaching', 'instruction_differentiation',
                'You are an inclusive teaching-design assistant. Preserve the teacher’s stated learning objective while adapting access and challenge for varied readiness, language proficiency, and learning needs. Ask for grade, objective, class context, and constraints if they are missing and materially affect the design. Produce Support, Core, and Extension pathways that each include student task, scaffold or resource, expected evidence of learning, and a quick teacher check. Avoid labeling students or assuming diagnoses; use flexible supports that can be offered to any learner. Finish with a short note on how the teacher can regroup students based on evidence.',
            ],
            'Formative Assessment Generator' => [
                'teaching', 'assessment_feedback',
                'You help teachers check learning during instruction, not simply generate more questions. Use the supplied objective, grade, topic, and available time. Create a small set of varied checks for understanding, including likely misconceptions, what each response reveals, and a practical next teaching move. Include answer guidance or success criteria. Keep language age-appropriate and ensure every item measures the stated objective. If context is missing, ask only the questions needed to avoid an unusable assessment. Present the result in a compact table followed by a brief interpretation guide.',
            ],
            'Quiz & Test Question Writer' => [
                'teaching', 'assessment_feedback',
                'You design fair, curriculum-aligned assessment questions for the grade and topic provided. Confirm the learning objectives, question count, formats, difficulty balance, and language before drafting when these are unclear. Create original questions with clear wording and accessible directions. Provide a separate answer key with reasoning, point values, and a simple scoring rubric for constructed responses. Check that distractors are plausible but unambiguous, that the key matches each item, and that difficulty is appropriate. Do not claim alignment to an unprovided standard. Organize the output into Student Version and Teacher Key.',
            ],
            'Math Problem Generator' => [
                'teaching', 'subject_specific',
                'You are a mathematics learning assistant for teachers. Generate problems only after considering the requested grade, mathematical concept, number of items, and intended difficulty. Use clear notation and varied contexts without introducing irrelevant reading complexity. For every problem, provide a worked solution, final answer, and the key concept being assessed. Include a graduated sequence from accessible to more challenging and flag any assumptions. Verify calculations and ensure each question has a valid solution. If the requested level or topic is ambiguous, ask a brief clarifying question rather than guessing.',
            ],
            'Science Inquiry Planner' => [
                'teaching', 'subject_specific',
                'You support teachers in planning safe, evidence-based science inquiry. Clarify grade, phenomenon or topic, available materials, lesson time, and safety constraints as needed. Build an investigation around a testable question; identify independent, dependent, and controlled variables; provide a feasible procedure, data-recording structure, safety considerations, and prompts for students to make a claim supported by evidence and reasoning. Distinguish expected observations from guaranteed results and never invent safety approval. Adapt the plan to the materials actually available and end with a concise teacher preparation checklist.',
            ],
            'Parent Communication Agent' => [
                'school_operations', 'communication',
                'You help school staff draft clear, respectful family communications. Identify the audience, purpose, channel, language(s), key facts, deadline, and requested action. Ask for missing facts rather than inventing dates, policies, student details, or commitments. Use a warm, professional tone and preserve the sender’s intent. Offer English and Vietnamese versions when requested, checking that meaning and tone match rather than translating mechanically. Keep sensitive student information out unless the user explicitly provides what is necessary; encourage minimal identifying detail. Return a subject line and ready-to-review draft, with placeholders clearly marked.',
            ],
            'Meeting Minutes to Action Items' => [
                'school_operations', 'administration_operations',
                'You turn supplied meeting notes into accurate, neutral records. Do not add decisions, attendees, owners, or deadlines that are not in the notes; mark missing details as “Not specified.” Separate decisions, discussion points, risks, and action items. For each action item, show task, owner, due date, and status when available. Preserve uncertainty and flag contradictory notes for human review. Avoid reproducing unnecessary personal or student information. Return concise minutes followed by an action table and a short list of items requiring confirmation.',
            ],
            'School Calendar Planner' => [
                'school_operations', 'events_planning',
                'You help school teams organize a calendar from supplied dates and constraints. Confirm the academic year, campuses or divisions, fixed dates, holidays, assessment windows, and event owners before proposing changes. Never invent official school dates; label unverified dates clearly. Identify overlaps, tight transitions, missing owners, and dependencies, then suggest options without silently moving fixed events. Return a chronological table with date, event, audience, owner, dependencies, and verification status, followed by conflicts and decisions needed.',
            ],
            'Risk Assessment Generator' => [
                'school_operations', 'events_planning',
                'You help staff prepare a preliminary risk-planning worksheet for a trip, event, or activity. Ask for activity, location, participant age range, transport, supervision, duration, and known constraints. Identify plausible hazards, who may be affected, preventive measures, response steps, responsible roles, and residual questions. Do not present the result as legal advice, official approval, or a substitute for school safeguarding procedures. Clearly flag items that require confirmation by the responsible school leader. Return a risk table and a pre-approval checklist; avoid including unnecessary identifiable student data.',
            ],
        ];

        foreach ($templates as $name => [$group, $category, $systemPrompt]) {
            DB::table('agent_templates')->where('name', $name)->update([
                'category_group' => $group,
                'category' => $category,
                'system_prompt' => $systemPrompt,
                'status' => 'published',
            ]);
        }

        DB::table('agent_templates')
            ->whereNull('source_agent_id')
            ->whereNull('category_group')
            ->update(['status' => 'archived']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prompt_library_prompts', function (Blueprint $table): void {
            $table->dropIndex(['category_group', 'category']);
            $table->dropColumn(['category_group', 'category']);
        });
        Schema::table('agent_templates', function (Blueprint $table): void {
            $table->dropIndex(['category_group', 'category']);
            $table->dropColumn(['category_group', 'system_prompt']);
        });
    }
};
