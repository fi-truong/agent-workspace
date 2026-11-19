<?php

namespace App\Support;

final class HomepageQuotes
{
    private const QUOTES = [
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

    /** @return array{id: string, text: string, name: string, role: string} */
    public static function choose(?string $previousId): array
    {
        $ids = array_keys(self::QUOTES);
        shuffle($ids);
        $id = $ids[0] === $previousId ? $ids[1] : $ids[0];
        $quote = self::QUOTES[$id];

        return [
            'id' => $id,
            'text' => $quote['text'],
            'name' => $quote['name'],
            'role' => $quote['role'],
        ];
    }
}
