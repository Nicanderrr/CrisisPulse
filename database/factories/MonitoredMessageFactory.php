<?php

namespace Database\Factories;

use App\Models\MonitoredMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoredMessage>
 */
class MonitoredMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => $this->faker->randomElement(['Facebook', 'X', 'News Comment', 'Patient Feedback']),
            'author' => $this->faker->name(),
            'source_url' => null,
            'attachments' => [],
            'content' => $this->faker->sentence(14),
            'sentiment' => $this->faker->randomElement(['positive', 'neutral', 'negative']),
            'sentiment_score' => $this->faker->randomFloat(2, -1, 1),
            'crisis_level' => $this->faker->randomElement(['low', 'medium', 'high']),
            'crisis_keywords' => [],
            'recommended_response' => $this->faker->sentence(),
            'summary' => $this->faker->sentence(),
            'detailed_analysis' => $this->faker->paragraph(),
            'analyzed_at' => now(),
        ];
    }
}
