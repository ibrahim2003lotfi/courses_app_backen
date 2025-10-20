<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RecommendationService;
use App\Models\User;

class WarmRecommendationCache extends Command
{
    protected $signature = 'recommendations:warm-cache';
    protected $description = 'Warm up recommendation caches for better performance';

    protected $recommendationService;

    public function __construct(RecommendationService $recommendationService)
    {
        parent::__construct();
        $this->recommendationService = $recommendationService;
    }

    public function handle()
    {
        $this->info('Starting cache warming...');

        // Clear old caches
        $this->recommendationService->clearAllCaches();
        $this->info('✓ Cleared old caches');

        // Warm up general caches
        $this->recommendationService->getMostPopular();
        $this->info('✓ Cached popular courses');

        $this->recommendationService->getTrending();
        $this->info('✓ Cached trending courses');

        $this->recommendationService->getBestInstructors();
        $this->info('✓ Cached best instructors');

        // Warm up for recently active users
        $activeUsers = User::whereHas('enrollments', function ($query) {
            $query->where('created_at', '>=', now()->subDays(7));
        })->limit(50)->get();

        $bar = $this->output->createProgressBar($activeUsers->count());
        $bar->start();

        foreach ($activeUsers as $user) {
            $this->recommendationService->getRecommendations($user);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✓ Cached recommendations for ' . $activeUsers->count() . ' active users');

        $this->info('Cache warming completed successfully!');
        
        return Command::SUCCESS;
    }
}