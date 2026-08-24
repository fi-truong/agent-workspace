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
        // Danh sách tác giả demo (phải có trong UserSeeder)
        $authors = [
            'fi.truong@lsts.edu.vn',
            'toan.huynh@lsts.edu.vn',
            'ngoc.tran@lsts.edu.vn',
            'lan.hoang@lsts.edu.vn',
            'dung.vu@lsts.edu.vn',
            'ha.nguyen@lsts.edu.vn',
            'mai.tran@lsts.edu.vn',
            'chi.tran@lsts.edu.vn',
        ];

        // Helper: lấy author_id ngẫu nhiên hoặc cụ thể
        $getAuthor = function ($email) use ($authors) {
            $user = User::where('email', $email)->first();
            return $user ? $user->id : null;
        };

        $prompts = [

            // ═══════════════════════════════════════════════════════════════════════════
            // ADMIN / OPERATIONS (10)
            // ═══════════════════════════════════════════════════════════════════════════
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Email to Parents Generator',
                'description' => 'Generate professional, warm emails to parents about school events, student progress, or announcements in English and Vietnamese.',
                'preview_text' => 'You are a helpful school administrator at a K-12 bilingual school. Write an email to parents about [TOPIC]. The email should be warm, professional, and bilingual (English + Vietnamese)...',
                'uses_count' => 234,
                'tags' => [['name' => 'Admin', 'category' => 'subject'], ['name' => 'Admin', 'category' => 'role'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'ha.nguyen@lsts.edu.vn',
                'title' => 'Meeting Minutes Summarizer',
                'description' => 'Transform raw meeting notes into structured minutes with action items, decisions, and owners.',
                'preview_text' => 'Convert the following meeting notes into structured meeting minutes with: (1) Attendees, (2) Key Decisions, (3) Action Items (owner + deadline), (4) Next Steps...',
                'uses_count' => 87,
                'tags' => [['name' => 'Admin', 'category' => 'subject'], ['name' => 'Admin', 'category' => 'role']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Policy Brief Generator',
                'description' => 'Tóm tắt chính sách/mục tiêu trường thành brief 1 trang cho ban giám hiệu/hội đồng.',
                'preview_text' => 'Summarize the attached policy document into a 1-page executive brief for school leadership, with: Context, Key Changes, Impact, Recommendations...',
                'uses_count' => 54,
                'tags' => [['name' => 'Admin', 'category' => 'subject'], ['name' => 'Admin', 'category' => 'role']],
            ],
            [
                'author_email' => 'ha.nguyen@lsts.edu.vn',
                'title' => 'School Event Planner Checklist',
                'description' => 'Checklist đầy đủ cho sự kiện trường: logistics, communication, safety, follow-up.',
                'preview_text' => 'Create a comprehensive event planning checklist for [EVENT TYPE] at a K-12 bilingual school, grouped by: 4 weeks prior, 1 week prior, Day-of, Post-event...',
                'uses_count' => 72,
                'tags' => [['name' => 'Admin', 'category' => 'subject'], ['name' => 'Admin', 'category' => 'role'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'ha.nguyen@lsts.edu.vn',
                'title' => 'Incident Report Template',
                'description' => 'Mẫu báo cáo sự cố an toàn/hành vi học sinh chuẩn hóa.',
                'preview_text' => 'Generate a standardized incident report template for student behavioral/safety incidents with sections: Date/Time, Location, Persons Involved, Description, Immediate Action, Follow-up...',
                'uses_count' => 41,
                'tags' => [['name' => 'Admin', 'category' => 'subject'], ['name' => 'Admin', 'category' => 'role']],
            ],
            [
                'author_email' => 'ha.nguyen@lsts.edu.vn',
                'title' => 'Newsletter Content Generator',
                'description' => 'Tạo nội dung newsletter hàng tuần/tháng cho phụ huynh & staff.',
                'preview_text' => 'Write a school newsletter section about [TOPIC] for parents and staff. Bilingual EN/VI, friendly tone, 150-200 words, include 1 call-to-action...',
                'uses_count' => 63,
                'tags' => [['name' => 'Admin', 'category' => 'subject'], ['name' => 'Admin', 'category' => 'role']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Budget Request Justification',
                'description' => 'Viết thư trình bày ngân sách cho dự án/thiết bị trường học.',
                'preview_text' => 'Draft a budget justification memo for [ITEM/PROJECT] requesting [AMOUNT] VND. Structure: Need, Cost Breakdown, Expected Impact, Alternatives Considered...',
                'uses_count' => 29,
                'tags' => [['name' => 'Admin', 'category' => 'subject'], ['name' => 'Admin', 'category' => 'role']],
            ],
            [
                'author_email' => 'ha.nguyen@lsts.edu.vn',
                'title' => 'Parent Complaint Response Drafter',
                'description' => 'Soạn phản hồi chuyên nghiệp, thấu cảm cho khiếu nại của phụ huynh.',
                'preview_text' => 'Draft a professional, empathetic response to the following parent complaint. Acknowledge concern, explain action taken, propose follow-up. Bilingual tone...',
                'uses_count' => 38,
                'tags' => [['name' => 'Admin', 'category' => 'subject'], ['name' => 'Admin', 'category' => 'role']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Staff Onboarding Welcome Kit',
                'description' => 'Tạo gói chào mừng & checklist onboarding cho nhân viên mới.',
                'preview_text' => 'Create a staff onboarding welcome kit for a new [ROLE] at LSTS: Day-1 checklist, first-week goals, key contacts, school culture notes...',
                'uses_count' => 22,
                'tags' => [['name' => 'Admin', 'category' => 'subject'], ['name' => 'Admin', 'category' => 'role'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'ha.nguyen@lsts.edu.vn',
                'title' => 'Weekly Update to Leadership',
                'description' => 'Tóm tắt tuần làm việc thành báo cáo ngắn gọn cho ban giám hiệu.',
                'preview_text' => 'Summarize this week\'s activities into a leadership update: Wins, Challenges, Metrics, Next Week Priorities. Max 250 words, bullet format...',
                'uses_count' => 31,
                'tags' => [['name' => 'Admin', 'category' => 'subject'], ['name' => 'Admin', 'category' => 'role']],
            ],

            // ═══════════════════════════════════════════════════════════════════════════
            // TEACHER TOOLS / PEDAGOGY (10)
            // ═══════════════════════════════════════════════════════════════════════════
            [
                'author_email' => 'ngoc.tran@lsts.edu.vn',
                'title' => 'Lesson Plan Creator',
                'description' => 'Create detailed lesson plans with objectives, activities, materials needed, and assessment methods aligned to standards.',
                'preview_text' => 'Create a lesson plan for [SUBJECT], Grade [LEVEL], duration [MINUTES] minutes. Include: Objectives, Materials, Procedure (5E model), Assessment, Differentiation...',
                'uses_count' => 156,
                'tags' => [['name' => 'General', 'category' => 'subject']],
            ],
            [
                'author_email' => 'ngoc.tran@lsts.edu.vn',
                'title' => 'Bilingual Lesson Plan (VN/EN)',
                'description' => 'Giáo án song ngữ: content song ngữ, language objectives, scaffolding cho ELL.',
                'preview_text' => 'Create a bilingual lesson plan for [SUBJECT] with: Vietnamese & English language objectives, key vocabulary, scaffolding for ELL students, assessment in both languages...',
                'uses_count' => 142,
                'tags' => [['name' => 'General', 'category' => 'subject'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'ngoc.tran@lsts.edu.vn',
                'title' => 'Differentiated Instruction Planner',
                'description' => 'Kế hoạch phân biệt hướng dẫn theo readiness, interest, learning profile.',
                'preview_text' => 'Design differentiated activities for [TOPIC] at 3 tiers: Support (scaffolded), Core (grade-level), Extension (enrichment). Include materials and success criteria...',
                'uses_count' => 98,
                'tags' => [['name' => 'General', 'category' => 'subject']],
            ],
            [
                'author_email' => 'ngoc.tran@lsts.edu.vn',
                'title' => 'Formative Assessment Designer',
                'description' => 'Tạo bài kiểm tra quá trình: exit tickets, quizzes, observations, rubrics.',
                'preview_text' => 'Create 5 formative assessment strategies for [TOPIC] with examples and scoring guides: Exit Ticket, Think-Pair-Share, Quiz, Observation Checklist, One-Sentence Summary...',
                'uses_count' => 76,
                'tags' => [['name' => 'General', 'category' => 'subject']],
            ],
            [
                'author_email' => 'ngoc.tran@lsts.edu.vn',
                'title' => 'Parent-Teacher Conference Prep',
                'description' => 'Chuẩn bị hội thảo phụ huynh: talking points, data, goals, action plan.',
                'preview_text' => 'Prepare talking points for parent-teacher conference for student [NAME]: Strengths, Growth Areas (data-backed), Goals, Action Plan. Empathetic, bilingual tone...',
                'uses_count' => 67,
                'tags' => [['name' => 'General', 'category' => 'subject']],
            ],
            [
                'author_email' => 'ngoc.tran@lsts.edu.vn',
                'title' => 'Substitute Teacher Packet',
                'description' => 'Gói tài liệu cho giáo viên thay: schedule, procedures, emergency, lesson notes.',
                'preview_text' => 'Create a substitute teacher packet for [SUBJECT/GRADE] with: Daily Schedule, Class Procedures, Emergency Protocol, Lesson Notes, Student Roster, Behavior Notes...',
                'uses_count' => 54,
                'tags' => [['name' => 'General', 'category' => 'subject']],
            ],
            [
                'author_email' => 'ngoc.tran@lsts.edu.vn',
                'title' => 'Exit Ticket Generator',
                'description' => 'Tạo exit tickets nhanh cho kết thúc bài học.',
                'preview_text' => 'Generate 3 exit ticket questions for [TOPIC] lesson: 1 recall, 1 apply, 1 reflect. Include answer key and rubric for quick grading...',
                'uses_count' => 45,
                'tags' => [['name' => 'General', 'category' => 'subject']],
            ],
            [
                'author_email' => 'ngoc.tran@lsts.edu.vn',
                'title' => 'Classroom Discussion Question Bank',
                'description' => 'Ngân hàng câu hỏi thảo luận theo Bloom\'s Taxonomy.',
                'preview_text' => 'Generate 10 discussion questions for [TOPIC] across Bloom\'s levels: Remember, Understand, Apply, Analyze, Evaluate, Create. Include facilitation prompts...',
                'uses_count' => 39,
                'tags' => [['name' => 'General', 'category' => 'subject']],
            ],
            [
                'author_email' => 'ngoc.tran@lsts.edu.vn',
                'title' => 'Unit Plan Template (KUD-aligned)',
                'description' => 'Mẫu unit plan theo khung KUD (Know, Understand, Do) của trường.',
                'preview_text' => 'Design a unit plan for [SUBJECT] Grade [LEVEL] using LSTS KUD model: KNOW (facts/skills), UNDERSTAND (big ideas), DO (transfer tasks). Include assessments...',
                'uses_count' => 61,
                'tags' => [['name' => 'General', 'category' => 'subject'], ['name' => 'KUD', 'category' => 'framework']],
            ],
            [
                'author_email' => 'ngoc.tran@lsts.edu.vn',
                'title' => 'Homework Assignment Designer',
                'description' => 'Thiết kế bài tập về nhà cân bằng, có mục đích rõ ràng.',
                'preview_text' => 'Create homework for [TOPIC] Grade [LEVEL]: 3 problems (easy/medium/hard), purpose statement, est. time 20 min, bilingual instructions...',
                'uses_count' => 33,
                'tags' => [['name' => 'General', 'category' => 'subject']],
            ],

            // ═══════════════════════════════════════════════════════════════════════════
            // MATH (10 + KUD)
            // ═══════════════════════════════════════════════════════════════════════════
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'Math Problem Generator',
                'description' => 'Create math problems at specific difficulty levels for any grade, with step-by-step solutions and answer keys.',
                'preview_text' => 'Generate [NUMBER] math problems for Grade [LEVEL] on the topic of [TOPIC]. Each problem should include: question, step-by-step solution, answer key...',
                'uses_count' => 189,
                'tags' => [['name' => 'Math', 'category' => 'subject']],
            ],
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'Math Word Problems (Real-world VN)',
                'description' => 'Bài toán thực tế liên quan đời sống Việt Nam, STEAM context.',
                'preview_text' => 'Create contextual math word problems for Grade [LEVEL] using Vietnam/SEA real-world scenarios (market, traffic, weather, festivals). Show worked solutions...',
                'uses_count' => 124,
                'tags' => [['name' => 'Math', 'category' => 'subject'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'Geometry Proof Explainer',
                'description' => 'Giải thích chứng minh hình học từng bước, dễ hiểu.',
                'preview_text' => 'Explain the proof of [THEOREM] step-by-step for Grade [LEVEL]: Given, To Prove, Construction, Logical Steps, Conclusion. Use simple language + diagrams description...',
                'uses_count' => 88,
                'tags' => [['name' => 'Math', 'category' => 'subject']],
            ],
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'Math Misconception Diagnostic',
                'description' => 'Chẩn đoán và sửa lỗi hiểu sai thường gặp của học sinh.',
                'preview_text' => 'Student answered [ANSWER] for [PROBLEM]. Identify the misconception, explain the correct approach, give 2 similar practice problems to reinforce...',
                'uses_count' => 57,
                'tags' => [['name' => 'Math', 'category' => 'subject']],
            ],
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'Algebra Equation Solver & Tutor',
                'description' => 'Giải phương trình đại số kèm hướng dẫn từng bước.',
                'preview_text' => 'Solve [EQUATION] for [VARIABLE]. Show each algebraic step with explanation. Then create 3 practice equations of increasing difficulty...',
                'uses_count' => 112,
                'tags' => [['name' => 'Math', 'category' => 'subject']],
            ],
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'Statistics & Probability Worksheet',
                'description' => 'Tạo bài tập thống kê/xác suất từ dữ liệu thực.',
                'preview_text' => 'Create a statistics worksheet from this dataset: [DATA]. Include: mean/median/mode, range, simple probability, 1 graph interpretation question. Answer key...',
                'uses_count' => 49,
                'tags' => [['name' => 'Math', 'category' => 'subject']],
            ],
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'Mental Math Strategies',
                'description' => 'Chiến thuật tính nhẩm nhanh cho học sinh tiểu học/THCS.',
                'preview_text' => 'Teach mental math strategy for [OPERATION] (e.g., friendly numbers, compensation). Give 5 example problems with think-aloud solutions...',
                'uses_count' => 38,
                'tags' => [['name' => 'Math', 'category' => 'subject']],
            ],
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'Math Project Ideas (PBL)',
                'description' => 'Ý tưởng dự án toán học thực tế cho PBL.',
                'preview_text' => 'Suggest 5 math PBL projects for Grade [LEVEL] connecting to real life (budgeting, architecture, sports stats). Include driving question + deliverable...',
                'uses_count' => 44,
                'tags' => [['name' => 'Math', 'category' => 'subject'], ['name' => 'STEAM', 'category' => 'subject']],
            ],
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'Calculus Concept Explainer',
                'description' => 'Giải thích khái niệm giải tích (giới hạn, đạo hàm, tích phân) trực quan.',
                'preview_text' => 'Explain [CALCULUS CONCEPT] visually for Grade 12: intuition, formal definition, 2 worked examples, 1 real-world application (physics/economics)...',
                'uses_count' => 36,
                'tags' => [['name' => 'Math', 'category' => 'subject']],
            ],
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'Math Quiz Generator (Auto-grade)',
                'description' => 'Tạo quiz toán tự động có đáp án và thang điểm.',
                'preview_text' => 'Generate a 10-question math quiz for [TOPIC] Grade [LEVEL] with mixed formats (MCQ, short answer, show-work). Include answer key + 4-point rubric...',
                'uses_count' => 71,
                'tags' => [['name' => 'Math', 'category' => 'subject']],
            ],
            [
                'author_email' => 'toan.huynh@lsts.edu.vn',
                'title' => 'KUD Math Unit Planner',
                'description' => 'Thiết kế unit toán theo khung KUD: Know (công thức), Understand (nguyên lý), Do (giải quyết vấn đề).',
                'preview_text' => 'Design a math unit for [TOPIC] Grade [LEVEL] using KUD: KNOW (formulas/procedures), UNDERSTAND (why they work, connections), DO (solve novel problems, model real situations). Map to assessments...',
                'uses_count' => 52,
                'tags' => [['name' => 'Math', 'category' => 'subject'], ['name' => 'KUD', 'category' => 'framework']],
            ],

            // ═══════════════════════════════════════════════════════════════════════════
            // SCIENCE (10 + KUD)
            // ═══════════════════════════════════════════════════════════════════════════
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'Science Lab Report Template',
                'description' => 'Help students structure their lab reports with proper scientific format including hypothesis, procedure, data, and conclusions.',
                'preview_text' => 'You are a science teacher helping a student write a lab report for [EXPERIMENT]: Title, Hypothesis, Materials, Procedure, Data Table, Analysis, Conclusion...',
                'uses_count' => 98,
                'tags' => [['name' => 'Science', 'category' => 'subject'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'Science Inquiry Question Bank',
                'description' => 'Câu hỏi truy vấn khoa học (inquiry) cho PBL/STEM units.',
                'preview_text' => 'Generate inquiry questions for a [UNIT] science unit using CER framework (Claim-Evidence-Reasoning). Include 3 levels: factual, comparative, predictive...',
                'uses_count' => 73,
                'tags' => [['name' => 'Science', 'category' => 'subject']],
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'Explain a Concept Simply',
                'description' => 'Giải thích khái niệm khoa học phức tạp cho học sinh dễ hiểu.',
                'preview_text' => 'Explain [CONCEPT] to a Grade [LEVEL] student using analogy, simple language, and 1 everyday example. Avoid jargon. Max 150 words...',
                'uses_count' => 115,
                'tags' => [['name' => 'Science', 'category' => 'subject']],
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'Experiment Design Helper',
                'description' => 'Thiết kế thí nghiệm: biến số, giả thuyết, quy trình, an toàn.',
                'preview_text' => 'Design an experiment to test [HYPOTHESIS]: Variables (IV/DV/controls), Materials, Step-by-step Procedure, Safety Notes, Expected Results...',
                'uses_count' => 67,
                'tags' => [['name' => 'Science', 'category' => 'subject']],
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'Biology Diagram Labeler',
                'description' => 'Tạo bài tập gán nhãn sơ đồ sinh học (tế bào, hệ cơ quan...).',
                'preview_text' => 'Generate a labeling worksheet for [STRUCTURE] (e.g., plant cell, human heart): 10 parts to label, function of each, answer key...',
                'uses_count' => 42,
                'tags' => [['name' => 'Science', 'category' => 'subject']],
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'Chemistry Equation Balancer',
                'description' => 'Cân bằng phương trình hóa học và giải thích.',
                'preview_text' => 'Balance [EQUATION]. Show steps using coefficient method. Explain reaction type (synthesis/decomposition/etc.) and real-world context...',
                'uses_count' => 58,
                'tags' => [['name' => 'Science', 'category' => 'subject']],
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'Physics Word Problem Solver',
                'description' => 'Giải bài toán vật lý có lời văn, chỉ ra công thức.',
                'preview_text' => 'Solve this physics problem: [PROBLEM]. Identify knowns/unknowns, formula, substitution, answer with units. Then 2 similar practice problems...',
                'uses_count' => 81,
                'tags' => [['name' => 'Science', 'category' => 'subject']],
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'Ecosystem Web Builder',
                'description' => 'Xây dựng chuỗi/tháp thức ăn và mối quan hệ sinh thái VN.',
                'preview_text' => 'Build a food web for [ECOSYSTEM] (e.g., Mekong Delta, Vietnamese rainforest). List producers/consumers/decomposers, energy flow, human impact...',
                'uses_count' => 35,
                'tags' => [['name' => 'Science', 'category' => 'subject']],
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'Science Fair Project Mentor',
                'description' => 'Hướng dẫn học sinh chọn & phát triển dự án khoa học.',
                'preview_text' => 'Help student develop a science fair project on [INTEREST]: brainstorming questions, hypothesis, method, timeline, how to present results...',
                'uses_count' => 47,
                'tags' => [['name' => 'Science', 'category' => 'subject'], ['name' => 'STEAM', 'category' => 'subject']],
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'Climate Change Discussion Guide',
                'description' => 'Hướng dẫn thảo luận biến đổi khí hậu (bối cảnh VN).',
                'preview_text' => 'Create a discussion guide on climate change for Grade [LEVEL]: Vietnam context (sea level, agriculture), causes, solutions, action students can take...',
                'uses_count' => 39,
                'tags' => [['name' => 'Science', 'category' => 'subject'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'dung.vu@lsts.edu.vn',
                'title' => 'KUD Science Unit Planner',
                'description' => 'Unit khoa học theo KUD: Know (thuật ngữ), Understand (nguyên lý tự nhiên), Do (thí nghiệm, lập luận).',
                'preview_text' => 'Design a science unit for [TOPIC] Grade [LEVEL] using KUD: KNOW (vocabulary/facts), UNDERSTAND (scientific principles, systems), DO (design experiment, argue from evidence). Include CER assessment...',
                'uses_count' => 44,
                'tags' => [['name' => 'Science', 'category' => 'subject'], ['name' => 'KUD', 'category' => 'framework']],
            ],

            // ═══════════════════════════════════════════════════════════════════════════
            // ENGLISH (10 + KUD)
            // ═══════════════════════════════════════════════════════════════════════════
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'Essay Rubric Builder',
                'description' => 'Generate detailed rubrics for essay assignments with criteria, descriptions, and point values.',
                'preview_text' => 'Create a rubric for a [TYPE] essay assignment for Grade [LEVEL]: 4 criteria (Thesis, Evidence, Organization, Language), 4 levels each with descriptors...',
                'uses_count' => 145,
                'tags' => [['name' => 'English', 'category' => 'subject']],
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'Creative Writing Prompts (Bilingual)',
                'description' => 'Prompt viết sáng tạo song ngữ: narrative, persuasive, descriptive.',
                'preview_text' => 'Generate 10 creative writing prompts for Grade [LEVEL] in English & Vietnamese: narrative, persuasive, descriptive. Include a starter sentence for each...',
                'uses_count' => 102,
                'tags' => [['name' => 'English', 'category' => 'subject'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'English Grammar & Vocabulary Builder',
                'description' => 'Bài tập ngữ pháp/từ vựng theo CEFR level, context LSTS.',
                'preview_text' => 'Create grammar/vocabulary exercises for CEFR [LEVEL] using school context: 5 fill-in-blank, 3 error correction, 2 sentence transformation. Answer key...',
                'uses_count' => 89,
                'tags' => [['name' => 'English', 'category' => 'subject']],
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'Reading Comprehension Worksheet',
                'description' => 'Tạo bài đọc hiểu + câu hỏi theo cấp độ.',
                'preview_text' => 'Write a [LEVEL] reading passage (~200 words) on [TOPIC] + 8 questions: 3 literal, 3 inferential, 2 evaluative. Include answer key...',
                'uses_count' => 76,
                'tags' => [['name' => 'English', 'category' => 'subject']],
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'Essay Feedback Generator',
                'description' => 'Phản hồi bài luận chi tiết, xây dựng, có ví dụ cụ thể.',
                'preview_text' => 'Give constructive feedback on this student essay: Praise (1 specific), Suggest (2 improvements with examples), Next Step. Encouraging tone, grade-appropriate...',
                'uses_count' => 64,
                'tags' => [['name' => 'English', 'category' => 'subject']],
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'Debate Topic & Motion Generator',
                'description' => 'Tạo chủ đề tranh biện và motion cho lớp tiếng Anh.',
                'preview_text' => 'Generate 5 debate motions for Grade [LEVEL] on [THEME]: For/Against stance, 3 arguments each side, vocabulary to use. Age-appropriate, globally aware...',
                'uses_count' => 51,
                'tags' => [['name' => 'English', 'category' => 'subject']],
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'Literature Analysis Guide',
                'description' => 'Hướng dẫn phân tích tác phẩm văn học tiếng Anh.',
                'preview_text' => 'Analyze [WORK]: themes, character development, literary devices, historical context. Include discussion questions for Grade [LEVEL]...',
                'uses_count' => 47,
                'tags' => [['name' => 'English', 'category' => 'subject']],
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'Public Speaking Coach',
                'description' => 'Huấn luyện kỹ năng thuyết trình tiếng Anh cho học sinh.',
                'preview_text' => 'Coach a student on speech about [TOPIC]: structure (hook-body-close), 3 delivery tips, 5 vocabulary upgrades, practice outline. Encouraging...',
                'uses_count' => 43,
                'tags' => [['name' => 'English', 'category' => 'subject']],
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'IELTS/TOEFL Task Prep',
                'description' => 'Luyện đề Writing/Speaking IELTS/TOEFL theo band.',
                'preview_text' => 'Generate an IELTS Writing Task 2 prompt on [TOPIC] + model essay (Band 7-8) + breakdown of why it scores. Include 2 practice prompts...',
                'uses_count' => 58,
                'tags' => [['name' => 'English', 'category' => 'subject']],
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'Book Report Template',
                'description' => 'Mẫu báo cáo sách song ngữ cho học sinh.',
                'preview_text' => 'Create a book report template for Grade [LEVEL]: Summary, Favorite Character, New Words, Connection to Self/World, Rating. Bilingual EN/VI prompts...',
                'uses_count' => 36,
                'tags' => [['name' => 'English', 'category' => 'subject']],
            ],
            [
                'author_email' => 'lan.hoang@lsts.edu.vn',
                'title' => 'KUD English Unit Planner',
                'description' => 'Unit tiếng Anh theo KUD: Know (từ vựng/ngữ pháp), Understand (giao tiếp, văn hóa), Do (viết/nói thực tế).',
                'preview_text' => 'Design an English unit for [SKILL/TOPIC] Grade [LEVEL] using KUD: KNOW (vocab/grammar), UNDERSTAND (purpose, audience, culture), DO (write email, give talk, debate). Map assessments to DO...',
                'uses_count' => 41,
                'tags' => [['name' => 'English', 'category' => 'subject'], ['name' => 'KUD', 'category' => 'framework']],
            ],

            // ═══════════════════════════════════════════════════════════════════════════
            // VIETNAMESE (10 + KUD)
            // ═══════════════════════════════════════════════════════════════════════════
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'title' => 'Vietnamese Literature Analysis Guide',
                'description' => 'Hướng dẫn phân tích tác phẩm VN: cấu trúc, biện pháp nghệ thuật, chủ đề.',
                'preview_text' => 'Phân tích tác phẩm [TÁC PHẨM] của [TÁC GIẢ]: bố cục, biện pháp nghệ thuật, chủ đề, giá trị nội dung/nghệ thuật, bối cảnh lịch sử...',
                'uses_count' => 93,
                'tags' => [['name' => 'Vietnamese', 'category' => 'subject']],
            ],
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'title' => 'Viết Đoạn Văn Nghị Luận',
                'description' => 'Hướng dẫn viết đoạn văn nghị luận xã hội/văn học.',
                'preview_text' => 'Hướng dẫn viết đoạn văn nghị luận về [VẤN ĐỀ]: mở đoạn, thân đoạn (luận điểm, dẫn chứng), kết đoạn. Kèm 1 đoạn mẫu và nhận xét...',
                'uses_count' => 78,
                'tags' => [['name' => 'Vietnamese', 'category' => 'subject']],
            ],
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'title' => 'Tả Cảnh / Tả Người',
                'description' => 'Bài tập văn miêu tả có hướng dẫn chi tiết.',
                'preview_text' => 'Gợi ý dàn ý tả [CẢNH/NGƯỜI]: bố cục 3 phần, từ ngữ gợi hình, biện pháp so sánh. Kèm đoạn văn mẫu lớp [LEVEL]...',
                'uses_count' => 61,
                'tags' => [['name' => 'Vietnamese', 'category' => 'subject']],
            ],
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'title' => 'Soạn Bài Thơ Lớp [LEVEL]',
                'description' => 'Hướng dẫn soạn bài thơ trong chương trình VN.',
                'preview_text' => 'Soạn bài thơ [TÊN] (tác giả [TÁC GIẢ]): tiểu sử, hoàn cảnh sáng tác, bố cục, ý nghĩa nhan đề, giá trị nội dung/nghệ thuật, câu hỏi gợi mở...',
                'uses_count' => 54,
                'tags' => [['name' => 'Vietnamese', 'category' => 'subject']],
            ],
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'title' => 'Chính Tả & Ngữ Pháp Tiếng Việt',
                'description' => 'Bài tập chính tả, ngữ pháp, dấu câu tiếng Việt.',
                'preview_text' => 'Tạo 10 bài tập tiếng Việt cho lớp [LEVEL]: chính tả (luật chính tả), dấu câu, từ loại, cấu trúc câu. Đáp án + giải thích...',
                'uses_count' => 46,
                'tags' => [['name' => 'Vietnamese', 'category' => 'subject']],
            ],
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'title' => 'Viết Thư / Đơn (Thực Hành)',
                'description' => 'Mẫu thư/đơn tiếng Việt thực tế cho học sinh.',
                'preview_text' => 'Viết mẫu đơn xin nghỉ học / thư cảm ơn cho [NGỮ CẢNH]: đúng thể thức, trang trọng, đủ ý. Kèm hướng dẫn điền thông tin...',
                'uses_count' => 38,
                'tags' => [['name' => 'Vietnamese', 'category' => 'subject']],
            ],
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'title' => 'Phân Tích Nhân Vật Văn Học',
                'description' => 'Hướng dẫn phân tích nhân vật (tính cách, diễn biến, ý nghĩa).',
                'preview_text' => 'Phân tích nhân vật [TÊN] trong [TÁC PHẨM]: xuất thân, tính cách, diễn biến tâm lý, ý nghĩa đại diện. Kèm sơ đồ tư duy...',
                'uses_count' => 42,
                'tags' => [['name' => 'Vietnamese', 'category' => 'subject']],
            ],
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'title' => 'Đọc Hiểu Văn Bản Tiếng Việt',
                'description' => 'Tạo bài đọc hiểu + câu hỏi tiếng Việt.',
                'preview_text' => 'Viết đoạn văn ~150 chữ về [CHỦ ĐỀ] + 6 câu hỏi đọc hiểu (nhận biết, suy luận, đánh giá). Đáp án...',
                'uses_count' => 49,
                'tags' => [['name' => 'Vietnamese', 'category' => 'subject']],
            ],
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'title' => 'Viết Truyện Sáng Tạo',
                'description' => 'Gợi ý ý tưởng và dàn ý truyện ngắn cho học sinh.',
                'preview_text' => 'Gợi ý 5 chủ đề truyện ngắn cho học sinh lớp [LEVEL]: nhân vật, tình huống, xung đột, bài học. Kèm 1 dàn ý chi tiết...',
                'uses_count' => 33,
                'tags' => [['name' => 'Vietnamese', 'category' => 'subject']],
            ],
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'title' => 'Nghị Luận Về Một Ý Kiến (Bàn Về)',
                'description' => 'Hướng dẫn nghị luận xã hội về câu danh ngôn/châm ngôn.',
                'preview_text' => 'Hướng dẫn làm bài nghị luận về ý kiến "[CÂU]": giải thích, bàn luận (đúng/sai, ví dụ), bài học. Dàn ý + đoạn mẫu...',
                'uses_count' => 37,
                'tags' => [['name' => 'Vietnamese', 'category' => 'subject']],
            ],
            [
                'author_email' => 'chi.tran@lsts.edu.vn',
                'title' => 'KUD Vietnamese Unit Planner',
                'description' => 'Unit Ngữ văn theo KUD: Know (từ ngữ, thi pháp), Understand (giá trị văn hóa), Do (viết, nói, cảm thụ).',
                'preview_text' => 'Thiết kế unit Ngữ văn [CHỦ ĐỀ] lớp [LEVEL] theo KUD: KNOW (từ ngữ, thi pháp), UNDERSTAND (giá trị văn hóa, con người VN), DO (viết đoạn văn, thuyết trình, cảm thụ thơ). Ghép đánh giá vào DO...',
                'uses_count' => 35,
                'tags' => [['name' => 'Vietnamese', 'category' => 'subject'], ['name' => 'KUD', 'category' => 'framework']],
            ],

            // ═══════════════════════════════════════════════════════════════════════════
            // CIEC / STEAM / INNOVATION (10)
            // ═══════════════════════════════════════════════════════════════════════════
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Design Thinking Sprint Planner',
                'description' => 'Kế hoạch sprint Design Thinking 5 giai đoạn cho học sinh.',
                'preview_text' => 'Plan a 5-day Design Thinking sprint for [CHALLENGE]: Empathize, Define, Ideate, Prototype, Test. Include student worksheets + facilitator notes...',
                'uses_count' => 68,
                'tags' => [['name' => 'STEAM', 'category' => 'subject'], ['name' => 'CIEC', 'category' => 'subject'], ['name' => 'CIEC', 'category' => 'role']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Project-Based Learning Unit Designer',
                'description' => 'Thiết kế unit PBL: driving question, milestones, products, public audience.',
                'preview_text' => 'Design a PBL unit for [SUBJECT] with: Driving Question, Milestones (4-6 weeks), Student Products, Public Audience, Reflection. Grade [LEVEL]...',
                'uses_count' => 84,
                'tags' => [['name' => 'STEAM', 'category' => 'subject'], ['name' => 'CIEC', 'category' => 'subject'], ['name' => 'CIEC', 'category' => 'role']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Entrepreneurship Pitch Coach',
                'description' => 'Huấn luyện pitch startup cho học sinh: problem, solution, market, traction.',
                'preview_text' => 'You are a startup mentor. Help student refine their pitch for [IDEA]: Problem, Solution, Market, Business Model, Ask. Give 3 improvement tips...',
                'uses_count' => 52,
                'tags' => [['name' => 'CIEC', 'category' => 'subject'], ['name' => 'CIEC', 'category' => 'role'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Innovation Challenge Brief Generator',
                'description' => 'Tạo brief thử thách đổi mới: context, constraints, success criteria, resources.',
                'preview_text' => 'Generate an innovation challenge brief for [THEME]: Context, Problem Statement, Constraints, Success Criteria, Available Resources, Judging Rubric...',
                'uses_count' => 41,
                'tags' => [['name' => 'CIEC', 'category' => 'subject'], ['name' => 'CIEC', 'category' => 'role']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Maker Space Activity Cards',
                'description' => 'Thẻ hoạt động Maker Space: materials, steps, learning outcomes, safety.',
                'preview_text' => 'Create 8 maker space activity cards for [GRADE BAND]: Title, Materials, Steps, Learning Outcome, Safety Note. Low-cost, recyclable materials...',
                'uses_count' => 37,
                'tags' => [['name' => 'STEAM', 'category' => 'subject']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Coding Project Generator (Beginner)',
                'description' => 'Ý tưởng dự án lập trình cho học sinh mới bắt đầu.',
                'preview_text' => 'Suggest 5 beginner coding projects in [LANGUAGE/BLOCK]: description, concepts covered, starter code snippet, extension challenge. Age [LEVEL]...',
                'uses_count' => 33,
                'tags' => [['name' => 'STEAM', 'category' => 'subject']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'STEM Career Exploration Guide',
                'description' => 'Hướng dẫn khám phá nghề STEM cho học sinh THCS/THPT.',
                'preview_text' => 'Create a STEM career exploration guide for [INTEREST]: 5 careers, what they do, skills needed, study path in VN, day-in-life. Inspiring tone...',
                'uses_count' => 29,
                'tags' => [['name' => 'STEAM', 'category' => 'subject'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Sustainability Project Planner',
                'description' => 'Kế hoạch dự án bền vững cho trường (rác, năng lượng, vườn).',
                'preview_text' => 'Design a school sustainability project on [FOCUS]: Goal, Baseline Audit, Action Steps, Student Roles, Measurement. Align to SDGs...',
                'uses_count' => 25,
                'tags' => [['name' => 'CIEC', 'category' => 'role'], ['name' => 'STEAM', 'category' => 'subject']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'Student Portfolio Reflection Prompts',
                'description' => 'Prompt phản tư cho portfolio dự án học sinh.',
                'preview_text' => 'Generate 8 portfolio reflection prompts for [PROJECT]: What did I make? What was hard? What would I change? How does this connect to real life?...',
                'uses_count' => 31,
                'tags' => [['name' => 'CIEC', 'category' => 'subject'], ['name' => 'CIEC', 'category' => 'role']],
            ],
            [
                'author_email' => 'fi.truong@lsts.edu.vn',
                'title' => 'EdTech Tool Evaluator',
                'description' => 'Tiêu chí đánh giá công cụ EdTech cho giáo viên.',
                'preview_text' => 'Evaluate [TOOL] for classroom use: Pedagogy fit, Ease of use, Cost, Privacy/Data, Accessibility, VN context. Score 1-5 each, recommend Y/N...',
                'uses_count' => 22,
                'tags' => [['name' => 'CIEC', 'category' => 'subject'], ['name' => 'CIEC', 'category' => 'role'], ['name' => 'New', 'category' => 'status']],
            ],

            // ═══════════════════════════════════════════════════════════════════════════
            // COUNSELING / WELLBEING (10)
            // ═══════════════════════════════════════════════════════════════════════════
            [
                'author_email' => 'mai.tran@lsts.edu.vn',
                'title' => 'SEL Reflection Prompts',
                'description' => 'Prompt phản tư SEL: self-awareness, self-management, social awareness, relationships.',
                'preview_text' => 'Generate SEL reflection prompts for [GRADE] aligned to CASEL: self-awareness, self-management, social awareness, relationship skills, responsible decision-making...',
                'uses_count' => 64,
                'tags' => [['name' => 'Counseling', 'category' => 'subject'], ['name' => 'Counseling', 'category' => 'role'], ['name' => 'New', 'category' => 'status']],
            ],
            [
                'author_email' => 'mai.tran@lsts.edu.vn',
                'title' => 'IEP/ILP Goal Writer',
                'description' => 'Viết mục tiêu IEP/ILP: SMART, measurable, aligned to standards.',
                'preview_text' => 'Write SMART IEP goals for student with [NEEDS] in [DOMAIN]: present level, 3 goals (measurable), progress monitoring method, timeline...',
                'uses_count' => 48,
                'tags' => [['name' => 'Counseling', 'category' => 'subject'], ['name' => 'Counseling', 'category' => 'role']],
            ],
            [
                'author_email' => 'mai.tran@lsts.edu.vn',
                'title' => 'College/Career Counseling Guide',
                'description' => 'Hướng dẫn tư vấn đại học/nghề: timeline, essays, portfolio, interviews.',
                'preview_text' => 'Create a college counseling timeline and essay brainstorming guide for Grade 11-12: testing, applications, essays, interviews, VN & abroad options...',
                'uses_count' => 53,
                'tags' => [['name' => 'Counseling', 'category' => 'subject'], ['name' => 'Counseling', 'category' => 'role']],
            ],
            [
                'author_email' => 'mai.tran@lsts.edu.vn',
                'title' => 'Bullying Prevention Scenario',
                'description' => 'Kịch bản phòng chống bắt nạt cho giáo viên/học sinh.',
                'preview_text' => 'Create a bullying prevention role-play scenario for Grade [LEVEL]: situation, bystander options, teacher facilitation questions, restorative circle script...',
                'uses_count' => 39,
                'tags' => [['name' => 'Counseling', 'category' => 'subject'], ['name' => 'Counseling', 'category' => 'role']],
            ],
            [
                'author_email' => 'mai.tran@lsts.edu.vn',
                'title' => 'Stress & Anxiety Coping Plan',
                'description' => 'Kế hoạch ứng phó căng thẳng cho học sinh thi cử.',
                'preview_text' => 'Help a student create a stress coping plan for exam season: triggers, 5 coping strategies (breathing, study schedule), support network, when to ask for help...',
                'uses_count' => 44,
                'tags' => [['name' => 'Counseling', 'category' => 'subject'], ['name' => 'Counseling', 'category' => 'role']],
            ],
            [
                'author_email' => 'mai.tran@lsts.edu.vn',
                'title' => 'Parent Counseling Script',
                'description' => 'Kịch bản tư vấn phụ huynh về phát triển con/em.',
                'preview_text' => 'Draft a parent counseling script for [CONCERN]: empathetic opening, share observation (data), collaborative plan, resources, follow-up. Bilingual tone...',
                'uses_count' => 31,
                'tags' => [['name' => 'Counseling', 'category' => 'subject'], ['name' => 'Counseling', 'category' => 'role']],
            ],
            [
                'author_email' => 'mai.tran@lsts.edu.vn',
                'title' => 'Study Skills Workshop Plan',
                'description' => 'Kế hoạch workshop kỹ năng học tập (note-taking, time management).',
                'preview_text' => 'Design a 45-min study skills workshop for Grade [LEVEL]: Cornell notes, Pomodoro, spaced repetition. Include handout + activity...',
                'uses_count' => 36,
                'tags' => [['name' => 'Counseling', 'category' => 'subject'], ['name' => 'Counseling', 'category' => 'role']],
            ],
            [
                'author_email' => 'mai.tran@lsts.edu.vn',
                'title' => 'Growth Mindset Lesson',
                'description' => 'Bài học tư duy phát triển (growth mindset) cho lớp.',
                'preview_text' => 'Create a growth mindset lesson for Grade [LEVEL]: story, "yet" power, reframe failure activity, class pledge. Engaging, bilingual...',
                'uses_count' => 42,
                'tags' => [['name' => 'Counseling', 'category' => 'subject'], ['name' => 'Counseling', 'category' => 'role']],
            ],
            [
                'author_email' => 'mai.tran@lsts.edu.vn',
                'title' => 'Conflict Resolution Mediator',
                'description' => 'Kịch bản hòa giải xung đột giữa học sinh.',
                'preview_text' => 'Mediate conflict between [A] and [B]: neutral opening, each side speaks, find common ground, agreement, follow-up check. Restorative approach...',
                'uses_count' => 28,
                'tags' => [['name' => 'Counseling', 'category' => 'subject'], ['name' => 'Counseling', 'category' => 'role']],
            ],
            [
                'author_email' => 'mai.tran@lsts.edu.vn',
                'title' => 'Wellbeing Newsletter for Parents',
                'description' => 'Newsletter sức khỏe tinh thần cho phụ huynh hàng tháng.',
                'preview_text' => 'Write a monthly wellbeing newsletter for parents: 1 tip, 1 activity, 1 resource (VN context), bilingual EN/VI. Warm, non-clinical tone...',
                'uses_count' => 24,
                'tags' => [['name' => 'Counseling', 'category' => 'subject'], ['name' => 'Counseling', 'category' => 'role'], ['name' => 'New', 'category' => 'status']],
            ],
        ];

        // Xóa dữ liệu cũ để seed sạch (idempotent)
        PromptLibraryPrompt::query()->delete();
        \DB::table('prompt_tags')->delete();

        foreach ($prompts as $data) {
            $authorId = $getAuthor($data['author_email']);
            if (! $authorId) {
                continue; // Chưa seed User thì bỏ qua — chạy UserSeeder trước
            }

            $prompt = PromptLibraryPrompt::create([
                'author_id' => $authorId,
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
