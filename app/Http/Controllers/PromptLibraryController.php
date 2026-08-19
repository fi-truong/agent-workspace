<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PromptLibraryController extends Controller
{
    public function index()
    {
        $prompts = [
            [
                'id' => 1,
                'icon' => 'PL',
                'title' => 'Email to Parents Generator',
                'description' => 'Generate professional, warm emails to parents about school events, student progress, or announcements in English and Vietnamese.',
                'preview' => 'You are a helpful school administrator. Write an email to parents about [TOPIC]. The email should be warm, professional...',
                'author' => 'Fi Truong',
                'authorInitials' => 'FT',
                'badges' => ['Admin', 'New'],
                'uses' => 234,
            ],
            [
                'id' => 2,
                'icon' => 'PL',
                'title' => 'Math Problem Generator',
                'description' => 'Create math problems at specific difficulty levels for any grade, with step-by-step solutions and answer keys.',
                'preview' => 'Generate [NUMBER] math problems for Grade [LEVEL] on the topic of [TOPIC]. Each problem should...',
                'author' => 'Toan Huynh',
                'authorInitials' => 'TH',
                'badges' => ['Math'],
                'uses' => 189,
            ],
            [
                'id' => 3,
                'icon' => 'PL',
                'title' => 'Lesson Plan Creator',
                'description' => 'Create detailed lesson plans with objectives, activities, materials needed, and assessment methods aligned to standards.',
                'preview' => 'Create a lesson plan for [SUBJECT], Grade [LEVEL], duration [MINUTES] minutes...',
                'author' => 'Ngoc Tran',
                'authorInitials' => 'NT',
                'badges' => ['General'],
                'uses' => 156,
            ],
            [
                'id' => 4,
                'icon' => 'PL',
                'title' => 'Essay Rubric Builder',
                'description' => 'Generate detailed rubrics for essay assignments with criteria, descriptions, and point values.',
                'preview' => 'Create a rubric for a [TYPE] essay assignment for Grade [LEVEL]...',
                'author' => 'Lan Hoang',
                'authorInitials' => 'LH',
                'badges' => ['English'],
                'uses' => 145,
            ],
            [
                'id' => 5,
                'icon' => 'PL',
                'title' => 'Science Lab Report Template',
                'description' => 'Help students structure their lab reports with proper scientific format including hypothesis, procedure, data, and conclusions.',
                'preview' => 'You are a science teacher helping a student write a lab report...',
                'author' => 'Dung Vu',
                'authorInitials' => 'DV',
                'badges' => ['Science', 'New'],
                'uses' => 98,
            ],
            [
                'id' => 6,
                'icon' => 'PL',
                'title' => 'Meeting Minutes Summarizer',
                'description' => 'Transform raw meeting notes into structured minutes with action items, decisions, and owners.',
                'preview' => 'Convert the following meeting notes into structured meeting minutes...',
                'author' => 'Ha Nguyen',
                'authorInitials' => 'HN',
                'badges' => ['Admin'],
                'uses' => 87,
            ],
        ];

        return view('ai-plus.prompt-library.index', [
            'prompts' => $prompts,
            'viewingAs' => 'Teacher / Staff',
            'totalPrompts' => 127,
            'totalSubjects' => 8,
            'totalContributors' => 45,
        ]);
    }
}
