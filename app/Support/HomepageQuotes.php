<?php

namespace App\Support;

final class HomepageQuotes
{
    private const ORIGINAL_QUOTES = [
        // https://issues.org/interview-godmother-ai-fei-fei-li/
        'fei-fei-li' => [
            'text' => 'And tools don’t have independent values—their values are human values.',
            'name' => 'Fei-Fei Li',
            'role' => 'Co-founder, World Labs',
        ],
        // https://blog.khanacademy.org/sal-khans-2023-ted-talk-ai-in-the-classroom-can-transform-education/
        'sal-khan' => [
            'text' => 'We’re at the cusp of using AI for probably the biggest positive transformation that education has ever seen.',
            'name' => 'Sal Khan',
            'role' => 'Founder, Khan Academy',
        ],
        // https://www.deeplearning.ai/blog/deeplearning-ai-announcing-new-deep-learning-courses-on-coursera
        'andrew-ng' => [
            'text' => 'AI is the new electricity.',
            'name' => 'Andrew Ng',
            'role' => 'Founder, DeepLearning.AI',
        ],
        // https://openai.com/sam-and-jony/
        'sam-altman' => [
            'text' => 'AI is an incredible technology, but great tools require work at the intersection of technology, design, and understanding people and the world.',
            'name' => 'Sam Altman',
            'role' => 'Co-founder, OpenAI',
        ],
        // https://darioamodei.com/post/we-must-pace-the-frontier#bottom-line
        'dario-amodei' => [
            'text' => 'I continue to believe that AI can enormously improve the quality of human life.',
            'name' => 'Dario Amodei',
            'role' => 'Co-founder, Anthropic',
        ],
        // https://deepmind.google/blog/demis-hassabis-john-jumper-awarded-nobel-prize-in-chemistry/
        'demis-hassabis' => [
            'text' => 'I hope we’ll look back on AlphaFold as the first proof point of AI’s incredible potential to accelerate scientific discovery.',
            'name' => 'Demis Hassabis',
            'role' => 'Co-founder, Google DeepMind',
        ],
        // https://lexfridman.com/?p=5937 (00:41:07)
        'aravind-srinivas' => [
            'text' => 'Curiosity is unbounded in this world.',
            'name' => 'Aravind Srinivas',
            'role' => 'Co-founder, Perplexity',
        ],
        // https://mistral.ai/news/mistral-ai-and-nvidia-partner-to-accelerate-open-frontier-models/
        'arthur-mensch' => [
            'text' => 'Open frontier models are how AI becomes a true platform.',
            'name' => 'Arthur Mensch',
            'role' => 'Co-founder, Mistral AI',
        ],
        // https://huggingface.co/blog/assets/164_ethics-soc-5/clem_ai_insight_forum.pdf
        'clement-delangue' => [
            'text' => 'Open and collaborative development helps developers leverage expertise and inventiveness across sectors to optimize safety and performance.',
            'name' => 'Clément Delangue',
            'role' => 'Co-founder, Hugging Face',
        ],
        // https://cohere.com/blog/how-were-getting-ai-risk-wrong-aidan-gomez
        'aidan-gomez' => [
            'text' => 'Finally, we need to ensure that AI acts in the service of humanity.',
            'name' => 'Aidan Gomez',
            'role' => 'Co-founder, Cohere',
        ],
    ];

    private const HUMAN_CENTERED_QUOTES = [
        // https://www.unesco.org/en/articles/unesco-dedicates-international-day-education-2025-artificial-intelligence
        'audrey-azoulay' => [
            'text' => 'It must become a tool at the service of teachers and pupils, with the main objective being their autonomy and well-being.',
            'name' => 'Audrey Azoulay',
            'role' => 'Former Director-General, UNESCO',
        ],
        // https://hai.stanford.edu/news/ai-leaders-human-centered-approach-artificial-intelligence
        'fei-fei-li-human-responsibility' => [
            'text' => 'Tools are made by humans, are being deployed by humans, and should be used properly by humans.',
            'name' => 'Fei-Fei Li',
            'role' => 'Co-founder, World Labs',
        ],
        // https://www.ted.com/podcasts/rethinking-with-adam-grant/khan-academy-founder-sal-khan-on-ai-and-the-future-of-education-transcript
        'sal-khan-teaching-first' => [
            'text' => 'What we’ve always advocated for is never putting technology first.',
            'name' => 'Sal Khan',
            'role' => 'Founder, Khan Academy',
        ],
        // https://www.oneusefulthing.org/p/the-overhang
        'ethan-mollick' => [
            'text' => 'And it is equally important that we, as individuals, use AI in ways that enhance, rather than only replace, our own efforts.',
            'name' => 'Ethan Mollick',
            'role' => 'Associate Professor, Wharton',
        ],
        // https://news.microsoft.com/wp-content/uploads/prod/2023/04/Satya-Nadella-The-Future-of-Work-with-AI.pdf
        'satya-nadella' => [
            'text' => 'Just as an individual can be aided by AI, AI can be influenced – positively or negatively – by the person using it.',
            'name' => 'Satya Nadella',
            'role' => 'Chairman and CEO, Microsoft',
        ],
        // https://www.ted.com/talks/joy_buolamwini_how_i_m_fighting_bias_in_algorithms
        'joy-buolamwini' => [
            'text' => 'Who codes matters, how we code matters and why we code matters.',
            'name' => 'Joy Buolamwini',
            'role' => 'Founder, Algorithmic Justice League',
        ],
        // https://darioamodei.com/essay/machines-of-loving-grace
        'dario-amodei-work-and-meaning' => [
            'text' => 'I think it is very likely a mistake to believe that tasks you undertake are meaningless simply because an AI could do them better.',
            'name' => 'Dario Amodei',
            'role' => 'Co-founder, Anthropic',
        ],
        // https://www.ted.com/talks/kai_fu_lee_how_ai_can_save_our_humanity
        'kai-fu-lee' => [
            'text' => 'It is here to liberate us from routine jobs, and it is here to remind us what it is that makes us human.',
            'name' => 'Kai-Fu Lee',
            'role' => 'Founder, 01.AI',
        ],
        // https://openai.com/index/sam-altman-un-security-council-remarks/
        'sam-altman-human-agency' => [
            'text' => 'AI should give people more power over their own lives.',
            'name' => 'Sam Altman',
            'role' => 'Co-founder, OpenAI',
        ],
        // https://cohere.com/blog/cohere-and-aleph-alpha-sign-agreement
        'aidan-gomez-ai-control' => [
            'text' => 'No government or enterprise should have to choose between capable AI and control over their technology.',
            'name' => 'Aidan Gomez',
            'role' => 'Co-founder, Cohere',
        ],
    ];

    // Keep only the groups that should appear in the homepage rotation.
    private const ACTIVE_QUOTES = self::ORIGINAL_QUOTES + self::HUMAN_CENTERED_QUOTES;

    /** @return array{id: string, text: string, name: string, role: string} */
    public static function choose(?string $previousId): array
    {
        $ids = array_keys(self::ACTIVE_QUOTES);
        shuffle($ids);
        $id = $ids[0] === $previousId ? $ids[1] : $ids[0];
        $quote = self::ACTIVE_QUOTES[$id];

        return [
            'id' => $id,
            'text' => $quote['text'],
            'name' => $quote['name'],
            'role' => $quote['role'],
        ];
    }
}
