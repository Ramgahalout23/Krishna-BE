<?php

namespace App\Services;

use App\Models\AdCampaign;

class AdAnalyticsService
{
    /**
     * Get comprehensive ad performance report with cross-platform comparison.
     */
    public function getPerformanceReport(int $days = 30): array
    {
        $since = now()->subDays($days);

        $campaigns = AdCampaign::where(function ($q) use ($since) {
            $q->where('created_at', '>=', $since)
              ->orWhere('updated_at', '>=', $since);
        })->orderBy('created_at', 'desc')->get();

        $platforms = $campaigns->pluck('platform')->unique()->values()->toArray();

        // Platform breakdown
        $byPlatform = [];
        foreach ($platforms as $platform) {
            $pc = $campaigns->where('platform', $platform);
            $totalBudget = (float) $pc->sum('budget');
            $totalSpent = (float) $pc->sum('spent');
            $totalImpressions = (int) $pc->sum('impressions');
            $totalClicks = (int) $pc->sum('clicks');
            $totalConversions = (int) $pc->sum('conversions');
            $totalReach = (int) $pc->sum('reach');
            $avgCtr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;
            $avgCpc = $totalClicks > 0 ? round($totalSpent / $totalClicks, 2) : 0;
            $avgCpa = $totalConversions > 0 ? round($totalSpent / $totalConversions, 2) : 0;
            $avgCpm = $totalImpressions > 0 ? round(($totalSpent / $totalImpressions) * 1000, 2) : 0;
            $roas = $totalSpent > 0 ? round(($totalConversions * 1000) / $totalSpent, 2) : 0;
            $conversionRate = $totalClicks > 0 ? round(($totalConversions / $totalClicks) * 100, 2) : 0;

            $byPlatform[] = [
                'platform' => $platform,
                'campaign_count' => $pc->count(),
                'total_budget' => $totalBudget,
                'total_spent' => $totalSpent,
                'total_impressions' => $totalImpressions,
                'total_clicks' => $totalClicks,
                'total_conversions' => $totalConversions,
                'total_reach' => $totalReach,
                'avg_ctr' => $avgCtr,
                'avg_cpc' => $avgCpc,
                'avg_cpa' => $avgCpa,
                'avg_cpm' => $avgCpm,
                'roas' => $roas,
                'spent_percent' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0,
                'conversion_rate' => $conversionRate,
                // CamelCase aliases for frontend
                'campaignCount' => $pc->count(),
                'totalBudget' => $totalBudget,
                'totalSpent' => $totalSpent,
                'totalImpressions' => $totalImpressions,
                'totalClicks' => $totalClicks,
                'totalConversions' => $totalConversions,
                'totalReach' => $totalReach,
                'avgCTR' => $avgCtr,
                'avgCPC' => $avgCpc,
                'avgCPA' => $avgCpa,
                'avgCPM' => $avgCpm,
                'conversionRate' => $conversionRate,
                'spentPercent' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0,
            ];
        }

        // Status breakdown
        $byStatus = $campaigns->groupBy('status')->map(fn($g) => $g->count())->toArray();

        // Top campaigns
        $topCampaigns = $campaigns->sortByDesc('clicks')->take(5)->values()->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'platform' => $c->platform,
            'status' => $c->status,
            'budget' => (float) ($c->budget ?? 0),
            'spent' => (float) ($c->spent ?? 0),
            'impressions' => (int) $c->impressions,
            'clicks' => (int) $c->clicks,
            'conversions' => (int) $c->conversions,
            'ctr' => $c->impressions > 0 ? round(($c->clicks / $c->impressions) * 100, 2) : 0,
            'roas' => ($c->spent && (float)$c->spent > 0) ? round(($c->conversions * 1000) / (float)$c->spent, 2) : 0,
        ])->toArray();

        // Trend data
        $trends = $this->generateTrendData($campaigns, $days);

        // Summary
        $totalBudget = (float) $campaigns->sum('budget');
        $totalSpent = (float) $campaigns->sum('spent');
        $totalImpressions = (int) $campaigns->sum('impressions');
        $totalClicks = (int) $campaigns->sum('clicks');
        $totalConversions = (int) $campaigns->sum('conversions');

        $summary = [
            'total_campaigns' => $campaigns->count(),
            'active_campaigns' => $campaigns->where('status', 'ACTIVE')->count(),
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'total_impressions' => $totalImpressions,
            'total_clicks' => $totalClicks,
            'total_conversions' => $totalConversions,
            'average_ctr' => $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0,
            'average_cpc' => $totalClicks > 0 ? round($totalSpent / $totalClicks, 2) : 0,
            'average_cpa' => $totalConversions > 0 ? round($totalSpent / $totalConversions, 2) : 0,
            'average_cpm' => $totalImpressions > 0 ? round(($totalSpent / $totalImpressions) * 1000, 2) : 0,
            'overall_roas' => $totalSpent > 0 ? round(($totalConversions * 1000) / $totalSpent, 2) : 0,
            'conversion_rate' => $totalClicks > 0 ? round(($totalConversions / $totalClicks) * 100, 2) : 0,
            'budget_utilization' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0,
        ];

        // Recommendations
        $recommendations = $this->generateRecommendations($byPlatform, $summary);

        return compact('summary', 'byPlatform', 'byStatus', 'trends', 'topCampaigns', 'recommendations');
    }

    /**
     * Compare two ad campaigns.
     */
    public function compareCampaigns(string $campaignId1, string $campaignId2): array
    {
        $c1 = AdCampaign::findOrFail($campaignId1);
        $c2 = AdCampaign::findOrFail($campaignId2);

        $metrics1 = $this->campaignMetrics($c1);
        $metrics2 = $this->campaignMetrics($c2);

        $winner = $this->determineWinner($metrics1, $metrics2);

        return ['campaign1' => $metrics1, 'campaign2' => $metrics2, 'winner' => $winner];
    }

    /**
     * Get brand preset performance analysis.
     */
    public function getBrandPresetPerformance(): array
    {
        $presetPatterns = [
            ['key' => 'flash_sale', 'name' => 'Flash Sale', 'patterns' => ['flash sale', 'flash']],
            ['key' => 'new_collection', 'name' => 'New Collection Launch', 'patterns' => ['new collection', 'collection launch', 'new launch']],
            ['key' => 'best_sellers', 'name' => 'Best Sellers', 'patterns' => ['best seller', 'top seller']],
            ['key' => 'seasonal', 'name' => 'Seasonal Promotion', 'patterns' => ['seasonal', 'festive', 'holiday', 'festival']],
            ['key' => 'brand_awareness', 'name' => 'Brand Awareness', 'patterns' => ['brand awareness', 'awareness']],
            ['key' => 'whatsapp', 'name' => 'WhatsApp Broadcast', 'patterns' => ['whatsapp', 'broadcast']],
        ];

        $campaigns = AdCampaign::orderBy('created_at', 'desc')->get();

        // Match campaigns to presets
        $presetMap = [];
        foreach ($presetPatterns as $p) { $presetMap[$p['key']] = collect(); }
        $presetMap['other'] = collect();

        foreach ($campaigns as $campaign) {
            $name = strtolower($campaign->name ?? '');
            $matched = false;
            foreach ($presetPatterns as $preset) {
                foreach ($preset['patterns'] as $pattern) {
                    if (str_contains($name, $pattern)) {
                        $presetMap[$preset['key']]->push($campaign);
                        $matched = true;
                        break 2;
                    }
                }
            }
            if (!$matched) {
                $presetMap['other']->push($campaign);
            }
        }

        $presets = [];
        foreach ($presetPatterns as $preset) {
            $pc = $presetMap[$preset['key']] ?? collect();
            if ($pc->isEmpty()) continue;

            $totalBudget = (float) $pc->sum('budget');
            $totalSpent = (float) $pc->sum('spent');
            $totalImpressions = (int) $pc->sum('impressions');
            $totalClicks = (int) $pc->sum('clicks');
            $totalConversions = (int) $pc->sum('conversions');

            $avgCTR = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0;
            $avgCPC = $totalClicks > 0 ? $totalSpent / $totalClicks : 0;
            $avgCPM = $totalImpressions > 0 ? ($totalSpent / $totalImpressions) * 1000 : 0;
            $roas = $totalSpent > 0 ? ($totalConversions * 1000) / $totalSpent : 0;
            $conversionRate = $totalClicks > 0 ? ($totalConversions / $totalClicks) * 100 : 0;

            $platforms = $pc->groupBy('platform')->map(fn($g) => $g->count())->toArray();

            // Performance score matching TS: CTR(30) + ROAS(30) + ConvRate(20) + Efficiency(10) + Volume(10)
            $ctrScore = min($avgCTR / 3, 1) * 30;
            $roasScore = min($roas / 3, 1) * 30;
            $conversionScore = min($conversionRate / 5, 1) * 20;
            $efficiencyScore = $totalBudget > 0 ? max(0, 1 - ($totalSpent / $totalBudget)) * 10 : 0;
            $volumeScore = min($totalImpressions / 50000, 1) * 10;

            $presets[] = [
                'preset_key' => $preset['key'],
                'preset_name' => $preset['name'],
                'campaign_count' => $pc->count(),
                'active_count' => $pc->where('status', 'ACTIVE')->count(),
                'total_budget' => $totalBudget,
                'total_spent' => $totalSpent,
                'total_impressions' => $totalImpressions,
                'total_clicks' => $totalClicks,
                'total_conversions' => $totalConversions,
                'avg_ctr' => round($avgCTR, 2),
                'avg_cpc' => round($avgCPC, 2),
                'avg_cpm' => round($avgCPM, 2),
                'roas' => round($roas, 2),
                'conversion_rate' => round($conversionRate, 2),
                'platforms' => $platforms,
                'performance_score' => round($ctrScore + $roasScore + $conversionScore + $efficiencyScore + $volumeScore, 1),
                // CamelCase aliases for frontend Brand tab
                'presetKey' => $preset['key'],
                'presetName' => $preset['name'],
                'campaignCount' => $pc->count(),
                'activeCount' => $pc->where('status', 'ACTIVE')->count(),
                'totalBudget' => $totalBudget,
                'totalSpent' => $totalSpent,
                'totalImpressions' => $totalImpressions,
                'totalClicks' => $totalClicks,
                'totalConversions' => $totalConversions,
                'avgCTR' => round($avgCTR, 2),
                'avgCPC' => round($avgCPC, 2),
                'avgCPM' => round($avgCPM, 2),
                'conversionRate' => round($conversionRate, 2),
                'performanceScore' => round($ctrScore + $roasScore + $conversionScore + $efficiencyScore + $volumeScore, 1),
            ];
        }

        usort($presets, fn($a, $b) => $b['performance_score'] <=> $a['performance_score']);

        // Generate cross-platform insights (matching TS behavior)
        $crossPlatformInsights = [];
        if (!empty($presets)) {
            if ($presets[0]['performance_score'] > 50) {
                $crossPlatformInsights[] = "🏆 Top preset: \"{$presets[0]['preset_name']}\" (score: {$presets[0]['performance_score']}) — {$presets[0]['campaign_count']} campaigns, {$presets[0]['roas']}x ROAS";
            }
            if (count($presets) > 1 && $presets[count($presets) - 1]['performance_score'] < 30) {
                $crossPlatformInsights[] = "⚠️ Worst preset: \"{$presets[count($presets) - 1]['preset_name']}\" — consider refreshing creatives or adjusting targeting";
            }
            // Platform preference insights
            $platformUsage = [];
            foreach ($presets as $p) {
                foreach ($p['platforms'] as $plat => $count) {
                    $platformUsage[$plat] = ($platformUsage[$plat] ?? 0) + $count;
                }
            }
            if (!empty($platformUsage)) {
                arsort($platformUsage);
                $mostUsedPlatform = array_key_first($platformUsage);
                $crossPlatformInsights[] = "📊 Most active platform: {$mostUsedPlatform} ({$platformUsage[$mostUsedPlatform]} campaigns)";
            }
        }

        return [
            'presets' => $presets,
            'top_preset' => $presets[0]['preset_name'] ?? null,
            'worst_preset' => count($presets) > 1 ? $presets[count($presets) - 1]['preset_name'] : null,
            'cross_platform_insights' => $crossPlatformInsights,
            'crossPlatformInsights' => $crossPlatformInsights,
            'topPreset' => $presets[0]['preset_name'] ?? null,
            'worstPreset' => count($presets) > 1 ? $presets[count($presets) - 1]['preset_name'] : null,
        ];
    }

    /**
     * Generate campaign metrics.
     */
    private function campaignMetrics(AdCampaign $campaign): array
    {
        $budget = (float) ($campaign->budget ?? 0);
        $spent = (float) ($campaign->spent ?? 0);
        $impressions = (int) $campaign->impressions;
        $clicks = (int) $campaign->clicks;
        $conversions = (int) $campaign->conversions;
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
        $cpc = $clicks > 0 ? round($spent / $clicks, 2) : 0;
        $cpm = $impressions > 0 ? round(($spent / $impressions) * 1000, 2) : 0;
        $conversionRate = $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0;
        $roas = $spent > 0 ? round(($conversions * 1000) / $spent, 2) : 0;

        return [
            'name' => $campaign->name,
            'platform' => $campaign->platform,
            'budget' => $budget,
            'spent' => $spent,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'conversions' => $conversions,
            'reach' => (int) $campaign->reach,
            'ctr' => $ctr,
            'cpc' => $cpc,
            'cpm' => $cpm,
            'conversion_rate' => $conversionRate,
            'roas' => $roas,
            // CamelCase aliases for frontend Compare tab
            'conversionRate' => $conversionRate,
        ];
    }

    /**
     * Determine which campaign is performing better.
     */
    private function determineWinner(array $c1, array $c2): array
    {
        $reasons = [];
        $c1Score = 0;
        $c2Score = 0;

        if ($c1['ctr'] > $c2['ctr']) { $c1Score++; $reasons[] = "Higher CTR ({$c1['ctr']}% vs {$c2['ctr']}%)"; }
        else { $c2Score++; $reasons[] = "Higher CTR ({$c2['ctr']}% vs {$c1['ctr']}%)"; }

        if ($c1['cpc'] < $c2['cpc']) { $c1Score++; $reasons[] = "Lower CPC (\${$c1['cpc']} vs \${$c2['cpc']})"; }
        else { $c2Score++; $reasons[] = "Lower CPC (\${$c2['cpc']} vs \${$c1['cpc']})"; }

        if ($c1['conversion_rate'] > $c2['conversion_rate']) { $c1Score++; $reasons[] = "Higher conversion rate ({$c1['conversion_rate']}% vs {$c2['conversion_rate']}%)"; }
        else { $c2Score++; $reasons[] = "Higher conversion rate ({$c2['conversion_rate']}% vs {$c1['conversion_rate']}%)"; }

        if ($c1['roas'] > $c2['roas']) { $c1Score++; $reasons[] = "Better ROAS ({$c1['roas']}x vs {$c2['roas']}x)"; }
        else { $c2Score++; $reasons[] = "Better ROAS ({$c2['roas']}x vs {$c1['roas']}x)"; }

        $winner = $c1Score >= $c2Score ? $c1 : $c2;
        return ['campaign_name' => $winner['name'], 'reasons' => array_slice($reasons, 0, 3)];
    }

    /**
     * Generate daily trend data (matching TS behavior).
     * Uses active campaign distribution instead of just creation date filter.
     */
    private function generateTrendData($campaigns, int $days): array
    {
        $trends = [];
        $totalImpressions = (int) $campaigns->sum('impressions');
        $totalSpent = (float) $campaigns->sum('spent');
        $totalClicks = (int) $campaigns->sum('clicks');
        $totalConversions = (int) $campaigns->sum('conversions');
        $totalCampaigns = max($campaigns->count(), 1);

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateStr = $date->format('Y-m-d');

            // Count campaigns active on this day (created <= date AND endDate >= date)
            $activeCount = $campaigns->filter(function ($c) use ($date) {
                $created = $c->created_at;
                $endDate = $c->end_date ? \Carbon\Carbon::parse($c->end_date) : now();
                return $created <= $date && $endDate >= $date;
            })->count();

            $weight = max($activeCount, 1) / $totalCampaigns;

            $impressions = round(($totalImpressions / $days) * $weight);
            $clicks = round(($totalClicks / $days) * $weight);
            $spent = round(($totalSpent / $days) * $weight, 2);
            $conversions = round(($totalConversions / $days) * $weight);

            $trends[] = [
                'date' => $dateStr,
                'impressions' => (int) $impressions,
                'clicks' => (int) $clicks,
                'spent' => $spent,
                'conversions' => (int) $conversions,
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
            ];
        }

        return $trends;
    }

    /**
     * Get budget optimization insights for all active campaigns.
     * Analyzes budget utilization, efficiency, and provides actionable recommendations.
     */
    public function getBudgetOptimization(): array
    {
        $campaigns = AdCampaign::where('status', 'ACTIVE')->orWhere('status', 'PAUSED')->get();

        $totalBudget = (float) $campaigns->sum('budget');
        $totalSpent = (float) $campaigns->sum('spent');
        $totalImpressions = (int) $campaigns->sum('impressions');
        $totalClicks = (int) $campaigns->sum('clicks');
        $totalConversions = (int) $campaigns->sum('conversions');
        $activeCount = $campaigns->where('status', 'ACTIVE')->count();

        // Per-campaign efficiency
        $campaignEfficiency = [];
        foreach ($campaigns as $c) {
            $budget = (float) ($c->budget ?? 0);
            $spent = (float) ($c->spent ?? 0);
            $clicks = (int) $c->clicks;
            $impressions = (int) $c->impressions;
            $conversions = (int) $c->conversions;

            $ctr = $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
            $cpc = $clicks > 0 ? $spent / $clicks : 0;
            $cpa = $conversions > 0 ? $spent / $conversions : 0;
            $budgetUtilization = $budget > 0 ? ($spent / $budget) * 100 : 0;
            $efficiency = $spent > 0 && $conversions > 0 ? ($conversions / $spent) * 1000 : 0;

            $campaignEfficiency[] = [
                'id' => $c->id,
                'name' => $c->name,
                'platform' => $c->platform,
                'status' => $c->status,
                'budget' => $budget,
                'spent' => $spent,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'conversions' => $conversions,
                'ctr' => round($ctr, 2),
                'cpc' => round($cpc, 2),
                'cpa' => round($cpa, 2),
                'budget_utilization' => round($budgetUtilization, 1),
                'efficiency_score' => round($efficiency, 2),
            ];
        }

        // Generate recommendations
        $recommendations = [];

        // Underutilized budget campaigns
        $underutilized = array_filter($campaignEfficiency, fn($c) =>
            $c['status'] === 'ACTIVE' && $c['budget_utilization'] < 30
        );
        foreach ($underutilized as $c) {
            $recommendations[] = [
                'type' => 'underutilized',
                'campaign_id' => $c['id'],
                'campaign_name' => $c['name'],
                'severity' => 'warning',
                'title' => "{$c['name']} — Budget Underutilized",
                'detail' => "Only {$c['budget_utilization']}% of ₹" . number_format($c['budget']) . " budget used.",
                'action' => 'Consider lowering budget or expanding audience targeting.',
            ];
        }

        // Low ROI campaigns (high spend, low conversions)
        $lowROI = array_filter($campaignEfficiency, fn($c) =>
            $c['efficiency_score'] < 1 && $c['spent'] > 1000
        );
        foreach ($lowROI as $c) {
            $recommendations[] = [
                'type' => 'low-roi',
                'campaign_id' => $c['id'],
                'campaign_name' => $c['name'],
                'severity' => 'critical',
                'title' => "{$c['name']} — Low ROI",
                'detail' => "₹" . number_format($c['spent']) . " spent, only {$c['conversions']} conversions (CPA: ₹" . number_format($c['cpa'], 0) . ").",
                'action' => 'Pause campaign, refresh creatives, or narrow audience targeting.',
            ];
        }

        // High performers — recommend budget increase
        $highPerformer = array_filter($campaignEfficiency, fn($c) =>
            $c['efficiency_score'] > 5 && $c['ctr'] > 2 && $c['status'] === 'ACTIVE'
        );
        $highPerformer = array_slice($highPerformer, 0, 3);
        foreach ($highPerformer as $c) {
            $recommendations[] = [
                'type' => 'high-performer',
                'campaign_id' => $c['id'],
                'campaign_name' => $c['name'],
                'severity' => 'success',
                'title' => "{$c['name']} — High Performer!",
                'detail' => "{$c['ctr']}% CTR · ₹{$c['cpc']} CPC · {$c['efficiency_score']} conversions/₹1K",
                'action' => 'Consider increasing budget by 20-30% to maximize ROI.',
            ];
        }

        // Budget running out
        $budgetRunningOut = array_filter($campaignEfficiency, fn($c) =>
            $c['budget'] > 0 && $c['spent'] > 0 && ($c['spent'] / max($c['budget'], 1)) > 0.85 && $c['status'] === 'ACTIVE'
        );
        foreach ($budgetRunningOut as $c) {
            $recommendations[] = [
                'type' => 'budget-exhausted',
                'campaign_id' => $c['id'],
                'campaign_name' => $c['name'],
                'severity' => 'warning',
                'title' => "{$c['name']} — Budget Running Out",
                'detail' => "" . round(($c['spent'] / max($c['budget'], 1)) * 100) . "% of budget used.",
                'action' => 'Increase budget or adjust end dates to prevent premature stopping.',
            ];
        }

        // Summary
        $summary = [
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'total_impressions' => $totalImpressions,
            'total_clicks' => $totalClicks,
            'total_conversions' => $totalConversions,
            'active_campaigns' => $activeCount,
            'total_campaigns' => $campaigns->count(),
            'budget_utilization' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0,
            'overall_efficiency' => $totalSpent > 0 ? round(($totalConversions / $totalSpent) * 1000, 2) : 0,
        ];

        usort($recommendations, fn($a, $b) =>
            ['critical' => 0, 'warning' => 1, 'success' => 2][$a['severity']] <=> ['critical' => 0, 'warning' => 1, 'success' => 2][$b['severity']]
        );

        return [
            'summary' => $summary,
            'campaigns' => $campaignEfficiency,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Get available ad template presets.
     */
    public function getAdTemplates(): array
    {
        return [
            [
                'name' => 'Flash Sale',
                'description' => 'Urgency-driven. Limited time offer with countdown feel.',
                'platform' => 'INSTAGRAM',
                'objective' => 'Sales & Conversions',
                'tone' => 'urgent',
                'icon' => 'Zap',
                'gradient' => 'from-red-500 to-orange-500',
                'headline_sample' => '⚡ FLASH SALE: 50% OFF!',
                'copy_sample' => 'Limited stock! Grab your favorites before they are gone. Free shipping on orders above ₹999.',
                'cta_sample' => 'SHOP NOW',
                'best_for' => 'Clearing inventory, seasonal sales, discount events',
            ],
            [
                'name' => 'New Arrivals',
                'description' => 'Showcase your newest products with style and enthusiasm.',
                'platform' => 'FACEBOOK',
                'objective' => 'Brand Awareness',
                'tone' => 'luxury',
                'icon' => 'Sparkles',
                'gradient' => 'from-purple-500 to-pink-500',
                'headline_sample' => '✨ Just Landed — Shop the New Collection',
                'copy_sample' => 'Fresh off the runway. Our latest drop is here with premium quality you will love.',
                'cta_sample' => 'EXPLORE NEW',
                'best_for' => 'Product launches, seasonal collections, brand refreshes',
            ],
            [
                'name' => 'Best Sellers',
                'description' => 'Social proof-driven. Highlight top-rated products.',
                'platform' => 'GOOGLE',
                'objective' => 'Traffic & Sales',
                'tone' => 'professional',
                'icon' => 'Crown',
                'gradient' => 'from-amber-500 to-yellow-500',
                'headline_sample' => 'Best Sellers | Top Rated Products',
                'copy_sample' => 'See what everyone is loving! Our most popular picks with verified 5-star reviews.',
                'cta_sample' => 'SHOP BESTSELLERS',
                'best_for' => 'Top products, high-margin items, customer favorites',
            ],
            [
                'name' => 'Seasonal Promo',
                'description' => 'Festive/holiday themed campaign for special occasions.',
                'platform' => 'INSTAGRAM',
                'objective' => 'Engagement',
                'tone' => 'friendly',
                'icon' => 'Tag',
                'gradient' => 'from-green-500 to-teal-500',
                'headline_sample' => '🎉 Festive Special — Extra 20% Off!',
                'copy_sample' => 'Celebrate the season with us! Exclusive discounts on your favorite styles.',
                'cta_sample' => 'GRAB THE DEAL',
                'best_for' => 'Festivals, holidays, special occasions, celebrations',
            ],
            [
                'name' => 'Brand Awareness',
                'description' => 'Build brand recognition and tell your story.',
                'platform' => 'FACEBOOK',
                'objective' => 'Reach & Awareness',
                'tone' => 'professional',
                'icon' => 'Megaphone',
                'gradient' => 'from-blue-500 to-indigo-500',
                'headline_sample' => 'Discover Premium Quality | Brand Name',
                'copy_sample' => 'We craft products that stand the test of time. Join thousands of happy customers today.',
                'cta_sample' => 'LEARN MORE',
                'best_for' => 'New brands, rebranding, market expansion',
            ],
            [
                'name' => 'WhatsApp Broadcast',
                'description' => 'Direct promotional broadcast to your subscribers.',
                'platform' => 'WHATSAPP',
                'objective' => 'Direct Messaging',
                'tone' => 'friendly',
                'icon' => 'MessageCircle',
                'gradient' => 'from-green-500 to-emerald-500',
                'headline_sample' => 'Hey! Exclusive offer just for you 🎉',
                'copy_sample' => 'As a valued subscriber, enjoy an extra 15% off your next order. Use code: WELCOME15',
                'cta_sample' => 'SHOP NOW',
                'best_for' => 'Subscriber engagement, repeat purchases, loyalty rewards',
            ],
            [
                'name' => 'Product Launch',
                'description' => 'Big reveal for a new product with maximum impact.',
                'platform' => 'INSTAGRAM',
                'objective' => 'Awareness & Sales',
                'tone' => 'luxury',
                'icon' => 'Sparkles',
                'gradient' => 'from-indigo-500 to-purple-600',
                'headline_sample' => 'Introducing: [Product Name] — Redefined.',
                'copy_sample' => 'After months of perfecting, we are thrilled to unveil our latest innovation. Limited first-batch available.',
                'cta_sample' => 'PRE-ORDER NOW',
                'best_for' => 'New product launches, pre-orders, exclusive drops',
            ],
            [
                'name' => 'Retargeting',
                'description' => 'Re-engage visitors who did not complete purchase.',
                'platform' => 'FACEBOOK',
                'objective' => 'Conversions',
                'tone' => 'urgent',
                'icon' => 'Target',
                'gradient' => 'from-rose-500 to-red-600',
                'headline_sample' => 'Still Thinking? It is Waiting For You!',
                'copy_sample' => 'You left something behind! Complete your purchase now and get free shipping.',
                'cta_sample' => 'COMPLETE ORDER',
                'best_for' => 'Cart abandoners, window shoppers, past visitors',
            ],
        ];
    }

    /**
     * Generate data-driven recommendations (matching TS behavior exactly).
     */
    private function generateRecommendations(array $platforms, array $summary): array
    {
        $recs = [];

        if ($summary['budget_utilization'] < 50) {
            $recs[] = '📊 Budget underutilized — Increase daily budgets or extend targeting to spend more of your allocated budget.';
        } elseif ($summary['budget_utilization'] > 90) {
            $recs[] = '📊 Budget nearly exhausted — Consider increasing campaign budgets to maintain momentum.';
        }

        foreach ($platforms as $p) {
            if ($p['avg_ctr'] < 1) {
                $recs[] = "🎯 {$p['platform']} CTR is low ({$p['avg_ctr']}%) — Try refreshing ad creatives, testing new headlines, or narrowing audience targeting.";
            }
            if ($p['avg_cpc'] > 50 && $p['avg_ctr'] < 2) {
                $recs[] = "💰 {$p['platform']} CPC is high (\${$p['avg_cpc']}) — Focus on improving Quality Score / Relevance Score with better targeting.";
            }
            if ($p['conversion_rate'] > 5) {
                $recs[] = "🔥 {$p['platform']} is converting well at {$p['conversion_rate']}% — Consider increasing budget for this platform.";
            }
        }

        // Extra check matching TS: overall CTR below 1.5%
        if ($summary['average_ctr'] < 1.5) {
            $recs[] = '🎨 Overall CTR is below 1.5% — Run A/B tests on ad creatives and test different audience segments.';
        }

        if (count($platforms) > 1) {
            $bestPlatform = $platforms[0];
            foreach ($platforms as $p) {
                if ($p['roas'] > $bestPlatform['roas']) $bestPlatform = $p;
            }
            $recs[] = "🏆 Best performing platform: {$bestPlatform['platform']} (ROAS: {$bestPlatform['roas']}x) — Shift more budget here.";
        }

        if (empty($recs)) {
            $recs[] = '✅ All campaigns are performing well! Keep monitoring and testing new creatives.';
        }

        return $recs;
    }
}
