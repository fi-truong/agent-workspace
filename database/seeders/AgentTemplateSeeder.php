<?php

namespace Database\Seeders;

use App\Models\AgentTemplate;
use Illuminate\Database\Seeder;

class AgentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Xóa dữ liệu cũ
        AgentTemplate::query()->delete();

        $templates = [
            // ═══════════════════════════════════════════════════════════════════════
            // TEACHING / LESSON PLANNING (8)
            // ════════════════════════════════════════════════════════════════════════
            [
                'icon' => '🗺️',
                'title' => 'Lesson Plan Generator',
                'description' => 'Creates complete lesson plans with objectives, activities, materials, and assessments aligned to standards.',
                'features' => ['All subjects', 'Time-flexible', 'Differentiation options', 'KUD/UBD aligned'],
                'uses_count' => 203,
                'preview_class' => 'science',
                'badge' => 'new',
                'category' => 'teaching',
            ],
            [
                'icon' => '🎯',
                'title' => 'Learning Objective Writer',
                'description' => 'Writes clear, measurable learning objectives using Bloom\'s Taxonomy verbs for any grade and subject.',
                'features' => ['Bloom\'s Taxonomy', 'Measurable outcomes', 'Grade-specific', 'Bilingual EN/VI'],
                'uses_count' => 167,
                'preview_class' => 'english',
                'badge' => 'popular',
                'category' => 'teaching',
            ],
            [
                'icon' => '📅',
                'title' => 'Unit Plan Builder',
                'description' => 'Builds multi-week unit plans with enduring understandings, essential questions, and assessment evidence.',
                'features' => ['UbD framework', 'Cross-curricular links', 'Pacing guide', 'Resource suggestions'],
                'uses_count' => 134,
                'preview_class' => 'math',
                'badge' => 'none',
                'category' => 'teaching',
            ],
            [
                'icon' => '🎭',
                'title' => 'Differentiated Activity Designer',
                'description' => 'Creates tiered activities (support, core, challenge) for the same learning objective.',
                'features' => ['3 tiers', 'Same objective', 'Varied complexity', 'UDL principles'],
                'uses_count' => 112,
                'preview_class' => 'admin',
                'badge' => 'none',
                'category' => 'teaching',
            ],
            [
                'icon' => '🔄',
                'title' => 'Flipped Classroom Planner',
                'description' => 'Designs flipped classroom sequences with pre-class content, in-class activities, and post-class consolidation.',
                'features' => ['Video/content suggestions', 'Active learning tasks', 'Accountability checks', 'Flexible timing'],
                'uses_count' => 89,
                'preview_class' => 'english',
                'badge' => 'none',
                'category' => 'teaching',
            ],
            [
                'icon' => '🌱',
                'title' => 'Project-Based Learning Designer',
                'description' => 'Generates PBL unit outlines with driving question, milestones, authentic products, and public audience.',
                'features' => ['Driving question', 'Sustained inquiry', 'Authentic product', 'Community connections'],
                'uses_count' => 145,
                'preview_class' => 'science',
                'badge' => 'new',
                'category' => 'teaching',
            ],
            [
                'icon' => '📚',
                'title' => 'Interdisciplinary Unit Connector',
                'description' => 'Maps connections between subjects around a shared theme for collaborative teaching teams.',
                'features' => ['Multi-subject mapping', 'Shared assessments', 'Teacher collaboration guide', 'Timeline sync'],
                'uses_count' => 78,
                'preview_class' => 'math',
                'badge' => 'none',
                'category' => 'teaching',
            ],
            [
                'icon' => '⏱️',
                'title' => 'Classroom Routine Optimizer',
                'description' => 'Suggests efficient classroom routines for transitions, material distribution, and time management.',
                'features' => ['Transition scripts', 'Material systems', 'Time-saving tips', 'Grade-appropriate'],
                'uses_count' => 96,
                'preview_class' => 'admin',
                'badge' => 'none',
                'category' => 'teaching',
            ],

            // ════════════════════════════════════════════════════════════════════════
            // ASSESSMENT & FEEDBACK (7)
            // ════════════════════════════════════════════════════════════════════════
            [
                'icon' => '📋',
                'title' => 'Rubric Builder',
                'description' => 'Creates detailed assessment rubrics with criteria, performance levels, and point values for any assignment type.',
                'features' => ['All subjects', 'Customizable scales', 'Standards-aligned', 'Student-friendly language'],
                'uses_count' => 145,
                'preview_class' => '',
                'badge' => 'popular',
                'category' => 'assessment',
            ],
            [
                'icon' => '✅',
                'title' => 'Formative Assessment Generator',
                'description' => 'Generates quick formative checks (exit tickets, hinge questions, concept maps) for any lesson.',
                'features' => ['10+ techniques', 'Instant feedback', 'Misconception alerts', 'Data-ready'],
                'uses_count' => 178,
                'preview_class' => 'english',
                'badge' => 'new',
                'category' => 'assessment',
            ],
            [
                'icon' => '📝',
                'title' => 'Quiz & Test Question Writer',
                'description' => 'Writes multiple choice, short answer, and essay questions at specified cognitive levels.',
                'features' => ['Bloom\'s levels', 'Distractor rationale', 'Answer keys', 'Print-ready'],
                'uses_count' => 201,
                'preview_class' => 'math',
                'badge' => 'none',
                'category' => 'assessment',
            ],
            [
                'icon' => '🎯',
                'title' => 'Success Criteria Co-constructor',
                'description' => 'Helps teachers and students co-construct clear success criteria for any task or project.',
                'features' => ['Student voice', 'Visual exemplars', 'Progress tracking', 'Metacognitive prompts'],
                'uses_count' => 123,
                'preview_class' => 'science',
                'badge' => 'none',
                'category' => 'assessment',
            ],
            [
                'icon' => '💬',
                'title' => 'Feedback Phrase Bank',
                'description' => 'Provides specific, actionable feedback phrases for common student work patterns across subjects.',
                'features' => ['Subject-specific', 'Growth-oriented', 'Sentence starters', 'Bilingual EN/VI'],
                'uses_count' => 156,
                'preview_class' => 'english',
                'badge' => 'none',
                'category' => 'assessment',
            ],
            [
                'icon' => '📈',
                'title' => 'Student Progress Report Writer',
                'description' => 'Drafts narrative progress reports with strengths, growth areas, and next steps for each student.',
                'features' => ['Individualized', 'Evidence-based', 'Parent-friendly', 'Template library'],
                'uses_count' => 98,
                'preview_class' => 'admin',
                'badge' => 'none',
                'category' => 'assessment',
            ],
            [
                'icon' => '🔍',
                'title' => 'Work Sample Analyzer',
                'description' => 'Analyzes student work samples to identify patterns, misconceptions, and instructional next steps.',
                'features' => ['Pattern detection', 'Misconception ID', 'Grouping suggestions', 'Intervention links'],
                'uses_count' => 87,
                'preview_class' => 'math',
                'badge' => 'none',
                'category' => 'assessment',
            ],

            // ════════════════════════════════════════════════════════════════════════
            // COMMUNICATION (6)
            // ════════════════════════════════════════════════════════════════════════
            [
                'icon' => '📧',
                'title' => 'Parent Communication Agent',
                'description' => 'Drafts professional emails to parents in English and Vietnamese. Handles announcements, progress updates, and invitations.',
                'features' => ['Bilingual EN/VI', 'Tone customizable', 'Templates included', 'Cultural sensitivity'],
                'uses_count' => 156,
                'preview_class' => 'admin',
                'badge' => 'new',
                'category' => 'communication',
            ],
            [
                'icon' => '📅',
                'title' => 'Parent Conference Preparer',
                'description' => 'Creates conference agendas, student profiles, talking points, and follow-up plans for parent meetings.',
                'features' => ['Student snapshot', 'Data talking points', 'Goal setting', 'Action plan template'],
                'uses_count' => 134,
                'preview_class' => 'admin',
                'badge' => 'popular',
                'category' => 'communication',
            ],
            [
                'icon' => '📢',
                'title' => 'School Announcement Drafter',
                'description' => 'Writes clear, engaging school announcements for newsletters, assemblies, and digital channels.',
                'features' => ['Multi-channel', 'Audience-aware', 'Bilingual', 'Brand voice consistent'],
                'uses_count' => 112,
                'preview_class' => 'admin',
                'badge' => 'none',
                'category' => 'communication',
            ],
            [
                'icon' => '🤝',
                'title' => 'Conflict Resolution Mediator',
                'description' => 'Guides structured mediation conversations between students, teachers, or parents.',
                'features' => ['Neutral framing', 'Step-by-step script', 'Agreement template', 'Follow-up plan'],
                'uses_count' => 89,
                'preview_class' => 'admin',
                'badge' => 'none',
                'category' => 'communication',
            ],
            [
                'icon' => '📝',
                'title' => 'Meeting Minutes to Action Items',
                'description' => 'Converts raw meeting notes into structured minutes with action items, decisions, and deadline tracking.',
                'features' => ['All departments', 'Action item tracking', 'Email-ready format', 'Owner assignment'],
                'uses_count' => 87,
                'preview_class' => 'admin',
                'badge' => 'none',
                'category' => 'communication',
            ],
            [
                'icon' => '🌐',
                'title' => 'Multilingual Newsletter Builder',
                'description' => 'Creates bilingual (EN/VI) newsletters with sections for curriculum updates, events, and celebrations.',
                'features' => ['Auto-translate check', 'Section templates', 'Image placeholders', 'Accessibility check'],
                'uses_count' => 105,
                'preview_class' => 'english',
                'badge' => 'new',
                'category' => 'communication',
            ],

            // ════════════════════════════════════════════════════════════════════════
            // ADMIN & OPERATIONS (6)
            // ════════════════════════════════════════════════════════════════════════
            [
                'icon' => '📊',
                'title' => 'Data Dashboard Interpreter',
                'description' => 'Helps leaders interpret school data dashboards (attendance, achievement, wellbeing) and identify inquiry questions.',
                'features' => ['Trend detection', 'Equity lenses', 'Question prompts', 'Action planning'],
                'uses_count' => 92,
                'preview_class' => 'admin',
                'badge' => 'popular',
                'category' => 'admin',
            ],
            [
                'icon' => '📋',
                'title' => 'Policy Summary Generator',
                'description' => 'Summarizes lengthy policy documents into clear, parent-friendly or staff-friendly one-pagers.',
                'features' => ['Key points extraction', 'FAQ generation', 'Bilingual', 'Compliance check'],
                'uses_count' => 76,
                'preview_class' => 'admin',
                'badge' => 'none',
                'category' => 'admin',
            ],
            [
                'icon' => '💰',
                'title' => 'Budget Narrative Writer',
                'description' => 'Writes compelling budget justifications linking resources to student outcomes and strategic goals.',
                'features' => ['Outcome-focused', 'Evidence-based', 'Stakeholder-aligned', 'Template library'],
                'uses_count' => 65,
                'preview_class' => 'admin',
                'badge' => 'none',
                'category' => 'admin',
            ],
            [
                'icon' => '📅',
                'title' => 'School Calendar Planner',
                'description' => 'Generates annual school calendars with academic terms, events, assessments, and PD days.',
                'features' => ['Vietnam holidays', 'Assessment windows', 'Event coordination', 'Conflict detection'],
                'uses_count' => 134,
                'preview_class' => 'admin',
                'badge' => 'new',
                'category' => 'admin',
            ],
            [
                'icon' => '👥',
                'title' => 'Staff PD Needs Analyzer',
                'description' => 'Analyzes staff survey data to recommend targeted professional development priorities.',
                'features' => ['Gap analysis', 'Priority ranking', 'Provider matching', 'Impact prediction'],
                'uses_count' => 78,
                'preview_class' => 'admin',
                'badge' => 'none',
                'category' => 'admin',
            ],
            [
                'icon' => '⚠️',
                'title' => 'Risk Assessment Generator',
                'description' => 'Creates comprehensive risk assessments for trips, events, and activities with mitigation strategies.',
                'features' => ['Trip/Event specific', 'Legal compliance', 'Emergency procedures', 'Approval workflow'],
                'uses_count' => 89,
                'preview_class' => 'admin',
                'badge' => 'none',
                'category' => 'admin',
            ],

            // ════════════════════════════════════════════════════════════════════════
            // SUBJECT-SPECIFIC (8)
            // ════════════════════════════════════════════════════════════════════════
            [
                'icon' => '📐',
                'title' => 'Math Problem Generator',
                'description' => 'Generates math problems with solutions at any grade level. Customize difficulty, topic, and number of questions.',
                'features' => ['Grades 6-12', 'Step-by-step solutions', 'Answer key', 'Real-world contexts'],
                'uses_count' => 234,
                'preview_class' => 'math',
                'badge' => 'popular',
                'category' => 'subject',
            ],
            [
                'icon' => '✍️',
                'title' => 'Essay Writing Coach',
                'description' => 'Guides students through essay writing with structure suggestions, thesis development, and revision feedback.',
                'features' => ['All grades', 'Multi-language', 'Rubric aligned', 'Process-focused'],
                'uses_count' => 189,
                'preview_class' => 'english',
                'badge' => 'none',
                'category' => 'subject',
            ],
            [
                'icon' => '🔬',
                'title' => 'Science Lab Report Helper',
                'description' => 'Helps students structure lab reports with hypothesis, methodology, data analysis, and conclusions.',
                'features' => ['Biology/Chemistry/Physics', 'Format guide', 'Self-check', 'CER framework'],
                'uses_count' => 98,
                'preview_class' => 'science',
                'badge' => 'none',
                'category' => 'subject',
            ],
            [
                'icon' => '📖',
                'title' => 'Reading Comprehension Builder',
                'description' => 'Creates comprehension questions, vocabulary lists, and discussion prompts for any reading passage.',
                'features' => ['All grades', 'Multiple question types', 'Answer key', 'Strategy integration'],
                'uses_count' => 112,
                'preview_class' => 'english',
                'badge' => 'none',
                'category' => 'subject',
            ],
            [
                'icon' => '🗣️',
                'title' => 'Speaking Task Designer (IELTS/CEFR)',
                'description' => 'Generates speaking tasks aligned to IELTS or CEFR levels with rubrics and sample responses.',
                'features' => ['IELTS/CEFR aligned', 'Part 1/2/3 tasks', 'Examiner scripts', 'Band descriptors'],
                'uses_count' => 145,
                'preview_class' => 'english',
                'badge' => 'new',
                'category' => 'subject',
            ],
            [
                'icon' => '🧮',
                'title' => 'Math Talk Facilitator',
                'description' => 'Provides talk moves, question stems, and discourse routines for productive mathematical discussions.',
                'features' => ['Talk moves', 'Question stems', 'Discourse norms', 'Video exemplars'],
                'uses_count' => 123,
                'preview_class' => 'math',
                'badge' => 'none',
                'category' => 'subject',
            ],
            [
                'icon' => '🧪',
                'title' => 'Science Inquiry Planner',
                'description' => 'Designs inquiry-based investigations with phenomena, variables, and evidence-based reasoning scaffolds.',
                'features' => ['Phenomena-driven', 'Variable identification', 'CER scaffolds', 'NGSS aligned'],
                'uses_count' => 134,
                'preview_class' => 'science',
                'badge' => 'popular',
                'category' => 'subject',
            ],
            [
                'icon' => '🇻🇳',
                'title' => 'Vietnamese Literature Analyzer',
                'description' => 'Supports analysis of Vietnamese literary texts with cultural context, literary devices, and thematic exploration.',
                'features' => ['Vietnamese canon', 'Cultural context', 'Literary devices', 'Comparative prompts'],
                'uses_count' => 156,
                'preview_class' => '',
                'badge' => 'new',
                'category' => 'subject',
            ],
        ];

        foreach ($templates as $data) {
            $features = $data['features'];
            unset($data['features']);

            $template = AgentTemplate::create($data);

            foreach ($features as $index => $featureText) {
                $template->features()->create([
                    'feature_text' => $featureText,
                    'sort_order' => $index,
                ]);
            }
        }

        $this->command->info('Seeded ' . count($templates) . ' agent templates with features.');
    }
}