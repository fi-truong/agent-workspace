<?php

namespace Database\Seeders;

use App\Models\ShowcasePost;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'department' => 'Math Department',
                'title' => 'Adaptive Math Quiz Generator',
                'description' => 'Creates personalized math quizzes based on student performance. Adjusts difficulty automatically and generates detailed analytics for teachers.',
                'tags' => ['Math', 'Assessment', 'Grades 6-9'],
                'views_count' => 234, 'comments_count' => 18, 'uses_count' => 45,
                'badge' => '🔥 Trending',
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'department' => 'English Department',
                'title' => 'Essay Feedback Assistant',
                'description' => 'Provides constructive feedback on student essays with specific suggestions for improvement. Aligned with IB and Cambridge rubrics.',
                'tags' => ['English', 'Writing', 'IB/Cambridge'],
                'views_count' => 156, 'comments_count' => 12, 'uses_count' => 28,
                'badge' => 'New',
            ],
            [
                'author_email' => 'ha.nguyen@lsts.edu.vn',
                'department' => 'Academic Office',
                'title' => 'Student Report Generator',
                'description' => 'Transforms raw grade data into personalized student reports with qualitative comments. Supports both English and Vietnamese output.',
                'tags' => ['Admin', 'Reporting', 'Bilingual'],
                'views_count' => 312, 'comments_count' => 24, 'uses_count' => 67,
                'badge' => null,
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'department' => 'Science Department',
                'title' => 'Lab Report Structure Guide',
                'description' => 'Interactive guide helping students structure their lab reports properly. Includes templates for hypothesis, methodology, and conclusion sections.',
                'tags' => ['Science', 'Lab Reports', 'All Grades'],
                'views_count' => 198, 'comments_count' => 15, 'uses_count' => 52,
                'badge' => '⭐ Popular',
            ],
            [
                'author_email' => 'mai.tran@lsts.edu.vn',
                'department' => 'Counseling Office',
                'title' => 'University Recommendation Writer',
                'description' => 'Drafts personalized recommendation letters for university applications based on student achievements and teacher input.',
                'tags' => ['Counseling', 'University', 'Recommendations'],
                'views_count' => 89, 'comments_count' => 7, 'uses_count' => 34,
                'badge' => null,
            ],
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'department' => 'Vietnamese Department',
                'title' => 'Vietnamese Literature Analysis',
                'description' => 'Helps students analyze Vietnamese literary works with guided questions on themes, characters, and writing techniques.',
                'tags' => ['Vietnamese', 'Literature', 'Analysis'],
                'views_count' => 67, 'comments_count' => 5, 'uses_count' => 19,
                'badge' => 'New',
            ],
        ];

        // Tag nào thuộc môn học -> subject, còn lại (loại tag mô tả tự do) -> general
        $subjectTags = ['Math', 'English', 'Science', 'Admin', 'Counseling', 'Vietnamese'];

        foreach ($posts as $data) {
            $author = User::where('email', $data['author_email'])->first();
            if (! $author) {
                continue;
            }

            $post = ShowcasePost::create([
                'author_id' => $author->id,
                'department' => $data['department'],
                'title' => $data['title'],
                'description' => $data['description'],
                'views_count' => $data['views_count'],
                'comments_count' => $data['comments_count'],
                'uses_count' => $data['uses_count'],
                'badge' => $data['badge'],
            ]);

            $tagIds = [];
            foreach ($data['tags'] as $tagName) {
                $category = in_array($tagName, $subjectTags) ? 'subject' : 'general';
                $tag = Tag::firstOrCreate(['name' => $tagName], ['category' => $category]);
                $tagIds[] = $tag->id;
            }
            $post->tags()->sync($tagIds);
        }
    }
}
