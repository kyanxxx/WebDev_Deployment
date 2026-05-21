<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_username', [$this, 'formatUsername']),
        ];
    }

    public function formatUsername(string $username): string
    {
        // First, normalize to handle all cases: Kianharvey, kianharvey, kianHarvey, KIANHARVEY
        // Strategy: Insert space before capital letters (except the first), then handle all-lowercase/all-uppercase cases
        
        // Step 1: Insert space before capital letters (except the first one)
        // This handles: KianHarvey, kianHarvey -> "Kian Harvey", "kian Harvey"
        $formatted = preg_replace('/([a-z])([A-Z])/', '$1 $2', $username);
        
        // Step 2: If still no spaces (all lowercase or all uppercase), try to detect word boundaries
        // Only split if it makes sense (like "kianharvey" -> "Kian Harvey")
        // For single long names (like "abenis"), just capitalize first letter
        if (strpos($formatted, ' ') === false && strlen($formatted) > 4) {
            $length = strlen($formatted);
            
            // Try to find the best split point
            // Common first names are 3-6 letters, so try splitting in that range
            // Prefer splits that create roughly equal-length words or follow common patterns
            $bestSplit = null;
            $bestScore = 0;
            
            for ($splitPos = 3; $splitPos <= min(7, $length - 3); $splitPos++) {
                $part1 = substr($formatted, 0, $splitPos);
                $part2 = substr($formatted, $splitPos);
                
                // Both parts should be at least 3 characters
                if (strlen($part1) >= 3 && strlen($part2) >= 3) {
                    // Score: strongly prefer position 4 (most common first name length)
                    // then position 5, then consider balance
                    $score = 0;
                    if ($splitPos == 4) {
                        $score += 20; // Strongly prefer position 4
                    } elseif ($splitPos == 5) {
                        $score += 10; // Prefer position 5
                    } elseif ($splitPos >= 3 && $splitPos <= 6) {
                        $score += 5; // Slight preference for positions 3-6
                    }
                    // Prefer when both parts are similar length (but less important than position)
                    $lengthDiff = abs(strlen($part1) - strlen($part2));
                    $score += (5 - min($lengthDiff, 5));
                    
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestSplit = $splitPos;
                    }
                }
            }
            
            // Only split if we found a good split point (score threshold)
            // For single long names, if no good split is found, just capitalize first letter
            if ($bestSplit !== null && $bestScore >= 15) {
                $formatted = substr($formatted, 0, $bestSplit) . ' ' . substr($formatted, $bestSplit);
            }
            // If no good split found (single long name), leave as is - it will be capitalized in Step 3
        }
        
        // Step 3: Split by spaces and capitalize first letter of each word, lowercase the rest
        $words = explode(' ', $formatted);
        $formattedWords = array_map(function($word) {
            // Capitalize first letter, lowercase the rest
            return ucfirst(strtolower($word));
        }, $words);
        
        return implode(' ', $formattedWords);
    }
}

