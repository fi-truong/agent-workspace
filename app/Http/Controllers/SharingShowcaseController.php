<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SharingShowcaseController extends Controller
{
    public function index()
    {
        $showcases = [
            [
                'id' => 1,
                'author' => 'Toan Huynh',
                'authorInitials' => 'TH',
                'department' => 'Math Department',
                'title' => 'Adaptive Math Quiz Generator',
                'description' => 'Creates personalized math quizzes based on student performance. Adjusts difficulty automatically and generates detailed analytics for teachers.',
                'tags' => ['Math', 'Assessment', 'Grades 6-9'],
                'views' => 234,
                'comments' => 18,
                'uses' => 45,
                'badge' => '🔥 Trending',
            ],
            [
                'id' => 2,
                'author' => 'Lan Hoang',
                'authorInitials' => 'LH',
                'department' => 'English Department',
                'title' => 'Essay Feedback Assistant',
                'description' => 'Provides constructive feedback on student essays with specific suggestions for improvement. Aligned with IB and Cambridge rubrics.',
                'tags' => ['English', 'Writing', 'IB/Cambridge'],
                'views' => 156,
                'comments' => 12,
                'uses' => 28,
                'badge' => 'New',
            ],
            [
                'id' => 3,
                'author' => 'Ha Nguyen',
                'authorInitials' => 'HN',
                'department' => 'Academic Office',
                'title' => 'Student Report Generator',
                'description' => 'Transforms raw grade data into personalized student reports with qualitative comments. Supports both English and Vietnamese output.',
                'tags' => ['Admin', 'Reporting', 'Bilingual'],
                'views' => 312,
                'comments' => 24,
                'uses' => 67,
                'badge' => null,
            ],
            [
                'id' => 4,
                'author' => 'Dung Vu',
                'authorInitials' => 'DV',
                'department' => 'Science Department',
                'title' => 'Lab Report Structure Guide',
                'description' => 'Interactive guide helping students structure their lab reports properly. Includes templates for hypothesis, methodology, and conclusion sections.',
                'tags' => ['Science', 'Lab Reports', 'All Grades'],
                'views' => 198,
                'comments' => 15,
                'uses' => 52,
                'badge' => '⭐ Popular',
            ],
            [
                'id' => 5,
                'author' => 'Mai Tran',
                'authorInitials' => 'MT',
                'department' => 'Counseling Office',
                'title' => 'University Recommendation Writer',
                'description' => 'Drafts personalized recommendation letters for university applications based on student achievements and teacher input.',
                'tags' => ['Counseling', 'University', 'Recommendations'],
                'views' => 89,
                'comments' => 7,
                'uses' => 34,
                'badge' => null,
            ],
            [
                'id' => 6,
                'author' => 'Chi Tran',
                'authorInitials' => 'CT',
                'department' => 'Vietnamese Department',
                'title' => 'Vietnamese Literature Analysis',
                'description' => 'Helps students analyze Vietnamese literary works with guided questions on themes, characters, and writing techniques.',
                'tags' => ['Vietnamese', 'Literature', 'Analysis'],
                'views' => 67,
                'comments' => 5,
                'uses' => 19,
                'badge' => 'New',
            ],
        ];

        return view('ai-plus.sharing-showcase.index', [
            'showcases' => $showcases,
            'viewingAs' => 'Teacher / Staff',
            'totalAgents' => 47,
            'totalDepartments' => 12,
            'totalComments' => 156,
        ]);
    }
}
