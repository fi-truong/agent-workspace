<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AgentTemplateController extends Controller
{
    public function index()
    {
        $templates = [
            [
                'id' => 1,
                'icon' => '📐',
                'title' => 'Math Problem Generator',
                'description' => 'Generates math problems with solutions at any grade level. Customize difficulty, topic, and number of questions.',
                'features' => ['Grades 6-12', 'Step-by-step solutions', 'Answer key'],
                'uses' => 234,
                'preview_class' => 'math',
                'badge' => 'Popular',
            ],
            [
                'id' => 2,
                'icon' => '✍️',
                'title' => 'Essay Writing Coach',
                'description' => 'Guides students through essay writing with structure suggestions, thesis development, and revision feedback.',
                'features' => ['All grades', 'Multi-language', 'Rubric aligned'],
                'uses' => 189,
                'preview_class' => 'english',
                'badge' => null,
            ],
            [
                'id' => 3,
                'icon' => '📧',
                'title' => 'Parent Communication Agent',
                'description' => 'Drafts professional emails to parents in English and Vietnamese. Handles announcements, progress updates, and invitations.',
                'features' => ['Bilingual EN/VI', 'Tone customizable', 'Templates included'],
                'uses' => 156,
                'preview_class' => 'admin',
                'badge' => 'New',
            ],
            [
                'id' => 4,
                'icon' => '🔬',
                'title' => 'Science Lab Report Helper',
                'description' => 'Helps students structure lab reports with hypothesis, methodology, data analysis, and conclusions.',
                'features' => ['Biology/Chemistry/Physics', 'Format guide', 'Self-check'],
                'uses' => 98,
                'preview_class' => 'science',
                'badge' => null,
            ],
            [
                'id' => 5,
                'icon' => '📋',
                'title' => 'Rubric Builder',
                'description' => 'Creates detailed assessment rubrics with criteria, performance levels, and point values for any assignment type.',
                'features' => ['All subjects', 'Customizable scales', 'Standards-aligned'],
                'uses' => 145,
                'preview_class' => '',
                'badge' => null,
            ],
            [
                'id' => 6,
                'icon' => '📊',
                'title' => 'Meeting Notes to Minutes',
                'description' => 'Converts raw meeting notes into structured minutes with action items, decisions, and deadline tracking.',
                'features' => ['All departments', 'Action item tracking', 'Email-ready format'],
                'uses' => 87,
                'preview_class' => 'admin',
                'badge' => null,
            ],
            [
                'id' => 7,
                'icon' => '📖',
                'title' => 'Reading Comprehension Builder',
                'description' => 'Creates comprehension questions, vocabulary lists, and discussion prompts for any reading passage.',
                'features' => ['All grades', 'Multiple question types', 'Answer key'],
                'uses' => 112,
                'preview_class' => 'english',
                'badge' => null,
            ],
            [
                'id' => 8,
                'icon' => '🗺️',
                'title' => 'Lesson Plan Generator',
                'description' => 'Creates complete lesson plans with objectives, activities, materials, and assessments aligned to standards.',
                'features' => ['All subjects', 'Time-flexible', 'Differentiation options'],
                'uses' => 203,
                'preview_class' => 'science',
                'badge' => 'New',
            ],
        ];

        return view('ai-plus.agent-templates.index', [
            'templates' => $templates,
            'viewingAs' => 'Teacher / Staff',
        ]);
    }
}
