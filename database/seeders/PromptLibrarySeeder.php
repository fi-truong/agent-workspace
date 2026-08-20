<?php

namespace Database\Seeders;

use App\Models\PromptLibraryPrompt;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class PromptLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $prompts = [
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Email to Parents Generator',
                'description' => 'Generate professional, warm emails to parents about school events, student progress, or announcements in English and Vietnamese.',
                'preview_text' => 'You are a helpful school administrator. Write an email to parents about [TOPIC]. The email should be warm, professional...',
                'uses_count' => 234,
                'tags' => [['name' => 'Admin', 'category' => 'role'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'Math Problem Generator',
                'description' => 'Create math problems at specific difficulty levels for any grade, with step-by-step solutions and answer keys.',
                'preview_text' => 'Generate [NUMBER] math problems for Grade [LEVEL] on the topic of [TOPIC]. Each problem should...',
                'uses_count' => 189,
                'tags' => [['name' => 'Math', 'category' => 'subject']],
            ],
            [
                'author_email' => 'ngoc.tran@lsts.edu.vn',
                'title' => 'Lesson Plan Creator',
                'description' => 'Create detailed lesson plans with objectives, activities, materials needed, and assessment methods aligned to standards.',
                'preview_text' => 'Create a lesson plan for [SUBJECT], Grade [LEVEL], duration [MINUTES] minutes...',
                'uses_count' => 156,
                'tags' => [['name' => 'General', 'category' => 'subject']],
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'Essay Rubric Builder',
                'description' => 'Generate detailed rubrics for essay assignments with criteria, descriptions, and point values.',
                'preview_text' => 'Create a rubric for a [TYPE] essay assignment for Grade [LEVEL]...',
                'uses_count' => 145,
                'tags' => [['name' => 'English', 'category' => 'subject']],
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'Science Lab Report Template',
                'description' => 'Help students structure their lab reports with proper scientific format including hypothesis, procedure, data, and conclusions.',
                'preview_text' => 'You are a science teacher helping a student write a lab report...',
                'uses_count' => 98,
                'tags' => [['name' => 'Science', 'category' => 'subject'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'ha.nguyen@lsts.edu.vn',
                'title' => 'Meeting Minutes Summarizer',
                'description' => 'Transform raw meeting notes into structured minutes with action items, decisions, and owners.',
                'preview_text' => 'Convert the following meeting notes into structured meeting minutes...',
                'uses_count' => 87,
                'tags' => [['name' => 'Admin', 'category' => 'role']],
            ],
        ];

        foreach ($prompts as $data) {
            $author = User::where('email', $data['author_email'])->first();
            if (! $author) {
                continue; // Chưa seed User thì bỏ qua — chạy UserSeeder trước
            }

            $prompt = PromptLibraryPrompt::create([
                'author_id' => $author->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'preview_text' => $data['preview_text'],
                'uses_count' => $data['uses_count'],
            ]);

            $tagIds = [];
            foreach ($data['tags'] as $tagData) {
                $tag = Tag::firstOrCreate(['name' => $tagData['name']], ['category' => $tagData['category']]);
                $tagIds[] = $tag->id;
            }
            $prompt->tags()->sync($tagIds);
        }
    }
}
