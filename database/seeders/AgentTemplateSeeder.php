<?php

namespace Database\Seeders;

use App\Models\AgentTemplate;
use Illuminate\Database\Seeder;

class AgentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'icon' => '📐',
                'title' => 'Math Problem Generator',
                'description' => 'Generates math problems with solutions at any grade level. Customize difficulty, topic, and number of questions.',
                'features' => ['Grades 6-12', 'Step-by-step solutions', 'Answer key'],
                'uses_count' => 234,
                'preview_class' => 'math',
                'badge' => 'popular',
            ],
            [
                'icon' => '✍️',
                'title' => 'Essay Writing Coach',
                'description' => 'Guides students through essay writing with structure suggestions, thesis development, and revision feedback.',
                'features' => ['All grades', 'Multi-language', 'Rubric aligned'],
                'uses_count' => 189,
                'preview_class' => 'english',
                'badge' => 'none',
            ],
            [
                'icon' => '📧',
                'title' => 'Parent Communication Agent',
                'description' => 'Drafts professional emails to parents in English and Vietnamese. Handles announcements, progress updates, and invitations.',
                'features' => ['Bilingual EN/VI', 'Tone customizable', 'Templates included'],
                'uses_count' => 156,
                'preview_class' => 'admin',
                'badge' => 'new',
            ],
            [
                'icon' => '🔬',
                'title' => 'Science Lab Report Helper',
                'description' => 'Helps students structure lab reports with hypothesis, methodology, data analysis, and conclusions.',
                'features' => ['Biology/Chemistry/Physics', 'Format guide', 'Self-check'],
                'uses_count' => 98,
                'preview_class' => 'science',
                'badge' => 'none',
            ],
            [
                'icon' => '📋',
                'title' => 'Rubric Builder',
                'description' => 'Creates detailed assessment rubrics with criteria, performance levels, and point values for any assignment type.',
                'features' => ['All subjects', 'Customizable scales', 'Standards-aligned'],
                'uses_count' => 145,
                'preview_class' => '',
                'badge' => 'none',
            ],
            [
                'icon' => '📊',
                'title' => 'Meeting Notes to Minutes',
                'description' => 'Converts raw meeting notes into structured minutes with action items, decisions, and deadline tracking.',
                'features' => ['All departments', 'Action item tracking', 'Email-ready format'],
                'uses_count' => 87,
                'preview_class' => 'admin',
                'badge' => 'none',
            ],
            [
                'icon' => '📖',
                'title' => 'Reading Comprehension Builder',
                'description' => 'Creates comprehension questions, vocabulary lists, and discussion prompts for any reading passage.',
                'features' => ['All grades', 'Multiple question types', 'Answer key'],
                'uses_count' => 112,
                'preview_class' => 'english',
                'badge' => 'none',
            ],
            [
                'icon' => '🗺️',
                'title' => 'Lesson Plan Generator',
                'description' => 'Creates complete lesson plans with objectives, activities, materials, and assessments aligned to standards.',
                'features' => ['All subjects', 'Time-flexible', 'Differentiation options'],
                'uses_count' => 203,
                'preview_class' => 'science',
                'badge' => 'new',
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
    }
}
