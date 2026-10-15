<?php

namespace App\Support;

final class AiPlusCatalogCategories
{
    /** @var array<string, string> */
    public const GROUPS = [
        'teaching' => 'Teaching',
        'school_operations' => 'School Operations',
    ];

    /** @var array<string, array<string, string>> */
    public const CATEGORIES = [
        'teaching' => [
            'lesson_planning' => 'Lesson Planning',
            'instruction_differentiation' => 'Instruction & Differentiation',
            'assessment_feedback' => 'Assessment & Feedback',
            'subject_specific' => 'Subject-Specific',
            'student_support' => 'Student Support & Wellbeing',
        ],
        'school_operations' => [
            'communication' => 'Communication',
            'administration_operations' => 'Administration & Operations',
            'events_planning' => 'Events & Planning',
            'data_reporting' => 'Data & Reporting',
        ],
    ];

    /** @return array<string, string> */
    public static function allCategories(): array
    {
        return array_merge(...array_values(self::CATEGORIES));
    }

    public static function contains(string $group, string $category): bool
    {
        return isset(self::CATEGORIES[$group][$category]);
    }
}
