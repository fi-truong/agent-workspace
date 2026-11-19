<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class AgentCategories
{
    public const GROUPS = AiPlusCatalogCategories::GROUPS;

    public const CATEGORIES = [
        'teaching' => [
            'lesson_planning' => 'Lesson Planning',
            'teaching_materials' => 'Teaching Materials & Classroom Activities',
            'assessment_feedback' => 'Assessment & Feedback',
            'personalized_learning' => 'Personalized Learning',
            'student_progress' => 'Student Progress Tracking',
            'educational_research' => 'Educational Research',
            'other' => 'Other',
        ],
        'school_operations' => [
            'hr_staff_support' => 'HR & Staff Support',
            'budgeting_procurement' => 'Budgeting & Procurement',
            'academic_scheduling' => 'Academic Scheduling & Coordination',
            'communications_events' => 'Communications & Events',
            'surveys_forms' => 'Surveys & Forms',
            'data_reporting' => 'Data Analysis & Reporting',
            'policies_procedures' => 'School Policies & Procedures',
            'facilities_safety_assets' => 'Facilities, Safety & Assets',
            'other' => 'Other',
        ],
    ];

    public static function contains(?string $group, ?string $category): bool
    {
        return isset(self::CATEGORIES[$group][$category]);
    }

    /** @return array<string, list<mixed>> */
    public static function rules(mixed $group, bool $required): array
    {
        $categories = is_string($group) ? self::CATEGORIES[$group] ?? [] : [];
        $presence = $required ? 'required' : 'nullable';

        return [
            'category_group' => [$presence, 'required_with:category', 'string', Rule::in(array_keys(self::GROUPS))],
            'category' => [$presence, 'required_with:category_group', 'string', Rule::in(array_keys($categories))],
        ];
    }

    public static function templateCategory(?string $group, ?string $category): ?string
    {
        if (self::contains($group, $category)) {
            return $category;
        }

        return match ($group.':'.$category) {
            'teaching:instruction_differentiation' => 'personalized_learning',
            'school_operations:communication' => 'communications_events',
            default => null,
        };
    }
}
